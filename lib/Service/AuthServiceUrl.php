<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

/**
 * Prüft Auth-Proxy-URLs: HTTPS (oder lokales HTTP) und keine privaten IP-Literale.
 */
final class AuthServiceUrl {
	/**
	 * @return string Fehlermeldung oder leer bei Erfolg
	 */
	public static function validate(string $url): string {
		$url = trim($url);
		if ($url === '') {
			return 'Die Auth-URL fehlt.';
		}

		$parts = parse_url($url);
		if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
			return 'Die Auth-URL ist ungültig.';
		}

		$scheme = strtolower((string)$parts['scheme']);
		$host = strtolower((string)$parts['host']);
		$isLocalHttp = $scheme === 'http'
			&& ($host === 'localhost' || $host === '127.0.0.1');

		if ($scheme !== 'https' && !$isLocalHttp) {
			return 'Die Auth-URL muss HTTPS sein.';
		}

		if (isset($parts['user']) || isset($parts['pass'])) {
			return 'Die Auth-URL darf keine Zugangsdaten enthalten.';
		}

		if (self::isBlockedHost($host)) {
			return 'Die Auth-URL darf nicht auf private oder lokale Netze zeigen.';
		}

		return '';
	}

	private static function isBlockedHost(string $host): bool {
		if ($host === 'localhost' || $host === '127.0.0.1') {
			return false;
		}

		if (filter_var($host, FILTER_VALIDATE_IP) === false) {
			// Hostnamen (inkl. DNS) nicht auflösen — nur IP-Literale sperren.
			return false;
		}

		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		return filter_var($host, FILTER_VALIDATE_IP, $flags) === false;
	}
}
