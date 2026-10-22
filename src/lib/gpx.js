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
