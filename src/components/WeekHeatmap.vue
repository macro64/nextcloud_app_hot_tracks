<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { heatLayer } from '../lib/heatLayer.js'
import SportFilter from './SportFilter.vue'

const RANGE_KEY = 'hot_tracks_heat_range'
const MODE_KEY = 'hot_tracks_heat_mode'
const SETTINGS_URL = '/settings/user/hot_tracks'
const PAGE_SIZE = 12
const DEFAULT_VIEW = { lat: 51.16, lon: 10.45, zoom: 6 }

const props = defineProps({
	revision: { type: Number, default: 0 },
	sportOptions: { type: Array, default: () => [] },
})

const heatRanges = [
	{ id: 'week', label: t('hot_tracks', 'Week') },
	{ id: 'month', label: t('hot_tracks', 'Month') },
	{ id: 'year', label: t('hot_tracks', 'Year') },
	{ id: 'all', label: t('hot_tracks', 'All') },
]

function readStored(key, fallback, allowed) {
	try {
		const value = window.localStorage.getItem(key)
		if (value && (!allowed || allowed.includes(value))) return value
	} catch (error) {
		// ignore
	}
	return fallback
}

function writeStored(key, value) {
	try {
		window.localStorage.setItem(key, value)
	} catch (error) {
		// ignore
	}
}

const mapEl = ref(null)
const loading = ref(false)
const error = ref('')
const tracks = ref([])
const loadedCount = ref(0)
const totalCount = ref(0)
const modeLabels = ref([])
const heatRange = ref(readStored(RANGE_KEY, 'year', heatRanges.map((row) => row.id)))
const heatMode = ref(readStored(MODE_KEY, 'all'))
let map
let layer
let observer
let requestId = 0
let didFit = false

const rangeLabel = computed(() => heatRanges.find((row) => row.id === heatRange.value)?.label ?? heatRange.value)
const sportsLabel = computed(() => {
	if (heatMode.value !== 'all') {
		const hit = props.sportOptions.find((row) => row.id === heatMode.value)
		if (hit) return hit.label
	}
	if (modeLabels.value.length <= 1) return modeLabels.value[0] || t('hot_tracks', 'Sports')
	if (modeLabels.value.length === 2) return modeLabels.value.join(' · ')
	return t('hot_tracks', 'All')
})
const hasPoints = computed(() => tracks.value.some((track) => track.length > 0))
const progressLabel = computed(() => {
	if (!loading.value) return ''
	if (totalCount.value > 0) {
		return t('hot_tracks', 'Tracks {loaded}/{total} …', {
			loaded: Math.min(loadedCount.value, totalCount.value),
			total: totalCount.value,
		})
	}
	return t('hot_tracks', 'Loading tracks …')
})
const heatAriaLabel = computed(() => t('hot_tracks', 'Heatmap · {range} · {sports}', {
	range: rangeLabel.value,
	sports: sportsLabel.value,
}))

function setRange(range) {
	if (heatRange.value === range) return
	heatRange.value = range
	writeStored(RANGE_KEY, range)
}

function setMode(mode) {
	if (heatMode.value === mode) return
	heatMode.value = mode
	writeStored(MODE_KEY, mode)
}

watch(
	() => props.sportOptions.map((row) => row.id).join(','),
	(ids) => {
		if (!ids) return
		const allowed = new Set(ids.split(','))
		if (heatMode.value !== 'all' && !allowed.has(heatMode.value)) {
			setMode('all')
		}
	},
)

function destroyMap() {
	if (observer) {
		observer.disconnect()
		observer = undefined
	}
	if (map) {
		map.remove()
		map = undefined
		layer = undefined
	}
}

function watchSize() {
	if (!mapEl.value || observer) return
	observer = new ResizeObserver(() => {
		if (!map) {
			ensureMap()
			return
		}
		map.invalidateSize()
		if (layer) layer._schedule()
	})
	observer.observe(mapEl.value)
}

function fit() {
	const points = tracks.value.flat()
	if (!map || points.length === 0) return
	if (points.length === 1) {
		map.setView(points[0], 14)
		return
	}
	map.fitBounds(L.latLngBounds(points), { padding: [28, 28], maxZoom: 14 })
}

function ensureMap() {
	const el = mapEl.value
	if (!el || el.clientHeight < 40) return false
	watchSize()
	if (!map) {
		map = L.map(el, {
			scrollWheelZoom: true,
			preferCanvas: true,
		}).setView([DEFAULT_VIEW.lat, DEFAULT_VIEW.lon], DEFAULT_VIEW.zoom)
		L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; OpenStreetMap',
			className: 'hot-tracks-tiles-dark',
			maxZoom: 18,
			updateWhenIdle: true,
			keepBuffer: 1,
		}).addTo(map)
		layer = heatLayer([]).addTo(map)
	} else {
		map.invalidateSize()
	}
	return true
}

