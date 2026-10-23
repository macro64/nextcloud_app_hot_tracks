<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
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
		$url = trim($this->config->getAppValue(Application::APP_ID, 'auth_service_url', ''));
		if ($url === '') {
			// Anzeige-Default in der Admin-UI; isConfigured() verlangt trotzdem ein Secret.
			$url = self::DEFAULT_AUTH_URL;
		}
		return rtrim($url, '/');
	}

	public function clientId(): string {
		$id = trim($this->config->getAppValue(Application::APP_ID, 'auth_client_id', ''));
		return $id !== '' ? $id : self::DEFAULT_CLIENT_ID;
	}

	public function clientSecret(): string {
		return $this->config->getAppValue(Application::APP_ID, 'auth_client_secret', '');
	}

	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.auth.callback');
	}

	/**
	 * Geheimer Pfad-Anteil der Webhook-URL. Wird beim ersten Abruf erzeugt.
	 */
	public function webhookToken(): string {
		$token = $this->config->getAppValue(Application::APP_ID, 'webhook_token', '');
		if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
			$token = bin2hex(random_bytes(32));
			$this->config->setAppValue(Application::APP_ID, 'webhook_token', $token);
		}
		return $token;
	}

	public function webhookTokenMatches(string $token): bool {
		$stored = $this->config->getAppValue(Application::APP_ID, 'webhook_token', '');
		if (!is_string($stored) || $stored === '' || $token === '') {
			return false;
		}
		return hash_equals($stored, $token);
	}

	public function webhookUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.webhook.receive', [
			'token' => $this->webhookToken(),
		]);
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
