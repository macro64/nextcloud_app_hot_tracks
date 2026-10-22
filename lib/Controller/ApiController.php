<?php

declare(strict_types=1);

namespace OCA\Strava\Controller;

use OCA\Strava\Db\Activity;
use OCA\Strava\Db\ActivityMapper;
use OCA\Strava\Db\ConnectionMapper;
use OCA\Strava\Exception\AuthBrokerException;
use OCA\Strava\Exception\StravaApiException;
use OCA\Strava\Service\ActivityFiles;
use OCA\Strava\Service\ActivityPath;
use OCA\Strava\Service\AppConfig;
use OCA\Strava\Service\SyncService;
use OCA\Strava\Service\WeekStats;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\NotFoundException;
use OCP\IDateTimeZone;
use OCP\IRequest;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private AppConfig $appConfig,
		private ConnectionMapper $connections,
		private ActivityMapper $activities,
		private ActivityFiles $files,
		private SyncService $sync,
		private IDateTimeZone $timeZones,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function overview(): JSONResponse {
		$uid = $this->uid();
		$connection = $this->connections->findByUid($uid);
		$timezone = $this->timeZones->getTimeZone($uid);
		[$from, $to] = WeekStats::window($timezone);
		$rows = $connection === null ? [] : $this->activities->findBetween($uid, $from, $to);
		$payloads = array_map($this->present(...), $rows);
		$stats = WeekStats::summarize(array_map(static function (array $row): array {
			return [
				'sport' => $row['sport'],
				'distance' => $row['distance'],
				'movingTime' => $row['movingTime'],
				'elevation' => $row['elevation'],
			];
		}, $payloads));

		return new JSONResponse([
			'configured' => $this->appConfig->isConfigured(),
			'connected' => $connection !== null,
			'athleteName' => $connection?->getAthleteName() ?? '',
			'timeZone' => $timezone->getName(),
			'stats' => $stats,
			'activities' => $payloads,
		]);
	}

	#[NoAdminRequired]
	public function sync(): JSONResponse {
		try {
			$result = $this->sync->syncUser($this->uid());
		} catch (AuthBrokerException) {
			return new JSONResponse(['error' => 'Strava-Zugriff abgelaufen. Bitte die Verbindung erneuern.'], Http::STATUS_BAD_GATEWAY);
		} catch (StravaApiException $e) {
			if ($e->getCode() === 401) {
				return new JSONResponse(['error' => 'Strava hat den Zugriff abgelehnt. Bitte die Verbindung erneuern.'], Http::STATUS_UNAUTHORIZED);
			}
			return new JSONResponse(['error' => 'Strava-Anfrage fehlgeschlagen.'], Http::STATUS_BAD_GATEWAY);
		} catch (\RuntimeException $e) {
			if ($e->getMessage() === 'Nicht verbunden') {
				return new JSONResponse(['error' => 'Noch nicht mit Strava verbunden.'], Http::STATUS_CONFLICT);
			}
			throw $e;
		}

		if ($result->busy) {
			return new JSONResponse(['error' => 'Ein Abgleich läuft bereits.'], Http::STATUS_CONFLICT);
		}

		return new JSONResponse([
			'created' => $result->created,
			'skipped' => $result->skipped,
			'rateLimited' => $result->rateLimited,
		]);
	}

	#[NoAdminRequired]
	public function disconnect(): JSONResponse {
		$this->connections->deleteByUid($this->uid());
		return new JSONResponse(['ok' => true]);
	}

	#[NoAdminRequired]
	public function activity(int $stravaId): JSONResponse {
		$row = $this->activities->findByStravaId($this->uid(), $stravaId);
		if ($row === null) {
			return new JSONResponse(['error' => 'Aktivität nicht gefunden.'], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse($this->present($row));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function gpx(int $stravaId): DataDownloadResponse|JSONResponse {
		$row = $this->activities->findByStravaId($this->uid(), $stravaId);
		if ($row === null || $row->getFileId() === null) {
			return new JSONResponse(['error' => 'Keine GPX-Datei.'], Http::STATUS_NOT_FOUND);
		}
		try {
			$content = $this->files->readById($this->uid(), (int)$row->getFileId());
		} catch (NotFoundException) {
			return new JSONResponse(['error' => 'GPX-Datei fehlt im Ordner Strava.'], Http::STATUS_NOT_FOUND);
		}
		$name = $this->downloadName($row);
		return new DataDownloadResponse($content, $name, 'application/gpx+xml');
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function weekZip(): DataDownloadResponse|JSONResponse {
		if (!class_exists(\ZipArchive::class)) {
			return new JSONResponse(['error' => 'ZIP ist auf diesem Server nicht verfügbar.'], Http::STATUS_NOT_IMPLEMENTED);
		}
		$uid = $this->uid();
		$timezone = $this->timeZones->getTimeZone($uid);
		[$from, $to] = WeekStats::window($timezone);
		$rows = $this->activities->findBetween($uid, $from, $to);
		$tmp = tempnam(sys_get_temp_dir(), 'strava');
		if ($tmp === false) {
			return new JSONResponse(['error' => 'ZIP konnte nicht angelegt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		@unlink($tmp);
		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::CREATE) !== true) {
			@unlink($tmp);
			return new JSONResponse(['error' => 'ZIP konnte nicht angelegt werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		$added = 0;
		foreach ($rows as $row) {
			if ($row->getFileId() === null) {
				continue;
			}
			try {
				$content = $this->files->readById($uid, (int)$row->getFileId());
			} catch (NotFoundException) {
				continue;
			}
			$zip->addFromString($this->downloadName($row), $content);
			$added++;
		}
		$zip->close();
		if ($added === 0) {
			@unlink($tmp);
			return new JSONResponse(['error' => 'Keine GPX-Dateien in den letzten 7 Tagen.'], Http::STATUS_NOT_FOUND);
		}
		$bytes = file_get_contents($tmp);
		@unlink($tmp);
		if ($bytes === false) {
			return new JSONResponse(['error' => 'ZIP konnte nicht gelesen werden.'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataDownloadResponse($bytes, 'strava-7-tage.zip', 'application/zip');
	}

	private function uid(): string {
		if ($this->userId === null || $this->userId === '') {
			throw new \RuntimeException('Keine Anmeldung');
		}
		return $this->userId;
	}

	/**
	 * @return array{stravaId: int, name: string, sport: string, startTime: int, distance: float, movingTime: int, elapsedTime: int, elevation: float, hasGpx: bool}
	 */
	private function present(Activity $activity): array {
		return [
			'stravaId' => $activity->getStravaId(),
			'name' => $activity->getName(),
			'sport' => $activity->getSportType(),
			'startTime' => $activity->getStartTime(),
			'distance' => $activity->getDistance(),
			'movingTime' => $activity->getMovingTime(),
			'elapsedTime' => $activity->getElapsedTime(),
			'elevation' => $activity->getElevation(),
			'hasGpx' => $activity->getFileId() !== null,
		];
	}

	private function downloadName(Activity $activity): string {
		$timezone = $this->timeZones->getTimeZone($activity->getUid());
		$start = (new \DateTimeImmutable('@' . $activity->getStartTime()))->setTimezone($timezone);
		return ActivityPath::filename($start, $activity->getStravaId(), $activity->getName());
	}
}
