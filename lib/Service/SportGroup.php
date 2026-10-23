<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

final class SportGroup {
	public const RUN = 'run';
	public const RIDE = 'ride';
	public const SKI = 'ski';
	public const WATER = 'water';

	/** @var list<string> */
	public const ALL_IDS = [self::RUN, self::RIDE, self::SKI, self::WATER];

	/** @var list<string> */
	private const RUN_SPORTS = [
		'Run',
		'TrailRun',
		'VirtualRun',
		'Walk',
		'Hike',
		'Snowshoe',
		'Elliptical',
		'StairStepper',
	];

	/** @var list<string> */
	private const RIDE_SPORTS = [
		'Ride',
		'MountainBikeRide',
		'GravelRide',
		'EBikeRide',
		'EMountainBikeRide',
		'VirtualRide',
		'Handcycle',
		'Velomobile',
		'Wheelchair',
	];

	/** @var list<string> */
	private const SKI_SPORTS = [
		'AlpineSki',
		'BackcountrySki',
		'NordicSki',
		'Snowboard',
		'RollerSki',
		'IceSkate',
	];

	/** @var list<string> */
	private const WATER_SPORTS = [
		'Swim',
		'Surfing',
		'Kayaking',
		'Canoeing',
		'Rowing',
		'VirtualRow',
		'StandUpPaddling',
		'Kitesurf',
		'Windsurf',
		'Sail',
	];

	public static function isValid(string $mode): bool {
		return in_array($mode, self::ALL_IDS, true);
	}

	/**
	 * English source label (pass through IL10N::t at the call site).
	 */
	public static function label(string $mode): string {
		return match ($mode) {
			self::RUN => 'Run',
			self::RIDE => 'Ride',
			self::SKI => 'Ski',
			self::WATER => 'Water',
			default => $mode,
		};
	}

	/**
	 * @param list<string> $modes
	 */
	public static function matchesAny(array $modes, string $sport): bool {
		if ($modes === []) {
			return true;
		}
		foreach ($modes as $mode) {
			if (self::matches($mode, $sport)) {
				return true;
			}
		}
		return false;
	}

	public static function matches(string $mode, string $sport): bool {
		return match ($mode) {
			self::RUN => in_array($sport, self::RUN_SPORTS, true),
			self::RIDE => in_array($sport, self::RIDE_SPORTS, true),
			self::SKI => in_array($sport, self::SKI_SPORTS, true),
			self::WATER => in_array($sport, self::WATER_SPORTS, true),
			default => true,
		};
	}
}
