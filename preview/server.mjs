import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const port = 4173

const overview = {
	configured: true,
	connected: true,
	athleteName: 'Ada Beispiel',
	timeZone: 'Europe/Berlin',
	storedCount: 42,
	stats: {
		count: 2,
		distance: 15200,
		movingTime: 5400,
		elevation: 180,
		bySport: [
			{ sport: 'Ride', count: 1, distance: 12000, movingTime: 2400, elevation: 90 },
			{ sport: 'Run', count: 1, distance: 3200, movingTime: 3000, elevation: 90 },
		],
	},
	activities: [
		{
			activityId: 42,
			name: 'Abendrunde',
			sport: 'Ride',
			startTime: Math.floor(Date.now() / 1000) - 86400,
			distance: 12000,
			movingTime: 2400,
			elapsedTime: 2600,
			elevation: 90,
			hasGpx: true,
		},
		{
			activityId: 43,
			name: 'Parklauf',
			sport: 'Run',
			startTime: Math.floor(Date.now() / 1000) - 3 * 86400,
			distance: 3200,
			movingTime: 3000,
			elapsedTime: 3100,
			elevation: 90,
			hasGpx: false,
		},
	],
}

const gpx = `<?xml version="1.0" encoding="UTF-8"?>
<gpx version="1.1" creator="preview">
  <trk><name>Abendrunde</name><trkseg>
    <trkpt lat="52.5200" lon="13.4050"><ele>34.0</ele></trkpt>
    <trkpt lat="52.5230" lon="13.4120"><ele>41.0</ele></trkpt>
    <trkpt lat="52.5180" lon="13.4200"><ele>36.0</ele></trkpt>
    <trkpt lat="52.5150" lon="13.4080"><ele>33.0</ele></trkpt>
  </trkseg></trk>
</gpx>`

const page = `<!doctype html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<title>Hot Tracks Vorschau</title>
	<link rel="stylesheet" href="/css/hot_tracks-main.css">
	<style>
		body { margin: 0; font-family: system-ui, sans-serif; background: #fff; color: #1d1d1d; }
		:root {
			--color-main-text: #1d1d1d;
			--color-text-maxcontrast: #6b6b6b;
			--color-primary-element: #1a5fb4;
			--color-primary-element-light: #e7f0fb;
			--color-primary-element-light-text: #123a73;
			--color-primary-element-light-hover: #d3e4f8;
			--color-border: #d8d8d8;
			--color-main-background: #fff;
			--color-background-hover: #f3f3f3;
			--default-clickable-area: 34px;
			--default-grid-baseline: 4px;
			--border-radius-element: 8px;
		}
	</style>
</head>
<body>
	<div id="hot-tracks-root"></div>
	<script>
		window._oc_webroot = ''
		window.OC = { config: { modRewriteWorking: true }, requestToken: 'preview' }
	</script>
	<script src="/js/hot_tracks-main.js"></script>
</body>
</html>`

function send(res, status, body, type) {
	res.writeHead(status, { 'Content-Type': type })
	res.end(body)
}

const server = createServer(async (req, res) => {
	const url = new URL(req.url ?? '/', 'http://127.0.0.1')
	if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/preview')) {
		return send(res, 200, page, 'text/html; charset=utf-8')
	}
	if (req.method === 'GET' && url.pathname === '/apps/hot_tracks/api/overview') {
		return send(res, 200, JSON.stringify(overview), 'application/json')
	}
	if (req.method === 'POST' && url.pathname === '/apps/hot_tracks/api/sync') {
		return send(res, 200, JSON.stringify({ created: 1, updated: 1, skipped: 2, rateLimited: false, range: 'month' }), 'application/json')
	}
	if (req.method === 'GET' && url.pathname === '/apps/hot_tracks/api/activities/42/gpx') {
		return send(res, 200, gpx, 'application/gpx+xml')
	}
	if (req.method === 'GET' && (url.pathname === '/js/hot_tracks-main.js' || url.pathname === '/css/hot_tracks-main.css')) {
		const file = path.join(root, url.pathname)
		const body = await readFile(file)
		const type = url.pathname.endsWith('.css') ? 'text/css' : 'text/javascript'
		return send(res, 200, body, type)
	}
	send(res, 404, 'not found', 'text/plain')
})

server.listen(port, '127.0.0.1', () => {
	console.log(`preview http://127.0.0.1:${port}/`)
})
