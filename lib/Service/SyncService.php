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
		private IDateTimeZone $timeZones,
		private IConfig $config,
	) {
	}

	public function syncUser(string $uid): SyncResult {
		$connection = $this->connections->findByUid($uid);
		if ($connection === null) {
			throw new \RuntimeException('Nicht verbunden');
		}
		if ($this->locked($uid)) {
			return new SyncResult(0, 0, false, true);
		}
		$this->lock($uid);

		$created = 0;
		$skipped = 0;
		try {
			$token = $this->tokens->accessToken($connection);
			$after = $connection->getLastSyncAfter();
			if ($after === null || $after <= 0) {
				$after = time() - self::INITIAL_WINDOW;
			}
			$listed = $this->tracking->listActivities($token, $after);
			foreach ($listed as $summary) {
				$activityId = (int)($summary['id'] ?? 0);
				if ($activityId <= 0) {
					continue;
				}
				if ($this->activities->findByActivityId($uid, $activityId) !== null) {
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
				try {
					$streams = $this->tracking->streams($token, $activityId);
				} catch (TrackingApiException $e) {
					if ($e->getCode() !== 404) {
						throw $e;
					}
					$streams = [];
				}
				if ($this->store($uid, $activityId, $detail, $streams)) {
					$created++;
				} else {
					$skipped++;
				}
			}
			$connection->setLastSyncAfter(time() - self::OVERLAP);
			$this->connections->update($connection);
			return new SyncResult($created, $skipped, false);
		} catch (RateLimitException) {
			return new SyncResult($created, $skipped, true);
		} finally {
			$this->unlock($uid);
		}
	}

	/**
	 * @param array<string, mixed> $detail
	 * @param array<string, mixed> $streams
	 */
	private function store(string $uid, int $activityId, array $detail, array $streams): bool {
		$name = trim((string)($detail['name'] ?? ''));
		if ($name === '') {
			$name = 'Aktivität ' . $activityId;
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
			$local = $start->setTimezone($this->timeZones->getTimeZone($uid));
			$fileId = $this->files->writeGpx($uid, $local, $activityId, $name, $gpx);
		}

		$entity = new Activity();
		$entity->setUid($uid);
		$entity->setActivityId($activityId);
		$entity->setName($name);
		$entity->setSportType($sport);
		$entity->setStartTime($startTime);
		$entity->setDistance((float)($detail['distance'] ?? 0));
		$entity->setMovingTime((int)($detail['moving_time'] ?? 0));
		$entity->setElapsedTime((int)($detail['elapsed_time'] ?? 0));
		$entity->setElevation((float)($detail['total_elevation_gain'] ?? 0));
		if ($fileId !== null) {
			$entity->setFileId($fileId);
		}
		$entity->setSyncedAt(time());
		try {
			$this->activities->insert($entity);
		} catch (\Throwable $e) {
			if ($this->activities->findByActivityId($uid, $activityId) !== null) {
				return false;
			}
			throw $e;
		}
		return true;
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
