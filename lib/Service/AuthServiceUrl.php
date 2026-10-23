<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

/**
 * Validates auth-proxy URLs: HTTPS (or local HTTP) and no private IP literals.
 */
final class AuthServiceUrl {
	/**
	 * @return string English error message, or empty on success
	 */
	public static function validate(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return 'The auth URL is missing.';
		}

		$parts = parse_url($url);
		if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
			return 'The auth URL is invalid.';
		}

		$scheme = strtolower((string)$parts['scheme']);
		$host = strtolower((string)$parts['host']);
		$isLocalHttp = $scheme === 'http'
			&& ($host === 'localhost' || $host === '127.0.0.1');

		if ($scheme !== 'https' && !$isLocalHttp) {
			return 'The auth URL must use HTTPS.';
		}

		if (isset($parts['user']) || isset($parts['pass'])) {
			return 'The auth URL must not contain credentials.';
		}

		if (self::isBlockedHost($host)) {
			return 'The auth URL must not point to private or local networks.';
		}

		return '';
	}

	private static function isBlockedHost(string $host): bool {
		if ($host === 'localhost' || $host === '127.0.0.1') {
			return false;
		}

		if (filter_var($host, FILTER_VALIDATE_IP) === false) {
			// Do not resolve hostnames — only block IP literals.
			return false;
		}

		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		return filter_var($host, FILTER_VALIDATE_IP, $flags) === false;
	}
}
