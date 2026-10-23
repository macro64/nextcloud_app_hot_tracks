<?php

declare(strict_types=1);

namespace OCA\HotTracks\BackgroundJob;

use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Service\SyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class SyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ConnectionMapper $connections,
		private SyncService $sync,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(3600);
	}

	protected function run(mixed $argument): void {
		foreach ($this->connections->findAll() as $connection) {
			$uid = $connection->getUid();
			try {
				$result = $this->sync->syncUser($uid);
			} catch (\Throwable $e) {
				$this->logger->warning('Sync with the activity tracking app failed', [
					'app' => 'hot_tracks',
					'uid' => $uid,
					'exception' => $e,
				]);
				continue;
			}
			if ($result->rateLimited) {
				$this->logger->info('Activity tracking app rate limit reached, further sync waits for the next run', [
					'app' => 'hot_tracks',
				]);
				return;
			}
		}
	}
}
