<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCA\HotTracks\Db\Activity;
use OCA\HotTracks\Db\ActivityMapper;
use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Exception\RateLimitException;
use OCA\HotTracks\Exception\TrackingApiException;
use OCP\IConfig;
use OCP\IDateTimeZone;

class SyncService {
	private const INITIAL_WINDOW = 30 * 86400;
	private const OVERLAP = 86400;
	private const LOCK_SECONDS = 900;

	public function __construct(
		private ConnectionMapper $connections,
		private ActivityMapper $activities,
		private TokenStore $tokens,
		private TrackingClient $tracking,
		private GpxBuilder $gpxBuilder,
		private ActivityFiles $files,
		private SportPreferences $sports,
		private IDateTimeZone $timeZones,
		private IConfig $config,
	) {
	}

	/**
	 * @param string|null $range Monat, Jahr, 5y oder all – null = inkrementell (Hintergrundjob)
	 */
	public function syncUser(string $uid, ?string $range = null): SyncResult {
		$connection = $this->connections->findByUid($uid);
		if ($connection === null) {
			throw new \RuntimeException('Not connected');
		}
		if ($range !== null && !SyncRange::isValid($range)) {
			throw new \InvalidArgumentException('Unbekannter Zeitraum');
		}
		if ($this->locked($uid)) {
			return new SyncResult(0, 0, 0, false, true);
		}
		$this->lock($uid);

		$created = 0;
		$updated = 0;
		$skipped = 0;
		try {
			$token = $this->tokens->accessToken($connection);
			if ($range !== null) {
				$after = SyncRange::after($range);
			} else {
				$after = $connection->getLastSyncAfter();
				if ($after === null || $after <= 0) {
					$after = time() - self::INITIAL_WINDOW;
				}
			}
			$maxPages = SyncRange::maxPages($range);
			for ($page = 1; $page <= $maxPages; $page++) {
				$batch = $this->tracking->listActivitiesPage($token, $after, $page);
				foreach ($batch as $summary) {
					$activityId = (int)($summary['id'] ?? 0);
					if ($activityId <= 0) {
						continue;
					}
					$summarySport = trim((string)($summary['sport_type'] ?? $summary['type'] ?? ''));
					if ($summarySport !== '' && !$this->sports->allows($uid, $summarySport)) {
						$skipped++;
						continue;
					}
					$existing = $this->activities->findByActivityId($uid, $activityId);
					// Vollständige Einträge überspringen; ohne GPX erneut holen (Upsert).
					if ($existing !== null && $existing->getFileId() !== null) {
						$skipped++;
						continue;
					}
					try {
						$detail = $this->tracking->activity($token, $activityId);
					} catch (TrackingApiException $e) {
						if ($e->getCode() !== 404) {
							throw $e;
						}
						$detail = $summary;
					}
					$detailSport = trim((string)($detail['sport_type'] ?? $detail['type'] ?? $summarySport));
					if ($detailSport !== '' && !$this->sports->allows($uid, $detailSport)) {
						$skipped++;
						continue;
					}
					try {
						$streams = $this->tracking->streams($token, $activityId);
					} catch (TrackingApiException $e) {
						if ($e->getCode() !== 404) {
							throw $e;
						}
						$streams = [];
					}
					$outcome = $this->store($uid, $activityId, $detail, $streams, $existing);
					if ($outcome === 'created') {
						$created++;
					} elseif ($outcome === 'updated') {
						$updated++;
					} else {
						$skipped++;
					}
				}
				if (count($batch) < 50) {
					break;
				}
			}
			$connection->setLastSyncAfter(time() - self::OVERLAP);
			$this->connections->update($connection);
			return new SyncResult($created, $updated, $skipped, false);
		} catch (RateLimitException) {
			return new SyncResult($created, $updated, $skipped, true);
		} finally {
			$this->unlock($uid);
		}
	}

	/**
	 * Eine Aktivität sofort nachziehen. Überspringt vollständige Einträge nicht,
	 * damit eine Änderung an Name oder Strecke ankommt.
	 *
	 * @return 'created'|'updated'|'skipped'|'missing'
	 */
	public function pullActivity(string $uid, int $activityId): string {
		$connection = $this->connections->findByUid($uid);
		if ($connection === null) {
			throw new \RuntimeException('Not connected');
		}
		$token = $this->tokens->accessToken($connection);
		$existing = $this->activities->findByActivityId($uid, $activityId);
		$previousFileId = $existing?->getFileId();
		try {
			$detail = $this->tracking->activity($token, $activityId);
		} catch (TrackingApiException $e) {
			if ($e->getCode() === 404) {
				return 'missing';
			}
			throw $e;
		}
		$detailSport = trim((string)($detail['sport_type'] ?? $detail['type'] ?? ''));
		if ($detailSport !== '' && !$this->sports->allows($uid, $detailSport)) {
			return 'skipped';
		}
		try {
			$streams = $this->tracking->streams($token, $activityId);
		} catch (TrackingApiException $e) {
			if ($e->getCode() !== 404) {
				throw $e;
			}
			$streams = [];
		}
		$outcome = $this->store($uid, $activityId, $detail, $streams, $existing);
		if ($previousFileId !== null) {
			$fresh = $this->activities->findByActivityId($uid, $activityId);
			$newFileId = $fresh?->getFileId();
			if ($newFileId !== null && $newFileId !== $previousFileId) {
				try {
					$this->files->deleteById($uid, $previousFileId);
				} catch (\Throwable) {
				}
			}
		}
		return $outcome;
	}

