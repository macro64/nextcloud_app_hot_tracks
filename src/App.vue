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
const HEAT_RANGE_KEY = 'hot_tracks_heat_range'
const EMPTY_OPTIONS = Object.freeze([])

const syncRanges = [
	{ id: 'month', label: t('hot_tracks', 'Last month') },
	{ id: 'year', label: t('hot_tracks', 'Last year') },
	{ id: '5y', label: t('hot_tracks', '5 years') },
	{ id: 'all', label: t('hot_tracks', 'All') },
]
const heatRanges = computed(() => [
	{ id: 'week', label: t('hot_tracks', 'Week') },
	{ id: 'month', label: t('hot_tracks', 'Month') },
	{ id: 'year', label: t('hot_tracks', 'Year') },
	{ id: 'all', label: t('hot_tracks', 'All') },
])

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
const heatRange = ref(readStored(HEAT_RANGE_KEY, 'year', ['week', 'month', 'year', 'all']))
const listMode = ref(readStored('hot_tracks_heat_mode', '') || readStored(LIST_MODE_KEY, 'all'))
const heatmapRevision = ref(0)
const error = ref('')
const notice = ref('')
const overview = ref(null)
const selectedId = ref(null)

const syncRangeLabel = computed(() => syncRanges.find((row) => row.id === syncRange.value)?.label ?? t('hot_tracks', 'Time range'))
const sportOptions = computed(() => overview.value?.sportOptions ?? EMPTY_OPTIONS)
const listSportLabel = computed(() => {
	if (listMode.value === 'all') return t('hot_tracks', 'All')
	return sportOptions.value.find((row) => row.id === listMode.value)?.label || sportLabel(listMode.value)
})

watch(syncRange, (value) => {
	writeStored(RANGE_KEY, value)
})

function setHeatRange(range) {
	if (heatRange.value === range) return
	heatRange.value = range
	writeStored(HEAT_RANGE_KEY, range)
}

function setListMode(mode) {
	if (listMode.value === mode) return
	listMode.value = mode
	writeStored(LIST_MODE_KEY, mode)
	writeStored('hot_tracks_heat_mode', mode)
	load({ quiet: true })
}

const initialError = new URLSearchParams(window.location.search).get('error')
if (initialError === 'config') {
	error.value = t('hot_tracks', 'Finish setup first: an admin sets up the shared login under Administration settings → Hot Tracks.')
}
if (initialError === 'oauth') {
	error.value = t('hot_tracks', 'Strava connection failed. In Administration → Hot Tracks copy the Callback URL exactly into the auth broker redirect list (with or without index.php must match 1:1), then try again.')
}

const settingsUrl = '/settings/user/hot_tracks'
const adminSettingsUrl = '/settings/admin/hot_tracks'
const connectUrl = generateUrl('/apps/hot_tracks/oauth/start')
const connectButtonSrc = imagePath('hot_tracks', 'btn_strava_connect_with_orange.svg')
const zipUrl = generateUrl('/apps/hot_tracks/api/week/zip')

const selected = computed(() => {
	const rows = overview.value?.activities ?? []
	return rows.find((row) => row.activityId === selectedId.value) ?? null
})

