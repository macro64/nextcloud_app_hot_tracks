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

	public function __construct(
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private ICrypto $crypto,
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
		$stored = $this->config->getAppValue(Application::APP_ID, 'auth_client_secret', '');
		if (!is_string($stored) || $stored === '') {
			return '';
		}
		$plain = $this->revealSecret($stored);
		// Legacy: Klartext aus älteren Installationen beim Lesen nachziehen.
		if ($plain !== '' && $plain === $stored) {
			$this->persistSecret('auth_client_secret', $plain);
		}
		return $plain;
	}

	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.auth.callback');
	}

	/**
	 * Geheimer Pfad-Anteil der Webhook-URL. Wird beim ersten Abruf erzeugt.
	 */
	public function webhookToken(): string {
		$stored = $this->config->getAppValue(Application::APP_ID, 'webhook_token', '');
		if (is_string($stored) && $stored !== '') {
			$plain = $this->revealSecret($stored);
			if ($this->isWebhookToken($plain)) {
				if ($plain === $stored) {
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
		$plain = $this->revealSecret($stored);
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

	public function isConfigured(): bool {
		return $this->clientSecret() !== '' && $this->authServiceUrl() !== '';
	}

	public function save(string $authServiceUrl, string $clientId, ?string $clientSecret): void {
		$this->config->setAppValue(Application::APP_ID, 'auth_service_url', rtrim($authServiceUrl, '/'));
		$this->config->setAppValue(Application::APP_ID, 'auth_client_id', $clientId);
		if ($clientSecret !== null && $clientSecret !== '') {
			$this->persistSecret('auth_client_secret', $clientSecret);
		}
	}

	private function persistSecret(string $key, string $plain): void {
		$this->config->setAppValue(Application::APP_ID, $key, $this->crypto->encrypt($plain));
	}

	/**
	 * Entschlüsselt gespeicherte Secrets; Klartext aus älteren Versionen bleibt lesbar.
	 */
	private function revealSecret(string $stored): string {
		try {
			return $this->crypto->decrypt($stored);
		} catch (\Throwable) {
			return $stored;
		}
	}

	private function isWebhookToken(string $token): bool {
		return (bool)preg_match('/^[a-f0-9]{64}$/', $token);
	}
}