function paint({ refit = false } = {}) {
	if (!ensureMap()) {
		nextTick(() => {
			if (ensureMap()) {
				layer.setTracks(tracks.value)
				if (refit && hasPoints.value) {
					fit()
					didFit = true
				}
			}
		})
		return
	}
	layer.setTracks(tracks.value)
	if (refit && hasPoints.value) {
		fit()
		didFit = true
	}
}

function parseTracks(rows) {
	return rows.flatMap((track) => {
		const line = []
		for (const pair of track.points ?? []) {
			const lat = Number(pair[0])
			const lon = Number(pair[1])
			if (Number.isFinite(lat) && Number.isFinite(lon)) line.push([lat, lon])
		}
		return line.length > 0 ? [line] : []
	})
}

async function load() {
	const current = ++requestId
	const mode = heatMode.value
	const range = heatRange.value
	loading.value = true
	error.value = ''
	tracks.value = []
	loadedCount.value = 0
	totalCount.value = 0
	didFit = false
	modeLabels.value = []
	await nextTick()
	ensureMap()
	paint()

	let offset = 0
	let hasMore = true
	try {
		while (hasMore) {
			if (current !== requestId) return
			const response = await axios.get(generateUrl('/apps/hot_tracks/api/week/tracks'), {
				params: {
					range,
					sport: mode,
					limit: PAGE_SIZE,
					offset,
					_: `${props.revision}-${mode}-${range}-${offset}`,
				},
			})
			if (current !== requestId) return
			if (Array.isArray(response.data?.modeLabels) && response.data.modeLabels.length) {
				modeLabels.value = response.data.modeLabels
			}
			totalCount.value = Number(response.data?.total) || totalCount.value
			const batch = parseTracks(Array.isArray(response.data?.tracks) ? response.data.tracks : [])
			if (batch.length) {
				const firstPaint = !didFit
				tracks.value = tracks.value.concat(batch)
				loadedCount.value = tracks.value.length
				paint({ refit: firstPaint })
			} else {
				loadedCount.value = Math.min(offset + PAGE_SIZE, totalCount.value || offset)
			}
			hasMore = Boolean(response.data?.hasMore)
			offset += PAGE_SIZE
			if (offset >= 120) {
				hasMore = false
			}
		}
		if (current !== requestId) return
		if (!hasPoints.value) {
			paint()
		}
	} catch (loadError) {
		if (current !== requestId) return
		error.value = t('hot_tracks', 'Could not load the heatmap.')
		tracks.value = []
		modeLabels.value = []
		paint()
	} finally {
		if (current !== requestId) return
		loading.value = false
	}
}

watch(
	() => `${heatRange.value}:${heatMode.value}:${props.revision}`,
	() => {
		load()
	},
	{ immediate: true },
)

onMounted(() => {
	nextTick(() => ensureMap())
})

onBeforeUnmount(() => {
	requestId += 1
	destroyMap()
})
</script>

<template>
	<aside class="hot-tracks-side" :aria-label="heatAriaLabel">
		<div class="hot-tracks-heat-head">
			<p class="hot-tracks-kicker">{{ t('hot_tracks', 'Heatmap') }}</p>
			<div class="hot-tracks-heat-controls">
				<label class="hot-tracks-heat-range">
					<span class="hot-tracks-visually-hidden">{{ t('hot_tracks', 'Time range') }}</span>
					<select :value="heatRange" @change="setRange($event.target.value)">
						<option v-for="range in heatRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<SportFilter
					:value="heatMode"
					:options="sportOptions"
					:label="t('hot_tracks', 'Filter by sport')"
					@input="setMode"
				/>
			</div>
		</div>
		<div class="hot-tracks-heat-shell">
			<div ref="mapEl" class="hot-tracks-heat" />
			<p v-if="loading" class="hot-tracks-heat-progress">{{ progressLabel }}</p>
			<p v-else-if="error" class="hot-tracks-heat-overlay hot-tracks-error">{{ error }}</p>
			<p
				v-else-if="!hasPoints"
				class="hot-tracks-heat-overlay"
				v-html="t('hot_tracks', 'No tracks for {sports} · {range}. {linkStart}Choose sports{linkEnd}', {
					sports: sportsLabel,
					range: rangeLabel.toLowerCase(),
					linkStart: `<a href=\"${SETTINGS_URL}\">`,
					linkEnd: '</a>',
				}, undefined, { escape: false })"
			/>
		</div>
		<div v-if="hasPoints" class="hot-tracks-heat-legend" aria-hidden="true">
			<span>{{ t('hot_tracks', 'rare') }}</span>
			<span class="hot-tracks-heat-scale"></span>
			<span>{{ t('hot_tracks', 'often') }}</span>
		</div>
	</aside>
</template>
