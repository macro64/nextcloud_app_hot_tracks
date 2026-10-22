<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

final class SyncResult {
	public function __construct(
		public int $created,
		public int $skipped,
		public bool $rateLimited,
		public bool $busy = false,
	) {
	}
}
