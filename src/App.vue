<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ActivityDetail from './components/ActivityDetail.vue'
import SportFilter from './components/SportFilter.vue'
import WeekHeatmap from './components/WeekHeatmap.vue'
import { formatDistance, formatDuration, formatElevation, formatWhen, sportLabel } from './lib/format.js'

const RANGE_KEY = 'hot_tracks_sync_range'
const LIST_MODE_KEY = 'hot_tracks_list_mode'
const EMPTY_OPTIONS = Object.freeze([])

const syncRanges = [
	{ id: 'month', label: 'Letzter Monat' },
	{ id: 'year', label: 'Letztes Jahr' },
	{ id: '5y', label: '5 Jahre' },
	{ id: 'all', label: 'Alles' },
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

const loading = ref(true)
const syncing = ref(false)
const syncRange = ref(readStored(RANGE_KEY, 'year', syncRanges.map((row) => row.id)))
const listMode = ref(readStored(LIST_MODE_KEY, 'all'))
const heatmapRevision = ref(0)
const error = ref('')
const notice = ref('')
const overview = ref(null)
const selectedId = ref(null)

const syncRangeLabel = computed(() => syncRanges.find((row) => row.id === syncRange.value)?.label ?? 'Zeitraum')
const sportOptions = computed(() => overview.value?.sportOptions ?? EMPTY_OPTIONS)

watch(syncRange, (value) => {
	writeStored(RANGE_KEY, value)
})

function setListMode(mode) {
	if (listMode.value === mode) return
	listMode.value = mode
	writeStored(LIST_MODE_KEY, mode)
	load({ quiet: true })
}

const initialError = new URLSearchParams(window.location.search).get('error')
if (initialError === 'config') {
	error.value = 'Bitte zuerst unter Einstellungen → Hot Tracks die Strava-API-Daten eintragen.'
}
if (initialError === 'oauth') {
	error.value = 'Die Verbindung mit Strava ist fehlgeschlagen. Bitte erneut versuchen.'
}

const settingsUrl = '/settings/user/hot_tracks'
const connectUrl = generateUrl('/apps/hot_tracks/oauth/start')
const connectButtonSrc = imagePath('hot_tracks', 'btn_strava_connect_with_orange.svg')
const zipUrl = generateUrl('/apps/hot_tracks/api/week/zip')

const selected = computed(() => {
	const rows = overview.value?.activities ?? []
	return rows.find((row) => row.activityId === selectedId.value) ?? null
})

const gpxCount = computed(() => (overview.value?.activities ?? []).filter((row) => row.hasGpx).length)

async function load({ quiet = false } = {}) {
	if (!quiet) loading.value = true
	try {
		const response = await axios.get(generateUrl('/apps/hot_tracks/api/overview'), {
			params: { sport: listMode.value },
		})
		overview.value = response.data
		const allowed = (response.data.sportOptions ?? []).map((row) => row.id)
		if (listMode.value !== 'all' && !allowed.includes(listMode.value)) {
			listMode.value = 'all'
			writeStored(LIST_MODE_KEY, 'all')
		}
	} catch (loadError) {
		error.value = 'Die Übersicht konnte nicht geladen werden.'
	} finally {
		if (!quiet) loading.value = false
	}
}

async function syncNow() {
	syncing.value = true
	notice.value = ''
	error.value = ''
	try {
		const response = await axios.post(generateUrl('/apps/hot_tracks/api/sync'), {
			range: syncRange.value,
		})
		const created = response.data.created ?? 0
		const updated = response.data.updated ?? 0
		const label = syncRangeLabel.value.toLowerCase()
		const parts = []
		if (created > 0) {
			parts.push(created === 1 ? '1 neu' : `${created} neu`)
		}
		if (updated > 0) {
			parts.push(updated === 1 ? '1 nachgezogen' : `${updated} nachgezogen`)
		}
		if (response.data.rateLimited) {
			notice.value = parts.length
				? `Limit der activity tracking app erreicht. ${parts.join(', ')} (${label}) – denselben Zeitraum erneut wählen, sobald das Limit frei ist.`
				: `Limit der activity tracking app erreicht. Bitte denselben Zeitraum (${label}) später erneut abgleichen.`
		} else if (parts.length === 0) {
			notice.value = `Keine neuen oder unvollständigen Aktivitäten für ${label}.`
		} else {
			notice.value = `${parts.join(', ')} gespeichert (${label}).`
		}
		await load({ quiet: true })
		heatmapRevision.value += 1
	} catch (syncError) {
		error.value = syncError?.response?.data?.error || 'Abgleich fehlgeschlagen.'
	} finally {
		syncing.value = false
	}
}

onMounted(load)
</script>

<template>
	<div class="hot-tracks-app">
		<header class="hot-tracks-top">
			<div>
				<h2>Hot Tracks</h2>
			</div>
			<div v-if="overview?.connected && !selected" class="hot-tracks-actions">
				<label class="hot-tracks-range">
					<span class="hot-tracks-visually-hidden">Zeitraum</span>
					<select v-model="syncRange" :disabled="syncing">
						<option v-for="range in syncRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<NcButton :disabled="syncing" @click="syncNow">{{ syncing ? 'Gleich ab…' : 'Abgleichen' }}</NcButton>
				<NcButton v-if="gpxCount" :href="zipUrl">Alles als ZIP</NcButton>
			</div>
		</header>

		<p v-if="error" class="hot-tracks-error">{{ error }}</p>
		<p v-if="notice" class="hot-tracks-note">{{ notice }}</p>
		<p v-if="loading" class="hot-tracks-empty">Laden…</p>

		<template v-else-if="overview && !overview.configured">
			<p class="hot-tracks-empty">
				Unter Einstellungen → Hot Tracks die Strava-Client-ID und das Secret eintragen
				(API-Zugang unter strava.com/settings/api). Danach hier verbinden.
			</p>
			<NcButton type="primary" :href="settingsUrl">Zu den Einstellungen</NcButton>
		</template>

		<template v-else-if="overview && !overview.connected">
			<p class="hot-tracks-empty">Noch nicht verbunden. Die Aktivitäten landen nur in deinem Profil, im Ordner Hot Tracks.</p>
			<a class="hot-tracks-strava-connect" :href="connectUrl">
				<img
					:src="connectButtonSrc"
					width="237"
					height="48"
					alt="Connect with Strava"
				>
			</a>
		</template>

		<ActivityDetail
			v-else-if="selected"
			:activity="selected"
			:time-zone="overview.timeZone"
			@back="selectedId = null"
		/>

		<div v-else-if="overview" class="hot-tracks-board">
			<div>
				<p v-if="overview.athleteName" class="hot-tracks-muted">
					Verbunden als {{ overview.athleteName }}
					<template v-if="overview.storedCount">
						· {{ overview.storedCount === 1 ? '1 Aktivität gespeichert' : `${overview.storedCount} Aktivitäten gespeichert` }}
					</template>
				</p>
				<section class="hot-tracks-stats" aria-label="Summen der angezeigten Aktivitäten">
					<div class="hot-tracks-stat"><span>Aktivitäten</span><strong>{{ overview.stats.count }}</strong></div>
					<div class="hot-tracks-stat"><span>Distanz</span><strong>{{ formatDistance(overview.stats.distance) }}</strong></div>
					<div class="hot-tracks-stat"><span>Bewegung</span><strong>{{ formatDuration(overview.stats.movingTime) }}</strong></div>
					<div class="hot-tracks-stat"><span>Höhe</span><strong>{{ formatElevation(overview.stats.elevation) }}</strong></div>
				</section>
				<ul v-if="overview.stats.bySport.length" class="hot-tracks-sports">
					<li v-for="group in overview.stats.bySport" :key="group.sport">
						{{ sportLabel(group.sport) }} · {{ group.count }} · {{ formatDistance(group.distance) }} · {{ formatElevation(group.elevation) }}
					</li>
				</ul>
				<div class="hot-tracks-list-head">
					<p class="hot-tracks-kicker hot-tracks-list-title">Alle Aktivitäten</p>
					<SportFilter
						:value="listMode"
						:options="sportOptions"
						label="Sportart filtern"
						@input="setListMode"
					/>
				</div>
				<p v-if="overview.activities.length === 0" class="hot-tracks-empty">
					Noch nichts für diesen Filter.
					<a :href="settingsUrl">Sportarten in den Einstellungen</a>
					wählen und oben abgleichen.
				</p>
				<p v-else-if="overview.storedCount > overview.activities.length" class="hot-tracks-muted">
					Die letzten {{ overview.activities.length }} von {{ overview.storedCount }} (gefiltert).
				</p>
				<div v-if="overview.activities.length" class="hot-tracks-list">
					<button
						v-for="activity in overview.activities"
						:key="activity.activityId"
						type="button"
						class="hot-tracks-row"
						@click="selectedId = activity.activityId"
					>
						<span>
							<strong>{{ activity.name }}</strong><br>
							<small>{{ sportLabel(activity.sport) }} · {{ formatWhen(activity.startTime, overview.timeZone) }}</small>
						</span>
						<span>{{ formatDistance(activity.distance) }}</span>
						<span>{{ formatDuration(activity.movingTime) }}</span>
						<span>{{ formatElevation(activity.elevation) }}</span>
					</button>
				</div>
			</div>
			<WeekHeatmap :revision="heatmapRevision" :sport-options="sportOptions" />
		</div>
	</div>
</template>
