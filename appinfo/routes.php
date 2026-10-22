<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'auth#start', 'url' => '/oauth/start', 'verb' => 'GET'],
		['name' => 'auth#callback', 'url' => '/oauth/callback', 'verb' => 'GET'],
		['name' => 'api#overview', 'url' => '/api/overview', 'verb' => 'GET'],
		['name' => 'api#sync', 'url' => '/api/sync', 'verb' => 'POST'],
		['name' => 'api#disconnect', 'url' => '/api/disconnect', 'verb' => 'POST'],
		['name' => 'api#activity', 'url' => '/api/activities/{stravaId}', 'verb' => 'GET', 'requirements' => ['stravaId' => '\d+']],
		['name' => 'api#gpx', 'url' => '/api/activities/{stravaId}/gpx', 'verb' => 'GET', 'requirements' => ['stravaId' => '\d+']],
		['name' => 'api#weekZip', 'url' => '/api/week/zip', 'verb' => 'GET'],
		['name' => 'admin#save', 'url' => '/admin', 'verb' => 'POST'],
	],
];
