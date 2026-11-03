/**
 * Raster basemap for Leaflet.
 * Nextcloud sends Referrer-Policy: no-referrer, which blocks tile.openstreetmap.org
 * unless each tile image sets referrerPolicy (Leaflet 1.9+ option).
 * No third-party API key — suitable for a Nextcloud app.
 */
export const TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'

export const TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'

export const TILE_OPTIONS = {
	attribution: TILE_ATTRIBUTION,
	maxZoom: 19,
	// Overrides document no-referrer so OSM volunteer tiles accept the request.
	referrerPolicy: 'strict-origin-when-cross-origin',
}
