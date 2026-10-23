import L from 'leaflet'

const STOPS = [
	[0.08, 20, 70, 255, 0.32],
	[0.2, 0, 185, 255, 0.48],
	[0.38, 40, 210, 60, 0.6],
	[0.55, 190, 230, 20, 0.72],
	[0.72, 255, 196, 0, 0.82],
	[0.86, 255, 90, 0, 0.88],
	[1, 210, 16, 16, 0.92],
]

const brushCache = new Map()

function shade(strength) {
	if (strength <= STOPS[0][0]) return null
	let upper = STOPS[STOPS.length - 1]
	let lower = STOPS[0]
	for (let index = 1; index < STOPS.length; index++) {
		if (strength <= STOPS[index][0]) {
			upper = STOPS[index]
			lower = STOPS[index - 1]
			break
		}
	}
	const span = upper[0] - lower[0] || 1
	const fraction = Math.min(1, Math.max(0, (strength - lower[0]) / span))
	return [
		lower[1] + (upper[1] - lower[1]) * fraction,
		lower[2] + (upper[2] - lower[2]) * fraction,
		lower[3] + (upper[3] - lower[3]) * fraction,
		lower[4] + (upper[4] - lower[4]) * fraction,
	]
}

function brush(radius) {
	const key = Math.round(radius * 2)
	const cached = brushCache.get(key)
	if (cached) return cached
	const size = key
	const canvas = document.createElement('canvas')
	canvas.width = size
	canvas.height = size
	const context = canvas.getContext('2d')
	const mid = size / 2
	const gradient = context.createRadialGradient(mid, mid, 0, mid, mid, mid)
	gradient.addColorStop(0, 'rgba(255,255,255,1)')
	gradient.addColorStop(0.28, 'rgba(255,255,255,0.55)')
	gradient.addColorStop(0.62, 'rgba(255,255,255,0.16)')
	gradient.addColorStop(1, 'rgba(255,255,255,0)')
	context.fillStyle = gradient
	context.fillRect(0, 0, size, size)
	brushCache.set(key, canvas)
	return canvas
}

function samples(track, project, step) {
	const out = []
	let previous = null
	let pending = 0
	for (const latlng of track) {
		const point = project(latlng)
		if (!previous) {
			out.push(point)
			previous = point
			continue
		}
		const dx = point.x - previous.x
		const dy = point.y - previous.y
		const distance = Math.hypot(dx, dy)
		if (distance < 0.5) continue
		let along = step - pending
		while (along <= distance) {
			const ratio = along / distance
			out.push({
				x: previous.x + dx * ratio,
				y: previous.y + dy * ratio,
			})
			along += step
		}
		pending = distance - (along - step)
		previous = point
	}
	return out
}

const HeatLayer = L.Layer.extend({
	initialize(tracks) {
		this._tracks = tracks
		this._scratch = null
		this._busy = false
	},

	setTracks(tracks) {
		this._tracks = tracks
		this._schedule(true)
	},

	onAdd(map) {
		this._map = map
		this._canvas = L.DomUtil.create('canvas', 'hot-tracks-heat-canvas')
		this._canvas.style.pointerEvents = 'none'
		this._canvas.style.transition = 'opacity 80ms linear'
		map.getPanes().overlayPane.appendChild(this._canvas)
		// Full redraw only after movement; hide the layer while dragging.
		map.on('moveend zoomend resize', this._onSettle, this)
		map.on('movestart zoomstart', this._onInteract, this)
		this._schedule(true)
	},

	onRemove(map) {
		map.off('moveend zoomend resize', this._onSettle, this)
		map.off('movestart zoomstart', this._onInteract, this)
		if (this._frame) cancelAnimationFrame(this._frame)
		if (this._delay) clearTimeout(this._delay)
		L.DomUtil.remove(this._canvas)
		this._scratch = null
	},

	_onInteract() {
		this._busy = true
		if (this._canvas) this._canvas.style.opacity = '0.2'
		if (this._frame) {
			cancelAnimationFrame(this._frame)
			this._frame = 0
		}
		if (this._delay) {
			clearTimeout(this._delay)
			this._delay = 0
		}
	},

	_onSettle() {
		this._busy = false
		this._schedule(true)
	},

	_schedule(force = false) {
		if (this._busy && !force) return
		if (this._frame) cancelAnimationFrame(this._frame)
		if (this._delay) clearTimeout(this._delay)
		// Short debounce after zoom or pan so the layer is not drawn several times.
		this._delay = setTimeout(() => {
			this._delay = 0
			this._frame = requestAnimationFrame(() => {
				this._frame = 0
				this._draw()
			})
		}, force ? 0 : 40)
	},

	_draw() {
		const map = this._map
		const canvas = this._canvas
		if (!map || !canvas || this._busy) return
		const size = map.getSize()
		if (size.x === 0 || size.y === 0) return

		const ratio = Math.min(window.devicePixelRatio || 1, 1.5)
		const width = Math.round(size.x * ratio)
		const height = Math.round(size.y * ratio)
		if (canvas.width !== width || canvas.height !== height) {
			canvas.width = width
			canvas.height = height
			canvas.style.width = `${size.x}px`
			canvas.style.height = `${size.y}px`
		}
		L.DomUtil.setPosition(canvas, map.containerPointToLayerPoint([0, 0]))

		const zoom = map.getZoom()
		const radius = Math.max(7, Math.min(16, 6.5 + (zoom - 11) * 1.1))
		const stamp = brush(radius)
		const step = Math.max(radius * 0.85, 8)
		const context = canvas.getContext('2d', { willReadFrequently: true })
		context.setTransform(ratio, 0, 0, ratio, 0, 0)
		context.clearRect(0, 0, size.x, size.y)
		context.globalCompositeOperation = 'lighter'

		if (!this._scratch || this._scratch.width !== width || this._scratch.height !== height) {
			this._scratch = document.createElement('canvas')
			this._scratch.width = width
			this._scratch.height = height
		}
		const scratch = this._scratch
		const pen = scratch.getContext('2d')
		const margin = radius + 2
		const project = (latlng) => map.latLngToContainerPoint(latlng)

		for (const track of this._tracks) {
			if (track.length === 0) continue
			pen.setTransform(ratio, 0, 0, ratio, 0, 0)
			pen.clearRect(0, 0, size.x, size.y)
			pen.globalCompositeOperation = 'lighter'
			pen.globalAlpha = 1
			for (const point of samples(track, project, step)) {
				if (point.x < -margin || point.y < -margin || point.x > size.x + margin || point.y > size.y + margin) {
					continue
				}
				pen.drawImage(stamp, point.x - radius, point.y - radius)
			}
			context.setTransform(1, 0, 0, 1, 0, 0)
			context.globalAlpha = 0.26
			context.drawImage(scratch, 0, 0)
		}

		const image = context.getImageData(0, 0, width, height)
		const data = image.data
		for (let index = 0; index < data.length; index += 4) {
			const tone = shade(data[index + 3] / 255)
			if (!tone) {
				data[index + 3] = 0
				continue
			}
			data[index] = tone[0]
			data[index + 1] = tone[1]
			data[index + 2] = tone[2]
			data[index + 3] = Math.round(tone[3] * 255)
		}
		context.setTransform(1, 0, 0, 1, 0, 0)
		context.putImageData(image, 0, 0)
		canvas.style.opacity = '1'
	},
})

export function heatLayer(tracks) {
	return new HeatLayer(tracks)
}
