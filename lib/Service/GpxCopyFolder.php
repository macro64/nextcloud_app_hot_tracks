<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCP\IConfig;

class GpxCopyFolder {
	private const KEY = 'gpx_copy_folder';

	public function __construct(
		private IConfig $config,
	) {
	}

	public function path(string $uid): string {
		$value = $this->config->getUserValue($uid, Application::APP_ID, self::KEY, '');
		return is_string($value) ? $value : '';
	}

	public function store(string $uid, string $path): void {
		if ($path === '') {
			$this->config->deleteUserValue($uid, Application::APP_ID, self::KEY);
			return;
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY, $path);
	}
}
