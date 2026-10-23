<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
	$prefix = 'OCA\\HotTracks\\';
	if (!str_starts_with($class, $prefix)) {
		return;
	}
	$path = dirname(__DIR__) . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
	if (is_file($path)) {
		require $path;
	}
});

use OCA\HotTracks\Service\ActivityPath;
use OCA\HotTracks\Service\GpxBuilder;
use OCA\HotTracks\Service\GpxCopyPath;
use OCA\HotTracks\Service\SportGroup;
use OCA\HotTracks\Service\SportPreferences;
use OCA\HotTracks\Service\SyncRange;
use OCA\HotTracks\Service\TrackPoints;
use OCA\HotTracks\Service\WebhookEvent;
use OCA\HotTracks\Service\WeekStats;

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

$dense = [];
for ($i = 0; $i < 40; $i++) {
	$dense[] = [52.5, 13.4 + ($i * 0.00008)];
}
$sampled = TrackPoints::fromGpx((new GpxBuilder())->build('dicht', 'Ride', 1761110400, $dense, [], []));
check(count($sampled) >= 2 && count($sampled) < count($dense), 'dichte spur wird ausgeduennt');
check($sampled[0] === [52.5, 13.4], 'erster punkt bleibt');
check(abs($sampled[count($sampled) - 1][1] - 13.40312) < 0.0002, 'letzter punkt bleibt');
check(TrackPoints::fromGpx('') === [], 'leeres gpx');
check(TrackPoints::fromGpx('<gpx></gpx>') === [], 'gpx ohne punkte');

$fixedNow = 1_700_000_000;
check(SyncRange::isValid('month'), 'zeitraum monat gueltig');
check(SyncRange::isValid('year'), 'zeitraum jahr gueltig');
check(SyncRange::isValid('5y'), 'zeitraum 5 jahre gueltig');
check(SyncRange::isValid('all'), 'zeitraum alles gueltig');
check(!SyncRange::isValid('week'), 'zeitraum week ungueltig');
check(SyncRange::after('month', $fixedNow) === $fixedNow - 30 * 86400, 'after monat');
check(SyncRange::after('year', $fixedNow) === $fixedNow - 365 * 86400, 'after jahr');
check(SyncRange::after('5y', $fixedNow) === $fixedNow - 5 * 365 * 86400, 'after 5 jahre');
check(SyncRange::after('all', $fixedNow) === 0, 'after alles');
check(SyncRange::maxPages('month') > SyncRange::maxPages(null), 'monat mehr seiten als inkrementell');
check(SyncRange::maxPages('all') >= SyncRange::maxPages('year'), 'alles mindestens so viele seiten wie jahr');
check(SyncRange::label('5y') === '5 Jahre', 'label 5 jahre');

$created = WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'create',
	'object_id' => 99,
	'owner_id' => 7,
	'updates' => [],
]);
check($created !== null && $created->aspect === 'create' && $created->objectId === 99 && !$created->deauthorized, 'webhook neue aktivitaet');
$deleted = WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'delete',
	'object_id' => '15',
	'owner_id' => '7',
]);
check($deleted !== null && $deleted->aspect === 'delete' && $deleted->objectId === 15, 'webhook loeschen');
$revoked = WebhookEvent::parse([
	'object_type' => 'athlete',
	'aspect_type' => 'update',
	'object_id' => 7,
	'owner_id' => 7,
	'updates' => ['authorized' => 'false'],
]);
check($revoked !== null && $revoked->deauthorized, 'webhook widerruf');
check(WebhookEvent::parse([
	'object_type' => 'athlete',
	'aspect_type' => 'update',
	'object_id' => 7,
	'owner_id' => 7,
	'updates' => ['authorized' => 'true'],
]) === null, 'andere athleten-updates ignorieren');
check(WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'create',
	'object_id' => 1,
	'owner_id' => 0,
]) === null, 'webhook ohne besitzer ignorieren');
check(WebhookEvent::parse(['object_type' => 'activity']) === null, 'unvollstaendiges webhook ignorieren');

check(SportGroup::matches('run', 'Run'), 'laufen ist run');
check(SportGroup::matches('run', 'TrailRun'), 'trailrun ist run');
check(!SportGroup::matches('run', 'Ride'), 'rad ist nicht run');
check(SportGroup::matches('ride', 'GravelRide'), 'gravel ist ride');
check(SportGroup::matches('ride', 'EBikeRide'), 'ebike ist ride');
check(!SportGroup::matches('ride', 'Run'), 'laufen ist nicht ride');
check(SportGroup::matches('ski', 'BackcountrySki'), 'skitour ist ski');
check(SportGroup::matches('ski', 'NordicSki'), 'langlauf ist ski');
check(SportGroup::matches('water', 'Swim'), 'schwimmen ist water');
check(SportGroup::matches('water', 'Kayaking'), 'kajak ist water');
check(SportGroup::isValid('run') && SportGroup::isValid('ski') && SportGroup::isValid('water'), 'sportfilter gueltig');
check(!SportGroup::isValid('yoga'), 'yoga kein heatmap-filter');
check(SportGroup::matchesAny(['run', 'ski'], 'BackcountrySki'), 'multiselect trifft ski');
check(SportGroup::matchesAny(['run', 'ski'], 'Run'), 'multiselect trifft run');
check(!SportGroup::matchesAny(['run', 'ski'], 'Ride'), 'multiselect ohne rad');
check(SportGroup::label('water') === 'Wasser', 'sportgruppen-label');

check(SportPreferences::narrow(['run', 'ride'], 'all') === ['run', 'ride'], 'filter all = settings');
check(SportPreferences::narrow(['run', 'ride'], 'run') === ['run'], 'filter run = eine gruppe');
check(SportPreferences::narrow(['run', 'ride'], 'ski') === ['run', 'ride'], 'filter ausserhalb settings fällt zurück');

$copy = GpxCopyPath::normalize('  /Sport//GPX/  ');
check($copy['error'] === '' && $copy['path'] === 'Sport/GPX', 'kopie-ordner wird normalisiert');
$off = GpxCopyPath::normalize('   ');
check($off['error'] === '' && $off['path'] === '', 'leerer kopie-ordner ist aus');
$escape = GpxCopyPath::normalize('../Geheim');
check($escape['error'] !== '' && $escape['path'] === '', 'kopie-ordner darf nicht ausbrechen');
$archive = GpxCopyPath::normalize('Hot Tracks/Export');
check($archive['error'] !== '' && $archive['path'] === '', 'hot tracks bleibt der eigene ablageort');
$slash = GpxCopyPath::normalize('Sport\\GPX');
check($slash['error'] === '' && $slash['path'] === 'Sport/GPX', 'backslash wird zum ordnertrenner');

exit($failed === 0 ? 0 : 1);
