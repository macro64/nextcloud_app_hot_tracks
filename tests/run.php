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
use OCA\HotTracks\Service\AuthServiceUrl;
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
check($slug === 'morning-run', 'slug from an english name');
check(ActivityPath::slug('') === 'activity', 'empty name');
check(ActivityPath::slug('A & B') === 'a-b', 'punctuation becomes hyphens');

$tz = new DateTimeZone('Europe/Berlin');
$now = new DateTimeImmutable('2026-10-22 15:04:00', $tz);
[$from, $to] = WeekStats::window($tz, $now);
$start = (new DateTimeImmutable('@' . $from))->setTimezone($tz);
$end = (new DateTimeImmutable('@' . $to))->setTimezone($tz);
check($start->format('Y-m-d H:i:s') === '2026-10-16 00:00:00', 'window starts six days earlier');
check($end->format('Y-m-d H:i:s') === '2026-10-22 23:59:59', 'window ends today');

$stats = WeekStats::summarize([
	['sport' => 'Run', 'distance' => 10000.0, 'movingTime' => 3600, 'elevation' => 100.0],
	['sport' => 'Ride', 'distance' => 40000.0, 'movingTime' => 5400, 'elevation' => 300.0],
	['sport' => 'Run', 'distance' => 5000.0, 'movingTime' => 1800, 'elevation' => 20.0],
]);
check($stats['count'] === 3, 'count');
check($stats['distance'] === 55000.0, 'distance sum');
check($stats['movingTime'] === 10800, 'moving time');
check($stats['elevation'] === 420.0, 'elevation');
check($stats['bySport'][0]['sport'] === 'Ride', 'sport with more distance comes first');
check($stats['bySport'][1]['count'] === 2, 'run counts two entries');

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
check(str_contains($gpx, '<ele>55.0</ele>'), 'elevation in the point');
check(str_contains($gpx, 'A &amp; B &lt;run&gt;'), 'name is escaped');
check(str_contains($gpx, '2026-10-22T06:40:30Z') || str_contains($gpx, gmdate('Y-m-d\TH:i:s\Z', 1761110400 + 30)), 'time offset');
check((new GpxBuilder())->build('x', 'Run', 0, [], [], []) === '', 'no gpx without points');

$name = ActivityPath::filename(new DateTimeImmutable('2026-10-22 08:00:00', $tz), 99, 'Morning Run');
check($name === '2026-10-22_99_morning-run.gpx', 'filename');

$dense = [];
for ($i = 0; $i < 40; $i++) {
	$dense[] = [52.5, 13.4 + ($i * 0.00008)];
}
$sampled = TrackPoints::fromGpx((new GpxBuilder())->build('dicht', 'Ride', 1761110400, $dense, [], []));
check(count($sampled) >= 2 && count($sampled) < count($dense), 'dense track is thinned');
check($sampled[0] === [52.5, 13.4], 'first point stays');
check(abs($sampled[count($sampled) - 1][1] - 13.40312) < 0.0002, 'last point stays');
check(TrackPoints::fromGpx('') === [], 'empty gpx');
check(TrackPoints::fromGpx('<gpx></gpx>') === [], 'gpx without points');

$fixedNow = 1_700_000_000;
check(SyncRange::isValid('month'), 'month range is valid');
check(SyncRange::isValid('year'), 'year range is valid');
check(SyncRange::isValid('5y'), 'five year range is valid');
check(SyncRange::isValid('all'), 'all range is valid');
check(!SyncRange::isValid('week'), 'week range is invalid');
check(SyncRange::after('month', $fixedNow) === $fixedNow - 30 * 86400, 'after month');
check(SyncRange::after('year', $fixedNow) === $fixedNow - 365 * 86400, 'after year');
check(SyncRange::after('5y', $fixedNow) === $fixedNow - 5 * 365 * 86400, 'after five years');
check(SyncRange::after('all', $fixedNow) === 0, 'after all');
check(SyncRange::maxPages('month') > SyncRange::maxPages(null), 'month allows more pages than incremental');
check(SyncRange::maxPages('all') >= SyncRange::maxPages('year'), 'all allows at least as many pages as year');
check(SyncRange::label('5y') === '5 years', 'five year label');

