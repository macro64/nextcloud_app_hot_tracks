<?php

declare(strict_types=1);

namespace OCA\Strava\BackgroundJob;

use OCA\Strava\Db\ConnectionMapper;
use OCA\Strava\Service\SyncService;
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
				$this->logger->warning('Strava-Abgleich fehlgeschlagen', [
					'app' => 'strava',
					'uid' => $uid,
					'exception' => $e,
				]);
				continue;
			}
			if ($result->rateLimited) {
				$this->logger->info('Strava-Limit erreicht, weiterer Abgleich wartet auf den nächsten Lauf', [
					'app' => 'strava',
				]);
				return;
			}
		}
	}
}
