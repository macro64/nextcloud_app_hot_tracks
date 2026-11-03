<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;

class AppConfig {
	public const DEFAULT_AUTH_URL = 'https://auth.fluadrits.de';
	public const DEFAULT_CLIENT_ID = 'nextcloud';

	private SecretValue $secrets;

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		ICrypto $crypto,
	) {
		$this->secrets = new SecretValue($crypto);
	}

	public function authServiceUrl(): string {
		$url = trim($this->config->getAppValue(Application::APP_ID, 'auth_service_url', ''));
		if ($url === '') {
			// Display default in the admin UI; isConfigured() still requires a secret.
			$url = self::DEFAULT_AUTH_URL;
		}
		return rtrim($url, '/');
	}

	public function clientId(): string {
		$id = trim($this->config->getAppValue(Application::APP_ID, 'auth_client_id', ''));
		return $id !== '' ? $id : self::DEFAULT_CLIENT_ID;
	}

	public function clientSecret(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, 'auth_client_secret', '');
		if (!is_string($stored) || $stored === '') {
			return '';
		}
		$plain = $this->secrets->reveal($stored);
		if ($plain !== '' && $this->secrets->isLegacyPlaintext($stored)) {
			$this->persistSecret('auth_client_secret', $plain);
		}
		return $plain;
	}

	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.auth.callback');
	}

	/**
	 * Secret path segment of the webhook URL. Created on first read.
	 */
	public function webhookToken(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, 'webhook_token', '');
		if (is_string($stored) && $stored !== '') {
			$plain = $this->secrets->reveal($stored);
			if ($this->isWebhookToken($plain)) {
				if ($this->secrets->isLegacyPlaintext($stored)) {
					$this->persistSecret('webhook_token', $plain);
				}
				return $plain;
			}
		}

		$token = bin2hex(random_bytes(32));
		$this->persistSecret('webhook_token', $token);
		return $token;
	}

	public function webhookTokenMatches(string $token): bool {
		if (!$this->isWebhookToken($token)) {
			return false;
		}
		$stored = $this->config->getAppValue(Application::APP_ID, 'webhook_token', '');
		if (!is_string($stored) || $stored === '') {
			return false;
		}
		$plain = $this->secrets->reveal($stored);
		if (!$this->isWebhookToken($plain)) {
			return false;
		}
		return hash_equals($plain, $token);
	}

	public function webhookUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.webhook.receive', [
			'token' => $this->webhookToken(),
		]);
	}

	public function stravaClientId(): string {
		return trim($this->config->getAppValue(Application::APP_ID, 'strava_client_id', ''));
	}

	public function stravaClientSecret(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, 'strava_client_secret', '');
		if (!is_string($stored) || $stored === '') {
			return '';
		}
		$plain = $this->secrets->reveal($stored);
		if ($plain !== '' && $this->secrets->isLegacyPlaintext($stored)) {
			$this->persistSecret('strava_client_secret', $plain);
		}
		return $plain;
	}

	public function hasDirectStrava(): bool {
		return $this->stravaClientId() !== '' && $this->stravaClientSecret() !== '';
	}

	public function isConfigured(): bool {
		return $this->clientSecret() !== '' && $this->authServiceUrl() !== '';
	}

	public function saveStrava(string $clientId, ?string $clientSecret): void {
		$this->config->setAppValue(Application::APP_ID, 'strava_client_id', $clientId);
		if ($clientSecret !== null && $clientSecret !== '') {
			$this->persistSecret('strava_client_secret', trim($clientSecret));
		}
	}

	public function save(string $authServiceUrl, string $clientId, ?string $clientSecret): void {
		$this->config->setAppValue(Application::APP_ID, 'auth_service_url', rtrim($authServiceUrl, '/'));
		$this->config->setAppValue(Application::APP_ID, 'auth_client_id', $clientId);
		if ($clientSecret !== null && $clientSecret !== '') {
			$this->persistSecret('auth_client_secret', trim($clientSecret));
		}
	}

	private function persistSecret(string $key, string $plain): void {
		$this->config->setAppValue(Application::APP_ID, $key, $this->secrets->seal($plain));
	}

	private function isWebhookToken(string $token): bool {
		return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
	}
}