$created = WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'create',
	'object_id' => 99,
	'owner_id' => 7,
	'updates' => [],
]);
check($created !== null && $created->aspect === 'create' && $created->objectId === 99 && !$created->deauthorized, 'webhook create activity');
$deleted = WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'delete',
	'object_id' => '15',
	'owner_id' => '7',
]);
check($deleted !== null && $deleted->aspect === 'delete' && $deleted->objectId === 15, 'webhook delete');
$revoked = WebhookEvent::parse([
	'object_type' => 'athlete',
	'aspect_type' => 'update',
	'object_id' => 7,
	'owner_id' => 7,
	'updates' => ['authorized' => 'false'],
]);
check($revoked !== null && $revoked->deauthorized, 'webhook deauthorize');
check(WebhookEvent::parse([
	'object_type' => 'athlete',
	'aspect_type' => 'update',
	'object_id' => 7,
	'owner_id' => 7,
	'updates' => ['authorized' => 'true'],
]) === null, 'ignore other athlete updates');
check(WebhookEvent::parse([
	'object_type' => 'activity',
	'aspect_type' => 'create',
	'object_id' => 1,
	'owner_id' => 0,
]) === null, 'ignore webhook without owner');
check(WebhookEvent::parse(['object_type' => 'activity']) === null, 'ignore incomplete webhook');

check(SportGroup::matches('run', 'Run'), 'run matches run');
check(SportGroup::matches('run', 'TrailRun'), 'trail run matches run');
check(!SportGroup::matches('run', 'Ride'), 'ride does not match run');
check(SportGroup::matches('ride', 'GravelRide'), 'gravel matches ride');
check(SportGroup::matches('ride', 'EBikeRide'), 'ebike matches ride');
check(!SportGroup::matches('ride', 'Run'), 'run does not match ride');
check(SportGroup::matches('ski', 'BackcountrySki'), 'backcountry ski matches ski');
check(SportGroup::matches('ski', 'NordicSki'), 'nordic ski matches ski');
check(SportGroup::matches('water', 'Swim'), 'swim matches water');
check(SportGroup::matches('water', 'Kayaking'), 'kayak matches water');
check(SportGroup::isValid('run') && SportGroup::isValid('ski') && SportGroup::isValid('water'), 'sport filter ids are valid');
check(!SportGroup::isValid('yoga'), 'yoga is not a heatmap filter');
check(SportGroup::matchesAny(['run', 'ski'], 'BackcountrySki'), 'multiselect matches ski');
check(SportGroup::matchesAny(['run', 'ski'], 'Run'), 'multiselect matches run');
check(!SportGroup::matchesAny(['run', 'ski'], 'Ride'), 'multiselect excludes ride');
check(SportGroup::label('water') === 'Water', 'sport group label');

check(SportPreferences::narrow(['run', 'ride'], 'all') === ['run', 'ride'], 'filter all = settings');
check(SportPreferences::narrow(['run', 'ride'], 'run') === ['run'], 'filter run is one group');
check(SportPreferences::narrow(['run', 'ride'], 'ski') === ['run', 'ride'], 'filter outside settings falls back');

$copy = GpxCopyPath::normalize('  /Sport//GPX/  ');
check($copy['error'] === '' && $copy['path'] === 'Sport/GPX', 'copy folder is normalized');
$off = GpxCopyPath::normalize('   ');
check($off['error'] === '' && $off['path'] === '', 'empty copy folder is off');
$escape = GpxCopyPath::normalize('../Geheim');
check($escape['error'] !== '' && $escape['path'] === '', 'copy folder must not escape');
$archive = GpxCopyPath::normalize('Hot Tracks/Export');
check($archive['error'] !== '' && $archive['path'] === '', 'hot tracks stays the own storage folder');
$slash = GpxCopyPath::normalize('Sport\\GPX');
check($slash['error'] === '' && $slash['path'] === 'Sport/GPX', 'backslash becomes a folder separator');

check(AuthServiceUrl::validate('https://auth.example.com') === '', 'https auth-url ok');
check(AuthServiceUrl::validate('http://127.0.0.1:8080/') === '', 'local http is allowed');
check(AuthServiceUrl::validate('http://auth.example.com') !== '', 'http without localhost is rejected');
check(AuthServiceUrl::validate('https://192.168.1.10/oauth') !== '', 'private ip literal is rejected');
check(AuthServiceUrl::validate('https://10.0.0.5') !== '', 'rfc1918 ip is rejected');
check(AuthServiceUrl::validate('https://user:pass@auth.example.com') !== '', 'url credentials are rejected');
check(AuthServiceUrl::validate('not-a-url') !== '', 'invalid auth url is rejected');

exit($failed === 0 ? 0 : 1);
