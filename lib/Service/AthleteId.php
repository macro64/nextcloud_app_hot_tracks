<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCP\IConfig;

class AthleteId {
	private const KEY = 'strava_athlete_id';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function get(string $uid): int {
		$value = $this->config->getUserValue($uid, Application::APP_ID, self::KEY, '0');
		if (!is_string($value) || !preg_match('/^[0-9]{1,15}$/', $value)) {
			return 0;
		}
		return (int)$value;
	}

	public function store(string $uid, int $athleteId): void {
		if ($athleteId <= 0) {
			$this->config->deleteUserValue($uid, Application::APP_ID, self::KEY);
			return;
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY, (string)$athleteId);
	}
}
