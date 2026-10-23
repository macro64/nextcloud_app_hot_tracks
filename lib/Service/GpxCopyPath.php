<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

/**
 * Relative path in a person's files. Empty means no extra copy.
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
			return ['path' => '', 'error' => 'The path is nested too deeply.'];
		}

		foreach ($parts as $part) {
			if ($part === '.' || $part === '..') {
				return ['path' => '', 'error' => 'The folder must stay inside your own files.'];
			}
			if (preg_match('/[\x00-\x1f:*?"<>|]/', $part) === 1) {
				return ['path' => '', 'error' => 'The folder contains a character that is not allowed in file names.'];
			}
			if (strlen($part) > 250) {
				return ['path' => '', 'error' => 'A folder name is too long.'];
			}
		}

		if (mb_strtolower($parts[0]) === 'hot tracks') {
			return ['path' => '', 'error' => 'Hot Tracks is already the storage folder. Choose a different folder.'];
		}

		return ['path' => implode('/', $parts), 'error' => ''];
	}
}
