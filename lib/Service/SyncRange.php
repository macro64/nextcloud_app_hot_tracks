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
	 * Epoch-Sekunde: Aktivitäten nach diesem Zeitpunkt abholen.
	 * Für „alles“ ist der Wert 0.
	 */
	public static function after(string $range, ?int $now = null): int {
		$now ??= time();
		return match ($range) {
			self::MONTH => $now - 30 * 86400,
			self::YEAR => $now - 365 * 86400,
			self::FIVE_YEARS => $now - 5 * 365 * 86400,
			self::ALL => 0,
			default => throw new \InvalidArgumentException('Unbekannter Zeitraum'),
		};
	}

	/**
	 * Maximale Listen-Seiten (je 50 Einträge) für den gewählten Zeitraum.
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
			self::MONTH => 'letzter Monat',
			self::YEAR => 'letztes Jahr',
			self::FIVE_YEARS => '5 Jahre',
			self::ALL => 'alles',
			default => $range,
		};
	}
}
