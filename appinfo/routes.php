<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'auth#start', 'url' => '/oauth/start', 'verb' => 'GET'],
		['name' => 'auth#callback', 'url' => '/oauth/callback', 'verb' => 'GET'],
		['name' => 'webhook#receive', 'url' => '/webhook/activity/{token}', 'verb' => 'POST', 'requirements' => ['token' => '[a-f0-9]{64}']],
		['name' => 'api#overview', 'url' => '/api/overview', 'verb' => 'GET'],
		['name' => 'api#sync', 'url' => '/api/sync', 'verb' => 'POST'],
		['name' => 'api#disconnect', 'url' => '/api/disconnect', 'verb' => 'POST'],
		['name' => 'personal#save', 'url' => '/settings/personal', 'verb' => 'POST'],
		['name' => 'personal#disconnect', 'url' => '/settings/disconnect', 'verb' => 'POST'],
		['name' => 'personal#saveCopyFolder', 'url' => '/settings/copy-folder', 'verb' => 'POST'],
		['name' => 'personal#saveSports', 'url' => '/settings/sports', 'verb' => 'POST'],
		['name' => 'api#activity', 'url' => '/api/activities/{activityId}', 'verb' => 'GET', 'requirements' => ['activityId' => '\d+']],
		['name' => 'api#gpx', 'url' => '/api/activities/{activityId}/gpx', 'verb' => 'GET', 'requirements' => ['activityId' => '\d+']],
		['name' => 'api#weekZip', 'url' => '/api/week/zip', 'verb' => 'GET'],
		['name' => 'api#weekTracks', 'url' => '/api/week/tracks', 'verb' => 'GET'],
		['name' => 'admin#save', 'url' => '/admin', 'verb' => 'POST'],
	],
];
