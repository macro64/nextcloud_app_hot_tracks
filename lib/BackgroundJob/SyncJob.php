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
				$this->logger->warning('Abgleich mit der activity tracking app fehlgeschlagen', [
					'app' => 'hot_tracks',
					'uid' => $uid,
					'exception' => $e,
				]);
				continue;
			}
			if ($result->rateLimited) {
				$this->logger->info('Limit der activity tracking app erreicht, weiterer Abgleich wartet auf den nächsten Lauf', [
					'app' => 'hot_tracks',
				]);
				return;
			}
		}
	}
}
