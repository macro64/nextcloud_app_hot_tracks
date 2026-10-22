<?php

declare(strict_types=1);

namespace OCA\Strava\Service;

use OCA\Strava\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;

class AppConfig {
	public const DEFAULT_AUTH_URL = 'https://auth.fluadrits.de';
	public const DEFAULT_CLIENT_ID = 'nextcloud';

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function authServiceUrl(): string {
		$url = trim($this->config->getAppValue(Application::APP_ID, 'auth_service_url', self::DEFAULT_AUTH_URL));
		if ($url === '') {
			$url = self::DEFAULT_AUTH_URL;
		}
		return rtrim($url, '/');
	}

	public function clientId(): string {
		$id = trim($this->config->getAppValue(Application::APP_ID, 'auth_client_id', self::DEFAULT_CLIENT_ID));
		return $id !== '' ? $id : self::DEFAULT_CLIENT_ID;
	}

	public function clientSecret(): string {
		return $this->config->getAppValue(Application::APP_ID, 'auth_client_secret', '');
	}

	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('strava.auth.callback');
	}

	public function isConfigured(): bool {
		return $this->clientSecret() !== '' && $this->authServiceUrl() !== '';
	}

	public function save(string $authServiceUrl, string $clientId, ?string $clientSecret): void {
		$this->config->setAppValue(Application::APP_ID, 'auth_service_url', rtrim($authServiceUrl, '/'));
		$this->config->setAppValue(Application::APP_ID, 'auth_client_id', $clientId);
		if ($clientSecret !== null && $clientSecret !== '') {
			$this->config->setAppValue(Application::APP_ID, 'auth_client_secret', $clientSecret);
		}
	}
}
