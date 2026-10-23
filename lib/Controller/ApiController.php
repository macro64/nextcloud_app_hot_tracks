<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Db\Activity;
use OCA\HotTracks\Db\ActivityMapper;
use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Exception\AuthBrokerException;
use OCA\HotTracks\Exception\TrackingApiException;
use OCA\HotTracks\Service\ActivityFiles;
use OCA\HotTracks\Service\ActivityPath;
use OCA\HotTracks\Service\SportGroup;
use OCA\HotTracks\Service\SportPreferences;
use OCA\HotTracks\Service\SyncRange;
use OCA\HotTracks\Service\SyncService;
use OCA\HotTracks\Service\TrackPoints;
use OCA\HotTracks\Service\UserAuthConfig;
use OCA\HotTracks\Service\WeekStats;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\Files\NotFoundException;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\IRequest;

class ApiController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private UserAuthConfig $userAuth,
		private ConnectionMapper $connections,
		private ActivityMapper $activities,
		private ActivityFiles $files,
		private SyncService $sync,
		private SportPreferences $sports,
		private IDateTimeZone $timeZones,
		private IL10N $l,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function overview(): JSONResponse {
		$uid = $this->uid();
		$connection = $this->connections->findByUid($uid);
		$timezone = $this->timeZones->getTimeZone(false, $uid);
		$modes = $this->sports->resolveFilter($uid, $this->requestSportFilter());
		$stored = $connection === null ? [] : $this->activities->findByUid($uid);
		$payloads = [];
		foreach ($stored as $activity) {
			$sport = $activity->getSportType();
			if (!SportGroup::matchesAny($modes, $sport)) {
				continue;
			}
			$payloads[] = $this->present($activity);
		}
		$stats = WeekStats::summarize(array_map(static function (array $row): array {
			return [
				'sport' => $row['sport'],
				'distance' => $row['distance'],
				'movingTime' => $row['movingTime'],
				'elevation' => $row['elevation'],
			];
		}, $payloads));

		$sportOptions = $this->sports->filterOptions($uid);
		return new JSONResponse([
			'configured' => $this->userAuth->isConfigured($uid),
			'connected' => $connection !== null,
			'athleteName' => $connection?->getAthleteName() ?? '',
			'timeZone' => $timezone->getName(),
			'storedCount' => $connection === null ? 0 : $this->activities->countByUid($uid),
			'stats' => $stats,
			'mode' => count($modes) === 1 ? $modes[0] : 'all',
			'modes' => $modes,
			'sportOptions' => $sportOptions,
			'activities' => $payloads,
		]);
	}

	#[NoAdminRequired]
	public function sync(): JSONResponse {
		$range = trim((string)$this->request->getParam('range', SyncRange::MONTH));
		if ($range === '') {
			$range = SyncRange::MONTH;
		}
		if (!SyncRange::isValid($range)) {
			return new JSONResponse(['error' => $this->l->t('Unknown time range.')], Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->sync->syncUser($this->uid(), $range);
		} catch (AuthBrokerException) {
			return new JSONResponse(['error' => $this->l->t('Access to the activity tracking app has expired. Please reconnect.')], Http::STATUS_BAD_GATEWAY);
		} catch (TrackingApiException $e) {
			if ($e->getCode() === 401) {
				return new JSONResponse(['error' => $this->l->t('The activity tracking app denied access. Please reconnect.')], Http::STATUS_UNAUTHORIZED);
			}
			return new JSONResponse(['error' => $this->l->t('Request to the activity tracking app failed.')], Http::STATUS_BAD_GATEWAY);
		} catch (\RuntimeException $e) {
			if ($e->getMessage() === 'Not connected') {
				return new JSONResponse(['error' => $this->l->t('Not connected to the activity tracking app yet.')], Http::STATUS_CONFLICT);
			}
			throw $e;
		} catch (\Throwable) {
			return new JSONResponse(['error' => $this->l->t('Sync failed.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if ($result->busy) {
			return new JSONResponse(['error' => $this->l->t('A sync is already running.')], Http::STATUS_CONFLICT);
		}

		return new JSONResponse([
			'created' => $result->created,
			'updated' => $result->updated,
			'skipped' => $result->skipped,
			'rateLimited' => $result->rateLimited,
			'range' => $range,
		]);
	}

	#[NoAdminRequired]
	public function disconnect(): JSONResponse {
		$this->connections->deleteByUid($this->uid());
		return new JSONResponse(['ok' => true]);
	}

	#[NoAdminRequired]
	public function activity(int $activityId): JSONResponse {
		$row = $this->activities->findByActivityId($this->uid(), $activityId);
		if ($row === null) {
			return new JSONResponse(['error' => $this->l->t('Activity not found.')], Http::STATUS_NOT_FOUND);
		}
		return new JSONResponse($this->present($row));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function gpx(int $activityId): DataDownloadResponse|JSONResponse {
		$row = $this->activities->findByActivityId($this->uid(), $activityId);
		if ($row === null || $row->getFileId() === null) {
			return new JSONResponse(['error' => $this->l->t('No GPX file.')], Http::STATUS_NOT_FOUND);
		}
		try {
			$content = $this->files->readById($this->uid(), (int)$row->getFileId());
		} catch (NotFoundException) {
			return new JSONResponse(['error' => $this->l->t('GPX file is missing in the Hot Tracks folder.')], Http::STATUS_NOT_FOUND);
		}
		$name = $this->downloadName($row);
		return new DataDownloadResponse($content, $name, 'application/gpx+xml');
	}

	#[NoAdminRequired]
	public function weekTracks(): JSONResponse {
		$uid = $this->uid();
		$timezone = $this->timeZones->getTimeZone(false, $uid);
		$range = trim((string)$this->request->getParam('range', 'week'));
		if ($range === 'week' || $range === '') {
			[$from, $to] = WeekStats::window($timezone);
			$range = 'week';
		} elseif (SyncRange::isValid($range)) {
			$from = SyncRange::after($range);
			$to = time();
		} else {
			return new JSONResponse(['error' => $this->l->t('Unknown time range.')], Http::STATUS_BAD_REQUEST);
		}
		$modes = $this->sports->resolveFilter($uid, $this->requestSportFilter());
		$limit = (int)$this->request->getParam('limit', 12);
		$offset = (int)$this->request->getParam('offset', 0);
		if ($limit < 1) {
			$limit = 12;
		}
		if ($limit > 40) {
			$limit = 40;
		}
		if ($offset < 0) {
			$offset = 0;
		}
		// Collect candidates without reading GPX, then parse only the current page.
		$candidates = [];
		foreach ($this->activities->findBetween($uid, $from, $to) as $row) {
			if ($row->getFileId() === null) {
				continue;
			}
			if (!SportGroup::matchesAny($modes, $row->getSportType())) {
				continue;
			}
			$candidates[] = $row;
			if (count($candidates) >= 120) {
				break;
			}
		}
		$total = count($candidates);
		$slice = array_slice($candidates, $offset, $limit);
		$tracks = [];
		foreach ($slice as $row) {
			try {
				$content = $this->files->readById($uid, (int)$row->getFileId());
			} catch (NotFoundException) {
				continue;
			}
			$points = TrackPoints::fromGpx($content);
			if ($points === []) {
				continue;
			}
			$tracks[] = [
				'activityId' => $row->getActivityId(),
				'sport' => $row->getSportType(),
				'points' => $points,
			];
		}
		return new JSONResponse([
			'range' => $range,
			'mode' => count($modes) === 1 ? $modes[0] : 'all',
			'modes' => $modes,
			'modeLabels' => array_map(fn (string $id): string => $this->l->t(SportGroup::label($id)), $modes),
			'sportOptions' => $this->sports->filterOptions($uid),
			'offset' => $offset,
			'limit' => $limit,
			'total' => $total,
			'hasMore' => ($offset + $limit) < $total,
			'tracks' => $tracks,
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function weekZip(): DataDownloadResponse|JSONResponse {
		if (!class_exists(\ZipArchive::class)) {
			return new JSONResponse(['error' => $this->l->t('ZIP is not available on this server.')], Http::STATUS_NOT_IMPLEMENTED);
		}
		$uid = $this->uid();
		$rows = $this->activities->findByUid($uid, 10000);
		$tmp = tempnam(sys_get_temp_dir(), 'hot_tracks');
		if ($tmp === false) {
			return new JSONResponse(['error' => $this->l->t('Could not create the ZIP file.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		@unlink($tmp);
		$zip = new \ZipArchive();
		if ($zip->open($tmp, \ZipArchive::CREATE) !== true) {
			@unlink($tmp);
			return new JSONResponse(['error' => $this->l->t('Could not create the ZIP file.')], Http::STATUS_INTERNAL_SERVER_ERROR);
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
			return new JSONResponse(['error' => $this->l->t('No GPX files stored.')], Http::STATUS_NOT_FOUND);
		}
		$bytes = file_get_contents($tmp);
		@unlink($tmp);
		if ($bytes === false) {
			return new JSONResponse(['error' => $this->l->t('Could not read the ZIP file.')], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		return new DataDownloadResponse($bytes, 'hot-tracks-all.zip', 'application/zip');
	}

	private function uid(): string {
		if ($this->userId === null || $this->userId === '') {
			throw new \RuntimeException('Not logged in');
		}
		return $this->userId;
	}

	/**
	 * Sport filter from the query: sport (preferred) or mode.
	 */
	private function requestSportFilter(): string {
		$sport = trim((string)$this->request->getParam('sport', ''));
		if ($sport !== '') {
			return $sport;
		}
		return trim((string)$this->request->getParam('mode', 'all'));
	}

	/**
	 * @return array{activityId: int, name: string, sport: string, startTime: int, distance: float, movingTime: int, elapsedTime: int, elevation: float, hasGpx: bool}
	 */
	private function present(Activity $activity): array {
		return [
			'activityId' => $activity->getActivityId(),
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
		$timezone = $this->timeZones->getTimeZone(false, $activity->getUid());
		$start = (new \DateTimeImmutable('@' . $activity->getStartTime()))->setTimezone($timezone);
		return ActivityPath::filename($start, $activity->getActivityId(), $activity->getName());
	}
}
