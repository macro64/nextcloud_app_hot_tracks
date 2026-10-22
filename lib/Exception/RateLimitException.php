<?php

declare(strict_types=1);

namespace OCA\HotTracks\Exception;

class RateLimitException extends TrackingApiException {
	public function __construct() {
		parent::__construct('Limit der activity tracking app erreicht', 429);
	}
}
