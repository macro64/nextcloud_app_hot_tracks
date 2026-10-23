<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Security\ICrypto;

/**
 * Per user: direct Strava OAuth (default) or an optional auth proxy.
 */
class UserAuthConfig {
	public const MODE_DIRECT = 'direct';
	public const MODE_PROXY = 'proxy';

	private const KEY_MODE = 'auth_mode';
	private const KEY_CLIENT_ID = 'auth_client_id';
	private const KEY_CLIENT_SECRET = 'auth_client_secret';
	private const KEY_PROXY_URL = 'auth_proxy_url';

	public function __construct(
		private IConfig $config,
		private ICrypto $crypto,
		private IURLGenerator $urlGenerator,
		private AppConfig $appConfig,
	) {
	}

	public function mode(string $uid): string {
		$mode = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_MODE, '');
		if ($mode === self::MODE_DIRECT || $mode === self::MODE_PROXY) {
			return $mode;
		}
		// Without a personal setting, fall back to the admin proxy (existing installs).
		return $this->appConfig->isConfigured() ? self::MODE_PROXY : self::MODE_DIRECT;
	}

	public function usesProxy(string $uid): bool {
		return $this->mode($uid) === self::MODE_PROXY;
	}

	public function clientId(string $uid): string {
		$id = trim($this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_ID, ''));
		if ($id !== '') {
			return $id;
		}
		if ($this->usesProxy($uid) && $this->appConfig->isConfigured()) {
			return $this->appConfig->clientId();
		}
		return '';
	}

	public function clientSecret(string $uid): string {
		$stored = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_SECRET, '');
		if (is_string($stored) && $stored !== '') {
			try {
				return $this->crypto->decrypt($stored);
			} catch (\Throwable) {
				return '';
			}
		}
		if ($this->usesProxy($uid) && $this->appConfig->isConfigured()) {
			return $this->appConfig->clientSecret();
		}
		return '';
	}

	public function proxyUrl(string $uid): string {
		$url = trim($this->config->getUserValue($uid, Application::APP_ID, self::KEY_PROXY_URL, ''));
		if ($url !== '') {
			return rtrim($url, '/');
		}
		if ($this->appConfig->isConfigured()) {
			return $this->appConfig->authServiceUrl();
		}
		return '';
	}

	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('hot_tracks.auth.callback');
	}

	/**
	 * Hostname without a scheme, for Strava's Authorization Callback Domain.
	 */
	public function callbackDomain(): string {
		$host = parse_url($this->redirectUri(), PHP_URL_HOST);
		return is_string($host) ? $host : '';
	}

	public function hasPersonalConfig(string $uid): bool {
		$mode = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_MODE, '');
		$id = trim($this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_ID, ''));
		$secret = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_SECRET, '');
		return ($mode === self::MODE_DIRECT || $mode === self::MODE_PROXY)
			&& $id !== ''
			&& is_string($secret)
			&& $secret !== '';
	}

	public function isConfigured(string $uid): bool {
		if ($this->hasPersonalConfig($uid)) {
			if ($this->mode($uid) === self::MODE_PROXY) {
				return $this->proxyUrl($uid) !== '' && $this->clientSecret($uid) !== '';
			}
			return $this->clientId($uid) !== '' && $this->clientSecret($uid) !== '';
		}
		return $this->appConfig->isConfigured();
	}

	public function secretIsSet(string $uid): bool {
		$stored = $this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_SECRET, '');
		if (is_string($stored) && $stored !== '') {
			return true;
		}
		return $this->usesProxy($uid) && $this->appConfig->clientSecret() !== '';
	}

	/**
	 * @return array{mode: string, clientId: string, proxyUrl: string, secretSet: bool, hasPersonal: bool}
	 */
	public function formState(string $uid): array {
		$hasPersonal = $this->hasPersonalConfig($uid);
		$mode = $hasPersonal
			? $this->mode($uid)
			: ($this->appConfig->isConfigured() ? self::MODE_PROXY : self::MODE_DIRECT);

		return [
			'mode' => $mode,
			'clientId' => $hasPersonal
				? trim($this->config->getUserValue($uid, Application::APP_ID, self::KEY_CLIENT_ID, ''))
				: ($mode === self::MODE_PROXY ? $this->appConfig->clientId() : ''),
			'proxyUrl' => $hasPersonal
				? rtrim(trim($this->config->getUserValue($uid, Application::APP_ID, self::KEY_PROXY_URL, '')), '/')
				: ($this->appConfig->isConfigured() ? $this->appConfig->authServiceUrl() : ''),
			'secretSet' => $this->secretIsSet($uid),
			'hasPersonal' => $hasPersonal,
		];
	}

	public function save(
		string $uid,
		string $mode,
		string $clientId,
		?string $clientSecret,
		string $proxyUrl,
	): void {
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_MODE, $mode);
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY_CLIENT_ID, $clientId);
		$this->config->setUserValue(
			$uid,
			Application::APP_ID,
			self::KEY_PROXY_URL,
			$mode === self::MODE_PROXY ? rtrim($proxyUrl, '/') : '',
		);
		if ($clientSecret !== null && $clientSecret !== '') {
			$this->config->setUserValue(
				$uid,
				Application::APP_ID,
				self::KEY_CLIENT_SECRET,
				$this->crypto->encrypt($clientSecret),
			);
		}
	}
}
