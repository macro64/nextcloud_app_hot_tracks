<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import NcButton from '@nextcloud/vue/components/NcButton'
import { bikerouterUrl, elevationLine, parseGpx } from '../lib/gpx.js'
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

const gpxUrl = computed(() => generateUrl(`/apps/hot_tracks/api/activities/${props.activity.activityId}/gpx`))
const routerUrl = computed(() => bikerouterUrl(points.value))
const profile = computed(() => elevationLine(points.value))
const elevationSpan = computed(() => {
	const values = points.value.map((point) => point.ele).filter((value) => Number.isFinite(value))
	if (values.length < 2) return ''
	let locale
	try {
		locale = getCanonicalLocale() || undefined
	} catch (error) {
		locale = undefined
	}
	const min = Math.round(Math.min(...values))
	const max = Math.round(Math.max(...values))
	return `${min.toLocaleString(locale)}–${max.toLocaleString(locale)} m`
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
			gpxError.value = t('hot_tracks', 'The GPX file contains no points.')
			return
		}
		draw()
	} catch (error) {
		gpxError.value = t('hot_tracks', 'Could not read the GPX file.')
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
watch(() => props.activity.activityId, () => {
	resetMap()
	loadGpx()
})
onBeforeUnmount(resetMap)
</script>

<template>
	<article>
		<div class="hot-tracks-detail-head">
			<div>
				<p class="hot-tracks-kicker">{{ sportLabel(activity.sport) }}</p>
				<h2>{{ activity.name }}</h2>
				<p class="hot-tracks-muted">{{ formatWhen(activity.startTime, timeZone) }}</p>
			</div>
			<div class="hot-tracks-actions">
				<NcButton @click="emit('back')">{{ t('hot_tracks', 'Back') }}</NcButton>
				<a
					v-if="routerUrl"
					class="button"
					:href="routerUrl"
					target="_blank"
					rel="noreferrer noopener"
				>{{ t('hot_tracks', 'Open with Bikerouter') }}</a>
				<NcButton v-if="activity.hasGpx" type="primary" :href="gpxUrl">{{ t('hot_tracks', 'Download GPX') }}</NcButton>
			</div>
		</div>

		<section class="hot-tracks-stats">
			<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Distance') }}</span><strong>{{ formatDistance(activity.distance) }}</strong></div>
			<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Moving') }}</span><strong>{{ formatDuration(activity.movingTime) }}</strong></div>
			<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Elapsed') }}</span><strong>{{ formatDuration(activity.elapsedTime) }}</strong></div>
			<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Elevation') }}</span><strong>{{ formatElevation(activity.elevation) }}</strong></div>
		</section>
		<p class="hot-tracks-muted">{{ t('hot_tracks', 'Pace:') }} {{ tempoLabel(activity) }}</p>

		<div v-if="activity.hasGpx && !gpxError" ref="mapEl" class="hot-tracks-map" />
		<p v-else-if="gpxError" class="hot-tracks-error">{{ gpxError }}</p>
		<p v-else class="hot-tracks-empty">{{ t('hot_tracks', 'No GPS track. The summary is still available.') }}</p>

		<div v-if="profile" class="hot-tracks-profile">
			<p class="hot-tracks-muted">{{ t('hot_tracks', 'Elevation along the track') }}<span v-if="elevationSpan"> · {{ elevationSpan }}</span></p>
			<svg viewBox="0 0 640 72" role="img" :aria-label="t('hot_tracks', 'Elevation profile')">
				<polyline :points="profile" />
			</svg>
		</div>
	</article>
</template>
