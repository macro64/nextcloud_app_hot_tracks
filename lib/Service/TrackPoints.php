<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

class TrackPoints {
	/**
	 * @return list<array{0: float, 1: float}>
	 */
	public static function fromGpx(string $xml, float $minMeters = 60.0, int $maxPoints = 300): array {
		if (trim($xml) === '') {
			return [];
		}
		$previous = libxml_use_internal_errors(true);
		$doc = simplexml_load_string($xml);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if ($doc === false) {
			return [];
		}
		$nodes = $doc->xpath('//*[local-name()="trkpt"]');
		if (!is_array($nodes)) {
			return [];
		}
		$raw = [];
		foreach ($nodes as $node) {
			$lat = self::coordinate($node['lat'] ?? null, -90.0, 90.0);
			$lon = self::coordinate($node['lon'] ?? null, -180.0, 180.0);
			if ($lat === null || $lon === null) {
				continue;
			}
			$raw[] = [$lat, $lon];
		}
		return self::downsample($raw, $minMeters, $maxPoints);
	}

	/**
	 * @param list<array{0: float, 1: float}> $points
	 * @return list<array{0: float, 1: float}>
	 */
	public static function downsample(array $points, float $minMeters = 60.0, int $maxPoints = 300): array {
		if ($points === []) {
			return [];
		}
		$kept = [$points[0]];
		$last = $points[0];
		$end = count($points) - 1;
		for ($index = 1; $index < $end; $index++) {
			$point = $points[$index];
			if (self::meters($last[0], $last[1], $point[0], $point[1]) < $minMeters) {
				continue;
			}
			$kept[] = $point;
			$last = $point;
		}
		if ($end > 0) {
			$kept[] = $points[$end];
		}
		if ($maxPoints > 1 && count($kept) > $maxPoints) {
			$stride = (int)ceil(count($kept) / $maxPoints);
			$thinned = [];
			foreach ($kept as $index => $point) {
				if ($index % $stride === 0) {
					$thinned[] = $point;
				}
			}
			$thinned[] = $kept[count($kept) - 1];
			$kept = $thinned;
		}
		$rounded = [];
		foreach ($kept as $point) {
			$rounded[] = [round($point[0], 5), round($point[1], 5)];
		}
		return $rounded;
	}

	private static function coordinate(mixed $value, float $min, float $max): ?float {
		if ($value instanceof \SimpleXMLElement) {
			$value = (string)$value;
		}
		if (!is_numeric($value)) {
			return null;
		}
		$number = (float)$value;
		if ($number < $min || $number > $max) {
			return null;
		}
		return $number;
	}

	private static function meters(float $lat1, float $lon1, float $lat2, float $lon2): float {
		$earth = 6371000.0;
		$phi1 = deg2rad($lat1);
		$phi2 = deg2rad($lat2);
		$dPhi = deg2rad($lat2 - $lat1);
		$dLambda = deg2rad($lon2 - $lon1);
		$a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLambda / 2) ** 2;
		return 2 * $earth * asin(min(1.0, sqrt($a)));
	}
}