	public function removeActivity(string $uid, int $activityId): bool {
		$existing = $this->activities->findByActivityId($uid, $activityId);
		if ($existing === null) {
			return false;
		}
		$fileId = $existing->getFileId();
		if ($fileId !== null) {
			try {
				$this->files->deleteById($uid, $fileId);
			} catch (\Throwable) {
			}
		}
		$this->activities->delete($existing);
		return true;
	}

	/**
	 * @param array<string, mixed> $detail
	 * @param array<string, mixed> $streams
	 * @return 'created'|'updated'|'skipped'
	 */
	private function store(string $uid, int $activityId, array $detail, array $streams, ?Activity $existing): string {
		$name = trim((string)($detail['name'] ?? ''));
		if ($name === '') {
			$name = 'Activity ' . $activityId;
		}
		$name = mb_substr($name, 0, 512);
		$sport = trim((string)($detail['sport_type'] ?? $detail['type'] ?? 'Activity'));
		if ($sport === '') {
			$sport = 'Activity';
		}
		$sport = mb_substr($sport, 0, 64);
		$startRaw = (string)($detail['start_date'] ?? '');
		$start = $startRaw !== '' ? new \DateTimeImmutable($startRaw) : new \DateTimeImmutable('now');
		$startTime = $start->getTimestamp();

		$latlng = $this->series($streams, 'latlng');
		$altitude = $this->series($streams, 'altitude');
		$time = $this->series($streams, 'time');
		$gpx = $this->gpxBuilder->build($name, $sport, $startTime, $latlng, $altitude, $time);

		$fileId = null;
		if ($gpx !== '') {
			$local = $start->setTimezone($this->timeZones->getTimeZone(false, $uid));
			$fileId = $this->files->writeGpx($uid, $local, $activityId, $name, $gpx);
		}

		$distance = (float)($detail['distance'] ?? 0);
		$movingTime = (int)($detail['moving_time'] ?? 0);
		$elapsedTime = (int)($detail['elapsed_time'] ?? 0);
		$elevation = (float)($detail['total_elevation_gain'] ?? 0);

		$entity = $existing ?? new Activity();
		$entity->setUid($uid);
		$entity->setActivityId($activityId);
		$entity->setName($name);
		$entity->setSportType($sport);
		$entity->setStartTime($startTime);
		// Erst Nicht-Default setzen, sonst lässt Entity 0-Werte beim Speichern weg.
		$entity->setDistance($distance + 1.0);
		$entity->setDistance($distance);
		$entity->setMovingTime($movingTime + 1);
		$entity->setMovingTime($movingTime);
		$entity->setElapsedTime($elapsedTime + 1);
		$entity->setElapsedTime($elapsedTime);
		$entity->setElevation($elevation + 1.0);
		$entity->setElevation($elevation);
		if ($fileId !== null) {
			$entity->setFileId($fileId);
		}
		$entity->setSyncedAt(time());
		$entity->markRequiredForInsert();

		if ($existing !== null) {
			$this->activities->update($entity);
			return 'updated';
		}

		try {
			$this->activities->insert($entity);
		} catch (\Throwable $e) {
			$again = $this->activities->findByActivityId($uid, $activityId);
			if ($again === null) {
				throw $e;
			}
			$entity->setId($again->getId());
			$this->activities->update($entity);
			return 'updated';
		}
		return 'created';
	}

	/**
	 * @param array<string, mixed> $streams
	 * @return list<mixed>
	 */
	private function series(array $streams, string $key): array {
		$stream = $streams[$key] ?? null;
		if (!is_array($stream)) {
			return [];
		}
		$data = $stream['data'] ?? null;
		return is_array($data) ? array_values($data) : [];
	}

	private function locked(string $uid): bool {
		$until = (int)$this->config->getUserValue($uid, Application::APP_ID, 'sync_lock', '0');
		return $until > time();
	}

	private function lock(string $uid): void {
		$this->config->setUserValue($uid, Application::APP_ID, 'sync_lock', (string)(time() + self::LOCK_SECONDS));
	}

	private function unlock(string $uid): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, 'sync_lock');
	}
}
