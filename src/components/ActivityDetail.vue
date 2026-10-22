<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import NcButton from '@nextcloud/vue/components/NcButton'
import { elevationLine, parseGpx } from '../lib/gpx.js'
import { formatDistance, formatDuration, formatElevation, formatWhen, sportLabel, tempoLabel } from '../lib/format.js'

const props = defineProps({
	activity: { type: Object, required: true },
	timeZone: { type: String, required: true },
})

const emit = defineEmits(['back'])

const mapEl = ref(null)
const points = ref([])
const gpxError = ref('')
let map
let layer

const gpxUrl = computed(() => generateUrl(`/apps/strava/api/activities/${props.activity.stravaId}/gpx`))
const profile = computed(() => elevationLine(points.value))
const elevationSpan = computed(() => {
	const values = points.value.map((point) => point.ele).filter((value) => Number.isFinite(value))
	if (values.length < 2) return ''
	const min = Math.round(Math.min(...values))
	const max = Math.round(Math.max(...values))
	return `${min.toLocaleString('de-DE')}–${max.toLocaleString('de-DE')} m`
})

function trackColor() {
	const value = getComputedStyle(document.body).getPropertyValue('--color-primary-element').trim()
	return value || '#1a5fb4'
}

function draw() {
	if (!mapEl.value || points.value.length === 0) return
	if (!map) {
		map = L.map(mapEl.value, { scrollWheelZoom: false })
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap',
			maxZoom: 18,
		}).addTo(map)
	}
	if (layer) layer.remove()
	layer = L.layerGroup().addTo(map)
	const latlngs = points.value.map((point) => [point.lat, point.lon])
	L.polyline(latlngs, { color: trackColor(), weight: 4 }).addTo(layer)
	const start = points.value[0]
	const end = points.value[points.value.length - 1]
	L.circleMarker([start.lat, start.lon], { radius: 6, color: '#1a5fb4', weight: 2, fillColor: '#1a5fb4', fillOpacity: 1 }).addTo(layer)
	L.circleMarker([end.lat, end.lon], { radius: 6, color: '#c64600', weight: 2, fillColor: '#c64600', fillOpacity: 1 }).addTo(layer)
	if (latlngs.length === 1) {
		map.setView(latlngs[0], 14)
	} else {
		map.fitBounds(latlngs, { padding: [24, 24] })
	}
	window.setTimeout(() => map.invalidateSize(), 50)
}

async function loadGpx() {
	points.value = []
	gpxError.value = ''
	if (!props.activity.hasGpx) return
	try {
		const response = await axios.get(gpxUrl.value, { responseType: 'text' })
		const xml = typeof response.data === 'string' ? response.data : ''
		points.value = parseGpx(xml)
		if (points.value.length === 0) {
			gpxError.value = 'Die GPX-Datei enthält keine Punkte.'
			return
		}
		draw()
	} catch (error) {
		gpxError.value = 'Die GPX-Datei konnte nicht gelesen werden.'
	}
}

function resetMap() {
	if (map) {
		map.remove()
		map = undefined
		layer = undefined
	}
}

onMounted(loadGpx)
watch(() => props.activity.stravaId, () => {
	resetMap()
	loadGpx()
})
onBeforeUnmount(resetMap)
</script>

<template>
	<article>
		<div class="strava-detail-head">
			<div>
				<p class="strava-kicker">{{ sportLabel(activity.sport) }}</p>
				<h2>{{ activity.name }}</h2>
				<p class="strava-muted">{{ formatWhen(activity.startTime, timeZone) }}</p>
			</div>
			<div class="strava-actions">
				<NcButton @click="emit('back')">Zurück</NcButton>
				<NcButton v-if="activity.hasGpx" type="primary" :href="gpxUrl">GPX herunterladen</NcButton>
			</div>
		</div>

		<section class="strava-stats">
			<div class="strava-stat"><span>Distanz</span><strong>{{ formatDistance(activity.distance) }}</strong></div>
			<div class="strava-stat"><span>Bewegung</span><strong>{{ formatDuration(activity.movingTime) }}</strong></div>
			<div class="strava-stat"><span>Gesamt</span><strong>{{ formatDuration(activity.elapsedTime) }}</strong></div>
			<div class="strava-stat"><span>Höhe</span><strong>{{ formatElevation(activity.elevation) }}</strong></div>
		</section>
		<p class="strava-muted">Tempo: {{ tempoLabel(activity) }}</p>

		<div v-if="activity.hasGpx && !gpxError" ref="mapEl" class="strava-map" />
		<p v-else-if="gpxError" class="strava-error">{{ gpxError }}</p>
		<p v-else class="strava-empty">Keine GPS-Spur. Die Zusammenfassung steht trotzdem zur Verfügung.</p>

		<div v-if="profile" class="strava-profile">
			<p class="strava-muted">Höhe entlang der Spur<span v-if="elevationSpan"> · {{ elevationSpan }}</span></p>
			<svg viewBox="0 0 640 72" role="img" aria-label="Höhenprofil">
				<polyline :points="profile" />
			</svg>
		</div>
	</article>
</template>
