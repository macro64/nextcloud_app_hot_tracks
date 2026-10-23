<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

/**
 * Relativer Pfad im Dateibereich einer Person. Leer heißt: keine zusätzliche Kopie.
 */
final class GpxCopyPath {
	/**
	 * @return array{path: string, error: string}
	 */
	public static function normalize(string $raw): array {
		$raw = trim(str_replace('\\', '/', $raw));
		if ($raw === '') {
			return ['path' => '', 'error' => ''];
		}

		$parts = [];
		foreach (explode('/', $raw) as $part) {
			if ($part === '') {
				continue;
			}
			$parts[] = $part;
		}
		if ($parts === []) {
			return ['path' => '', 'error' => ''];
		}
		if (count($parts) > 20) {
			return ['path' => '', 'error' => 'Der Pfad ist zu tief verschachtelt.'];
		}

		foreach ($parts as $part) {
			if ($part === '.' || $part === '..') {
				return ['path' => '', 'error' => 'Der Ordner darf nicht aus dem eigenen Dateibereich herausführen.'];
			}
			if (preg_match('/[\x00-\x1f:*?"<>|]/', $part) === 1) {
				return ['path' => '', 'error' => 'Der Ordner enthält ein Zeichen, das in Dateinamen nicht erlaubt ist.'];
			}
			if (strlen($part) > 250) {
				return ['path' => '', 'error' => 'Ein Ordnername ist zu lang.'];
			}
		}

		if (mb_strtolower($parts[0]) === 'hot tracks') {
			return ['path' => '', 'error' => 'Der Ordner Hot Tracks ist bereits der Ablageort. Wähle einen anderen Ordner.'];
		}

		return ['path' => implode('/', $parts), 'error' => ''];
	}
}
