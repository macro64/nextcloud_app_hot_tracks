function childText(point, localName) {
	for (const node of point.getElementsByTagName('*')) {
		if (node.localName === localName && node.textContent) return node.textContent.trim()
	}
	return ''
}

export function parseGpx(xml) {
	const doc = new DOMParser().parseFromString(xml, 'application/xml')
	if (doc.querySelector('parsererror')) return []
	return [...doc.getElementsByTagName('trkpt')].flatMap((point) => {
		const lat = Number(point.getAttribute('lat'))
		const lon = Number(point.getAttribute('lon'))
		if (!Number.isFinite(lat) || !Number.isFinite(lon)) return []
		const ele = Number(childText(point, 'ele'))
		const time = Date.parse(childText(point, 'time'))
		const hr = Number(childText(point, 'hr'))
		return [{
			lat,
			lon,
			ele,
			time: Number.isFinite(time) ? time : Number.NaN,
			hr: Number.isFinite(hr) && hr > 0 ? hr : Number.NaN,
		}]
	})
}

function haversineMeters(from, to) {
	const radius = 6371000
	const lat1 = from.lat * Math.PI / 180
	const lat2 = to.lat * Math.PI / 180
	const dLat = (to.lat - from.lat) * Math.PI / 180
	const dLon = (to.lon - from.lon) * Math.PI / 180
	const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2
	return 2 * radius * Math.asin(Math.min(1, Math.sqrt(h)))
}

function withMetrics(points) {
	let distance = 0
	const rows = points.map((point, index) => {
		let speed = Number.NaN
		if (index > 0) {
			const previous = points[index - 1]
			const meters = haversineMeters(previous, point)
			if (Number.isFinite(meters)) distance += meters
			const seconds = (point.time - previous.time) / 1000
			if (Number.isFinite(meters) && seconds > 0.5 && seconds < 180) {
				const kmh = (meters / seconds) * 3.6
				if (kmh >= 0 && kmh <= 200) speed = kmh
			}
		}
		return { ...point, distance, speed }
	})
	return rows.map((point, index) => {
		const window = []
		for (let cursor = Math.max(0, index - 2); cursor <= Math.min(rows.length - 1, index + 2); cursor++) {
			if (Number.isFinite(rows[cursor].speed)) window.push(rows[cursor].speed)
		}
		const speed = window.length > 0 ? window.reduce((sum, value) => sum + value, 0) / window.length : Number.NaN
		return { ...point, speed }
	})
}

function niceStep(raw) {
	if (!Number.isFinite(raw) || raw <= 0) return 1
	const power = 10 ** Math.floor(Math.log10(raw))
	const fraction = raw / power
	const nice = fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 5 ? 5 : 10
	return nice * power
}

function axisTicks(min, max, count = 4) {
	if (!Number.isFinite(min) || !Number.isFinite(max)) return []
	const span = Math.max(max - min, 1)
	const step = niceStep(span / Math.max(count - 1, 1))
	const start = Math.floor(min / step) * step
	const end = Math.ceil(max / step) * step
	const ticks = []
	for (let value = start; value <= end + step * 0.01; value += step) {
		ticks.push(Math.round(value * 1000) / 1000)
		if (ticks.length > 8) break
	}
	if (ticks.length < 2) {
		ticks.push(Math.round((start + step) * 1000) / 1000)
	}
	return ticks
}

function yFor(value, ticks, top, bottom) {
	const min = ticks[0]
	const max = ticks[ticks.length - 1]
	const span = Math.max(max - min, 1e-6)
	return bottom - ((value - min) / span) * (bottom - top)
}

function polyline(samples, key, ticks, left, right, top, bottom) {
	const usable = samples.filter((point) => Number.isFinite(point[key]))
	if (usable.length < 2 || ticks.length < 2) return ''
	const end = samples[samples.length - 1].distance
	return samples.map((point) => {
		if (!Number.isFinite(point[key])) return ''
		const x = end > 0 ? left + (point.distance / end) * (right - left) : left
		const y = yFor(point[key], ticks, top, bottom)
		return `${x.toFixed(1)},${y.toFixed(1)}`
	}).filter(Boolean).join(' ')
}

/**
 * Elevation, speed and heart-rate chart with labeled axes.
 * Speed is derived from the track timestamps. Heart rate is shown when the file has it.
 */
export function profileChart(points) {
	const samples = withMetrics(points).filter((point) => Number.isFinite(point.ele))
	if (samples.length < 2) return null
	const hasSpeed = samples.some((point) => Number.isFinite(point.speed))
	const hasHr = samples.some((point) => Number.isFinite(point.hr))
	const width = 800
	const height = 200
	const top = 28
	const bottom = 168
	const left = 58
	const right = width - (hasHr ? 108 : 56)
	const elevation = axisTicks(Math.min(...samples.map((point) => point.ele)), Math.max(...samples.map((point) => point.ele)))
	const speed = hasSpeed
		? axisTicks(0, Math.max(...samples.map((point) => point.speed).filter(Number.isFinite)))
		: []
	const hr = hasHr
		? axisTicks(
			Math.min(...samples.map((point) => point.hr).filter(Number.isFinite)),
			Math.max(...samples.map((point) => point.hr).filter(Number.isFinite)),
		)
		: []
	const grid = elevation.slice(0, -1).map((tick) => {
		const y = yFor(tick, elevation, top, bottom)
		return { x1: left, y1: y, x2: right, y2: y }
	})
	const labels = elevation.map((tick) => ({
		x: left - 6,
		y: yFor(tick, elevation, top, bottom) + 4,
		text: `${Math.round(tick)} m`,
		anchor: 'end',
		kind: 'elevation',
	}))
	if (speed.length > 1) {
		speed.forEach((tick) => {
			labels.push({
				x: right + 6,
				y: yFor(tick, speed, top, bottom) + 4,
				text: `${Math.round(tick)}`,
				anchor: 'start',
				kind: 'speed',
			})
		})
		labels.push({ x: right + 6, y: 14, text: 'km/h', anchor: 'start', kind: 'speed' })
	}
	if (hr.length > 1) {
		hr.forEach((tick) => {
			labels.push({
				x: width - 8,
				y: yFor(tick, hr, top, bottom) + 4,
				text: `${Math.round(tick)}`,
				anchor: 'end',
				kind: 'hr',
			})
		})
		labels.push({ x: width - 8, y: 14, text: 'bpm', anchor: 'end', kind: 'hr' })
	}
	const lines = [{
		key: 'elevation',
		className: 'is-elevation',
		points: polyline(samples, 'ele', elevation, left, right, top, bottom),
	}]
	if (speed.length > 1) {
		lines.push({
			key: 'speed',
			className: 'is-speed',
			points: polyline(samples, 'speed', speed, left, right, top, bottom),
		})
	}
	if (hr.length > 1) {
		lines.push({
			key: 'hr',
			className: 'is-hr',
			points: polyline(samples, 'hr', hr, left, right, top, bottom),
		})
	}
	return {
		viewBox: `0 0 ${width} ${height}`,
		grid,
		labels,
		lines: lines.filter((line) => line.points !== ''),
		hasSpeed: speed.length > 1,
		hasHr: hr.length > 1,
	}
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
