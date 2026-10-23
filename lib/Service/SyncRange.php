<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

final class SyncRange {
	public const MONTH = 'month';
	public const YEAR = 'year';
	public const FIVE_YEARS = '5y';
	public const ALL = 'all';

	/** @var list<string> */
	public const ALL_IDS = [self::MONTH, self::YEAR, self::FIVE_YEARS, self::ALL];

	public static function isValid(string $range): bool {
		return in_array($range, self::ALL_IDS, true);
	}

	/**
	 * Epoch seconds: fetch activities after this moment.
	 * For "all" the value is 0.
	 */
	public static function after(string $range, ?int $now = null): int {
		$now ??= time();
		return match ($range) {
			self::MONTH => $now - 30 * 86400,
			self::YEAR => $now - 365 * 86400,
			self::FIVE_YEARS => $now - 5 * 365 * 86400,
			self::ALL => 0,
			default => throw new \InvalidArgumentException('Unknown time range'),
		};
	}

	/**
	 * Maximum list pages (50 entries each) for the chosen range.
	 */
	public static function maxPages(?string $range): int {
		return match ($range) {
			self::MONTH => 20,
			self::YEAR => 80,
			self::FIVE_YEARS, self::ALL => 200,
			default => 10,
		};
	}

	public static function label(string $range): string {
		return match ($range) {
			self::MONTH => 'Last month',
			self::YEAR => 'Last year',
			self::FIVE_YEARS => '5 years',
			self::ALL => 'All',
			default => $range,
		};
	}
}
