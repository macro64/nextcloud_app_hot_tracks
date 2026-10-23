<?php

declare(strict_types=1);

namespace OCA\HotTracks\BackgroundJob;

use OCA\HotTracks\Db\ActivityMapper;
use OCA\HotTracks\Service\ActivityFiles;
use OCA\HotTracks\Service\GpxCopyFolder;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

class CopyGpxJob extends QueuedJob {
	private const BATCH = 40;

	public function __construct(
		ITimeFactory $time,
		private ActivityMapper $activities,
		private ActivityFiles $files,
		private GpxCopyFolder $copies,
		private IJobList $jobList,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	protected function run(mixed $argument): void {
		$uid = is_array($argument) ? (string)($argument['uid'] ?? '') : '';
		if ($uid === '' || $this->copies->path($uid) === '') {
			return;
		}
		if ($this->copyBatch($uid)) {
			$this->jobList->add(self::class, ['uid' => $uid]);
		}
	}

	private function copyBatch(string $uid): bool {
		$copied = 0;
		foreach ($this->activities->findByUid($uid, 10000) as $activity) {
			$fileId = $activity->getFileId();
			if ($fileId === null) {
				continue;
			}
			try {
				$status = $this->files->copyStored($uid, (int)$fileId);
			} catch (\Throwable $e) {
				$this->logger->warning('Could not copy an existing GPX file to the extra folder', [
					'app' => 'hot_tracks',
					'exception' => $e,
				]);
				continue;
			}
			if ($status !== 'copied') {
				continue;
			}
			$copied++;
			if ($copied >= self::BATCH) {
				return true;
			}
		}
		return false;
	}
}
