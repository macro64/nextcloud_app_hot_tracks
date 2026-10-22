<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

class WeekStats {
	/**
	 * @return array{0: int, 1: int}
	 */
	public static function window(\DateTimeZone $timezone, ?\DateTimeImmutable $now = null): array {
		$now = ($now ?? new \DateTimeImmutable('now'))->setTimezone($timezone);
		$start = $now->setTime(0, 0, 0)->modify('-6 days');
		$end = $now->setTime(23, 59, 59);
		return [$start->getTimestamp(), $end->getTimestamp()];
	}

	/**
	 * @param list<array{sport: string, distance: float, movingTime: int, elevation: float}> $rows
	 * @return array{count: int, distance: float, movingTime: int, elevation: float, bySport: list<array{sport: string, count: int, distance: float, movingTime: int, elevation: float}>}
	 */
	public static function summarize(array $rows): array {
		$distance = 0.0;
		$moving = 0;
		$elevation = 0.0;
		/** @var array<string, array{sport: string, count: int, distance: float, movingTime: int, elevation: float}> $bySport */
		$bySport = [];

		foreach ($rows as $row) {
			$distance += $row['distance'];
			$moving += $row['movingTime'];
			$elevation += $row['elevation'];
			$sport = $row['sport'];
			if (!isset($bySport[$sport])) {
				$bySport[$sport] = [
					'sport' => $sport,
					'count' => 0,
					'distance' => 0.0,
					'movingTime' => 0,
					'elevation' => 0.0,
				];
			}
			$bySport[$sport]['count']++;
			$bySport[$sport]['distance'] += $row['distance'];
			$bySport[$sport]['movingTime'] += $row['movingTime'];
			$bySport[$sport]['elevation'] += $row['elevation'];
		}

		$groups = array_values($bySport);
		usort($groups, static function (array $a, array $b): int {
			return $b['distance'] <=> $a['distance'];
		});

		return [
			'count' => count($rows),
			'distance' => $distance,
			'movingTime' => $moving,
			'elevation' => $elevation,
			'bySport' => $groups,
		];
	}
}
