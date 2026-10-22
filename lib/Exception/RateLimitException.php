<?php

declare(strict_types=1);

namespace OCA\Strava\Exception;

class RateLimitException extends StravaApiException {
	public function __construct() {
		parent::__construct('Strava-Limit erreicht', 429);
	}
}
