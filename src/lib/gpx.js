export function parseGpx(xml) {
	const doc = new DOMParser().parseFromString(xml, 'application/xml')
	if (doc.querySelector('parsererror')) return []
	return [...doc.getElementsByTagName('trkpt')].flatMap((point) => {
		const lat = Number(point.getAttribute('lat'))
		const lon = Number(point.getAttribute('lon'))
		if (!Number.isFinite(lat) || !Number.isFinite(lon)) return []
		const eleNode = point.getElementsByTagName('ele')[0]
		const ele = eleNode ? Number(eleNode.textContent) : Number.NaN
		return [{ lat, lon, ele }]
	})
}

export function elevationLine(points) {
	const usable = points.filter((point) => Number.isFinite(point.ele))
	if (usable.length < 2) return ''
	const min = Math.min(...usable.map((point) => point.ele))
	const max = Math.max(...usable.map((point) => point.ele))
	const span = Math.max(max - min, 1)
	const width = 640
	const height = 72
	return usable.map((point, index) => {
		const x = (index / (usable.length - 1)) * width
		const y = height - 6 - ((point.ele - min) / span) * (height - 12)
		return `${x.toFixed(1)},${y.toFixed(1)}`
	}).join(' ')
}

/**
 * Evenly thinned track for the Bikerouter URL (lon,lat;…).
 * @param {Array<{lat: number, lon: number}>} points
 * @param {number} maxPoints
 */
export function sampleTrack(points, maxPoints = 80) {
	const usable = points.filter((point) => Number.isFinite(point.lat) && Number.isFinite(point.lon))
	if (usable.length === 0) return []
	if (usable.length <= maxPoints) return usable
	const last = usable.length - 1
	const picked = []
	for (let index = 0; index < maxPoints; index++) {
		const at = Math.round((index / (maxPoints - 1)) * last)
		if (picked.length === 0 || picked[picked.length - 1] !== at) {
			picked.push(at)
		}
	}
	if (picked[picked.length - 1] !== last) {
		picked.push(last)
	}
	return picked.map((index) => usable[index])
}

/**
 * Link to bikerouter.de with the map center and lonlat waypoints.
 * @see https://bikerouter.de/
 */
export function bikerouterUrl(points) {
	const sampled = sampleTrack(points, 80)
	if (sampled.length < 2) return ''
	const lats = sampled.map((point) => point.lat)
	const lons = sampled.map((point) => point.lon)
	const lat = (Math.min(...lats) + Math.max(...lats)) / 2
	const lon = (Math.min(...lons) + Math.max(...lons)) / 2
	const lonlats = sampled
		.map((point) => `${point.lon.toFixed(6)},${point.lat.toFixed(6)}`)
		.join(';')
	return `https://bikerouter.de/#map=14/${lat.toFixed(4)}/${lon.toFixed(4)}/OpenTopoMap,route-quality&lonlats=${lonlats}`
}
