<?php

declare(strict_types=1);

namespace OCA\HotTracks\Exception;

class RateLimitException extends TrackingApiException {
	public function __construct() {
		parent::__construct('Activity tracking app rate limit reached', 429);
	}
}