const gpxCount = computed(() => (overview.value?.activities ?? []).filter((row) => row.hasGpx).length)
const needsFirstSync = computed(() => Boolean(overview.value?.connected && (overview.value?.storedCount ?? 0) === 0))
const emptyFilterHtml = computed(() => t(
	'hot_tracks',
	'Nothing for this filter. {linkStart}Adjust sports{linkEnd} or sync a wider range.',
	{
		linkStart: `<a href="${settingsUrl}">`,
		linkEnd: '</a>',
	},
	undefined,
	{ escape: false },
))

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
		<header v-if="overview?.connected && !selected && !needsFirstSync" class="hot-tracks-top">
			<div class="hot-tracks-heat-controls">
				<label class="hot-tracks-heat-range">
					<span class="hot-tracks-control-label">{{ t('hot_tracks', 'Time') }}</span>
					<select :value="heatRange" @change="setHeatRange($event.target.value)">
						<option v-for="range in heatRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<SportFilter
					:value="listMode"
					:options="sportOptions"
					:label="t('hot_tracks', 'Activity')"
					show-label
					@input="setListMode"
				/>
			</div>
			<div class="hot-tracks-actions">
				<label class="hot-tracks-range">
					<span class="hot-tracks-visually-hidden">{{ t('hot_tracks', 'Time range') }}</span>
					<select v-model="syncRange" :disabled="syncing">
						<option v-for="range in syncRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<NcButton :disabled="syncing" @click="syncNow">{{ syncing ? t('hot_tracks', 'Syncing …') : t('hot_tracks', 'Sync Strava') }}</NcButton>
				<NcButton v-if="gpxCount" :href="zipUrl">{{ t('hot_tracks', 'Download all tracks') }}</NcButton>
			</div>
		</header>

		<p v-if="error" class="hot-tracks-error">{{ error }}</p>
		<p v-if="notice" class="hot-tracks-note">{{ notice }}</p>
		<p v-if="loading" class="hot-tracks-empty">{{ t('hot_tracks', 'Loading …') }}</p>

		<section v-else-if="overview && !overview.configured" class="hot-tracks-setup" aria-labelledby="hot-tracks-setup-title">
			<p class="hot-tracks-setup-step">{{ t('hot_tracks', 'Step 1 of 2') }}</p>
			<h3 id="hot-tracks-setup-title">{{ t('hot_tracks', 'Set up the shared login') }}</h3>
			<p class="hot-tracks-setup-lead">
				{{ t('hot_tracks', 'An admin sets up the shared login under Administration settings → Hot Tracks.') }}
			</p>
			<NcButton v-if="overview.isAdmin" type="primary" :href="adminSettingsUrl">{{ t('hot_tracks', 'Open administration') }}</NcButton>
		</section>

		<section v-else-if="overview && !overview.connected" class="hot-tracks-setup" aria-labelledby="hot-tracks-connect-title">
			<p class="hot-tracks-setup-step">{{ t('hot_tracks', 'Step 2 of 2') }}</p>
			<h3 id="hot-tracks-connect-title">{{ t('hot_tracks', 'Connect Strava') }}</h3>
			<p class="hot-tracks-setup-lead">
				{{ t('hot_tracks', 'Authorize Hot Tracks. Your activities stay private in this Nextcloud.') }}
			</p>
			<p class="hot-tracks-setup-hint">
				{{ t('hot_tracks', 'After connecting, your Strava ID is shown under Personal settings → Hot Tracks. The shared login stays under Administration settings.') }}
			</p>
			<a class="hot-tracks-strava-connect" :href="connectUrl">
				<img
					:src="connectButtonSrc"
					width="237"
					height="48"
					:alt="t('hot_tracks', 'Connect with Strava')"
				>
			</a>
		</section>

		<section v-else-if="needsFirstSync && !selected" class="hot-tracks-setup" aria-labelledby="hot-tracks-sync-title">
			<p class="hot-tracks-setup-step">{{ t('hot_tracks', 'Almost done') }}</p>
			<h3 id="hot-tracks-sync-title">{{ t('hot_tracks', 'Fetch your activities') }}</h3>
			<p class="hot-tracks-setup-lead">
				{{ t('hot_tracks', 'Choose how far back to go, then sync once. Tracks land in the Hot Tracks folder.') }}
			</p>
			<div class="hot-tracks-actions">
				<label class="hot-tracks-range">
					<span class="hot-tracks-range-label">{{ t('hot_tracks', 'How far back?') }}</span>
					<select v-model="syncRange" :disabled="syncing">
						<option v-for="range in syncRanges" :key="range.id" :value="range.id">{{ range.label }}</option>
					</select>
				</label>
				<NcButton type="primary" :disabled="syncing" @click="syncNow">
					{{ syncing ? t('hot_tracks', 'Syncing …') : t('hot_tracks', 'Sync now') }}
				</NcButton>
			</div>
		</section>

		<ActivityDetail
			v-else-if="selected"
			:activity="selected"
			:time-zone="overview.timeZone"
			@back="selectedId = null"
		/>

		<div v-else-if="overview" class="hot-tracks-board">
			<div>
				<p v-if="overview.connected" class="hot-tracks-muted hot-tracks-connected-line">
					<template v-if="overview.athleteName">
						{{ t('hot_tracks', 'Connected as {name}', { name: overview.athleteName }) }}
					</template>
					<template v-else>
						{{ t('hot_tracks', 'Connected.') }}
					</template>
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
					<p class="hot-tracks-filter-note">{{ t('hot_tracks', 'Filter: {sport}', { sport: listSportLabel }) }}</p>
				</div>
				<p
					v-if="overview.activities.length === 0"
					class="hot-tracks-empty"
					v-html="emptyFilterHtml"
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
			<WeekHeatmap :revision="heatmapRevision" :sport-options="sportOptions" :mode="listMode" :range="heatRange" @mode="setListMode" />
		</div>
	</div>
</template>
