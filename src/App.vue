<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { n, t } from '@nextcloud/l10n'
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
	{ id: 'month', label: t('hot_tracks', 'Last month') },
	{ id: 'year', label: t('hot_tracks', 'Last year') },
	{ id: '5y', label: t('hot_tracks', '5 years') },
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

const loading = ref(true)
const syncing = ref(false)
const syncRange = ref(readStored(RANGE_KEY, 'year', syncRanges.map((row) => row.id)))
const listMode = ref(readStored(LIST_MODE_KEY, 'all'))
const heatmapRevision = ref(0)
const error = ref('')
const notice = ref('')
const overview = ref(null)
const selectedId = ref(null)

const syncRangeLabel = computed(() => syncRanges.find((row) => row.id === syncRange.value)?.label ?? t('hot_tracks', 'Time range'))
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
	error.value = t('hot_tracks', 'Please enter your Strava API credentials under Settings → Hot Tracks first.')
}
if (initialError === 'oauth') {
	error.value = t('hot_tracks', 'Connecting to Strava failed. Please try again.')
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
		error.value = t('hot_tracks', 'Could not load the overview.')
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
			parts.push(n('hot_tracks', '%n new', '%n new', created))
		}
		if (updated > 0) {
			parts.push(n('hot_tracks', '%n updated', '%n updated', updated))
		}
		if (response.data.rateLimited) {
			notice.value = parts.length
				? t('hot_tracks', 'Activity tracking app rate limit reached. {parts} ({range}) – choose the same range again once the limit clears.', { parts: parts.join(', '), range: label })
				: t('hot_tracks', 'Activity tracking app rate limit reached. Please sync the same range ({range}) again later.', { range: label })
		} else if (parts.length === 0) {
			notice.value = t('hot_tracks', 'No new or incomplete activities for {range}.', { range: label })
		} else {
			notice.value = t('hot_tracks', '{parts} saved ({range}).', { parts: parts.join(', '), range: label })
		}
		await load({ quiet: true })
		heatmapRevision.value += 1
	} catch (syncError) {
		error.value = syncError?.response?.data?.error || t('hot_tracks', 'Sync failed.')
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
				<h2>{{ t('hot_tracks', 'Hot Tracks') }}</h2>
			</div>
			<div v-if="overview?.connected && !selected" class="hot-tracks-actions">
				<label class="hot-tracks-range">
					<span class="hot-tracks-visually-hidden">{{ t('hot_tracks', 'Time range') }}</span>
					<select v-model="syncRange" :disabled="syncing">
						<option v-for="range in syncRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<NcButton :disabled="syncing" @click="syncNow">{{ syncing ? t('hot_tracks', 'Syncing …') : t('hot_tracks', 'Sync') }}</NcButton>
				<NcButton v-if="gpxCount" :href="zipUrl">{{ t('hot_tracks', 'Download all as ZIP') }}</NcButton>
			</div>
		</header>

		<p v-if="error" class="hot-tracks-error">{{ error }}</p>
		<p v-if="notice" class="hot-tracks-note">{{ notice }}</p>
		<p v-if="loading" class="hot-tracks-empty">{{ t('hot_tracks', 'Loading …') }}</p>

		<template v-else-if="overview && !overview.configured">
			<p class="hot-tracks-empty">
				{{ t('hot_tracks', 'Enter the Strava client ID and secret under Settings → Hot Tracks (API access at strava.com/settings/api). Then connect here.') }}
			</p>
			<NcButton type="primary" :href="settingsUrl">{{ t('hot_tracks', 'Go to settings') }}</NcButton>
		</template>

		<template v-else-if="overview && !overview.connected">
			<p class="hot-tracks-empty">{{ t('hot_tracks', 'Not connected yet. Activities are stored only in your profile, in the Hot Tracks folder.') }}</p>
			<a class="hot-tracks-strava-connect" :href="connectUrl">
				<img
					:src="connectButtonSrc"
					width="237"
					height="48"
					:alt="t('hot_tracks', 'Connect with Strava')"
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
					{{ t('hot_tracks', 'Connected as {name}', { name: overview.athleteName }) }}
					<template v-if="overview.storedCount">
						· {{ n('hot_tracks', '%n activity stored', '%n activities stored', overview.storedCount) }}
					</template>
				</p>
				<section class="hot-tracks-stats" :aria-label="t('hot_tracks', 'Totals for the displayed activities')">
					<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Activities') }}</span><strong>{{ overview.stats.count }}</strong></div>
					<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Distance') }}</span><strong>{{ formatDistance(overview.stats.distance) }}</strong></div>
					<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Moving') }}</span><strong>{{ formatDuration(overview.stats.movingTime) }}</strong></div>
					<div class="hot-tracks-stat"><span>{{ t('hot_tracks', 'Elevation') }}</span><strong>{{ formatElevation(overview.stats.elevation) }}</strong></div>
				</section>
				<ul v-if="overview.stats.bySport.length" class="hot-tracks-sports">
					<li v-for="group in overview.stats.bySport" :key="group.sport">
						{{ sportLabel(group.sport) }} · {{ group.count }} · {{ formatDistance(group.distance) }} · {{ formatElevation(group.elevation) }}
					</li>
				</ul>
				<div class="hot-tracks-list-head">
					<p class="hot-tracks-kicker hot-tracks-list-title">{{ t('hot_tracks', 'All activities') }}</p>
					<SportFilter
						:value="listMode"
						:options="sportOptions"
						:label="t('hot_tracks', 'Filter by sport')"
						@input="setListMode"
					/>
				</div>
				<p
					v-if="overview.activities.length === 0"
					class="hot-tracks-empty"
					v-html="t('hot_tracks', 'Nothing for this filter yet. {linkStart}Choose sports in settings{linkEnd} and sync above.', {
						linkStart: `<a href=\"${settingsUrl}\">`,
						linkEnd: '</a>',
					}, undefined, { escape: false })"
				/>
				<p v-else-if="overview.storedCount > overview.activities.length" class="hot-tracks-muted">
					{{ t('hot_tracks', 'Showing the latest {shown} of {total} (filtered).', { shown: overview.activities.length, total: overview.storedCount }) }}
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
