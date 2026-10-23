<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

class ActivityPath {
	public static function slug(string $name): string {
		$value = trim($name);
		if (function_exists('transliterator_transliterate')) {
			$ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
			$value = is_string($ascii) ? $ascii : $value;
		} else {
			$value = strtolower($value);
		}
		$value = strtolower($value);
		$slug = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
		$slug = trim($slug, '-');
		if ($slug === '') {
			$slug = 'activity';
		}
		return substr($slug, 0, 40);
	}

	public static function filename(\DateTimeImmutable $startLocal, int $activityId, string $name): string {
		return sprintf('%s_%d_%s.gpx', $startLocal->format('Y-m-d'), $activityId, self::slug($name));
	}
}
