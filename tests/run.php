<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
	$prefix = 'OCA\\Strava\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$path = dirname(__DIR__) . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	if (is_file($path)) {
		require $path;
	}
});

use OCA\Strava\Service\ActivityPath;
use OCA\Strava\Service\GpxBuilder;
use OCA\Strava\Service\WeekStats;

$failed = 0;

function check(bool $condition, string $message): void {
	global $failed;
	if ($condition) {
		echo "ok  $message\n";
		return;
	}
	$failed++;
	echo "FAIL $message\n";
}

$slug = ActivityPath::slug('Morning Run!');
check($slug === 'morning-run', 'slug aus englischem Namen');
check(ActivityPath::slug('') === 'aktivitaet', 'leerer Name');
check(ActivityPath::slug('A & B') === 'a-b', 'sonderzeichen werden zu bindestrichen');

$tz = new DateTimeZone('Europe/Berlin');
$now = new DateTimeImmutable('2026-10-22 15:04:00', $tz);
[$from, $to] = WeekStats::window($tz, $now);
$start = (new DateTimeImmutable('@' . $from))->setTimezone($tz);
$end = (new DateTimeImmutable('@' . $to))->setTimezone($tz);
check($start->format('Y-m-d H:i:s') === '2026-10-16 00:00:00', 'fenster beginnt sechs tage vorher');
check($end->format('Y-m-d H:i:s') === '2026-10-22 23:59:59', 'fenster endet heute');

$stats = WeekStats::summarize([
	['sport' => 'Run', 'distance' => 10000.0, 'movingTime' => 3600, 'elevation' => 100.0],
	['sport' => 'Ride', 'distance' => 40000.0, 'movingTime' => 5400, 'elevation' => 300.0],
	['sport' => 'Run', 'distance' => 5000.0, 'movingTime' => 1800, 'elevation' => 20.0],
]);
check($stats['count'] === 3, 'anzahl');
check($stats['distance'] === 55000.0, 'distanzsumme');
check($stats['movingTime'] === 10800, 'bewegungszeit');
check($stats['elevation'] === 420.0, 'hoehe');
check($stats['bySport'][0]['sport'] === 'Ride', 'sportart mit mehr distanz zuerst');
check($stats['bySport'][1]['count'] === 2, 'laufen zaehlt zwei eintraege');

$gpx = (new GpxBuilder())->build(
	'A & B <run>',
	'Run',
	1761110400,
	[[52.5, 13.4], [52.51, 13.41]],
	[40, 55],
	[0, 30],
);
check(str_contains($gpx, 'lat="52.5000000"'), 'latitude');
check(str_contains($gpx, 'lon="13.4100000"'), 'longitude');
check(str_contains($gpx, '<ele>55.0</ele>'), 'hoehe im punkt');
check(str_contains($gpx, 'A &amp; B &lt;run&gt;'), 'name wird escaped');
check(str_contains($gpx, '2026-10-22T06:40:30Z') || str_contains($gpx, gmdate('Y-m-d\TH:i:s\Z', 1761110400 + 30)), 'zeitoffset');
check((new GpxBuilder())->build('x', 'Run', 0, [], [], []) === '', 'ohne punkte kein gpx');

$name = ActivityPath::filename(new DateTimeImmutable('2026-10-22 08:00:00', $tz), 99, 'Morning Run');
check($name === '2026-10-22_99_morning-run.gpx', 'dateiname');

exit($failed === 0 ? 0 : 1);
