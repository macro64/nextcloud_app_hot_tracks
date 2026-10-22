<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'
import ActivityDetail from './components/ActivityDetail.vue'
import { formatDistance, formatDuration, formatElevation, formatWhen, sportLabel } from './lib/format.js'

const loading = ref(true)
const syncing = ref(false)
const error = ref('')
const notice = ref('')
const overview = ref(null)
const selectedId = ref(null)

const initialError = new URLSearchParams(window.location.search).get('error')
if (initialError === 'config') {
	error.value = 'Der Auth-Broker ist in den Verwaltungseinstellungen noch nicht eingerichtet.'
}
if (initialError === 'oauth') {
	error.value = 'Die Verbindung mit Strava ist fehlgeschlagen. Bitte erneut versuchen.'
}

const connectUrl = generateUrl('/apps/strava/oauth/start')
const zipUrl = generateUrl('/apps/strava/api/week/zip')

const selected = computed(() => {
	const rows = overview.value?.activities ?? []
	return rows.find((row) => row.stravaId === selectedId.value) ?? null
})

const gpxCount = computed(() => (overview.value?.activities ?? []).filter((row) => row.hasGpx).length)

async function load() {
	loading.value = true
	try {
		const response = await axios.get(generateUrl('/apps/strava/api/overview'))
		overview.value = response.data
	} catch (loadError) {
		error.value = 'Die Übersicht konnte nicht geladen werden.'
	} finally {
		loading.value = false
	}
}

async function syncNow() {
	syncing.value = true
	notice.value = ''
	error.value = ''
	try {
		const response = await axios.post(generateUrl('/apps/strava/api/sync'))
		const created = response.data.created ?? 0
		if (response.data.rateLimited) {
			notice.value = `Strava-Limit erreicht. ${created} neue Aktivitäten sind gespeichert, der Rest folgt beim nächsten Lauf.`
		} else if (created === 0) {
			notice.value = 'Keine neuen Aktivitäten.'
		} else {
			notice.value = created === 1 ? '1 Aktivität gespeichert.' : `${created} Aktivitäten gespeichert.`
		}
		await load()
	} catch (syncError) {
		error.value = syncError?.response?.data?.error || 'Abgleich fehlgeschlagen.'
	} finally {
		syncing.value = false
	}
}

async function disconnect() {
	if (!window.confirm('Verbindung trennen? Die GPX-Dateien im Ordner Strava bleiben erhalten.')) return
	error.value = ''
	try {
		await axios.post(generateUrl('/apps/strava/api/disconnect'))
		selectedId.value = null
		await load()
	} catch (disconnectError) {
		error.value = 'Die Verbindung konnte nicht getrennt werden.'
	}
}

onMounted(load)
</script>

<template>
	<div>
		<header class="strava-top">
			<div>
				<p class="strava-kicker">Letzte 7 Tage</p>
				<h2>Strava</h2>
			</div>
			<div v-if="overview?.connected && !selected" class="strava-actions">
				<NcButton :disabled="syncing" @click="syncNow">{{ syncing ? 'Gleich ab…' : 'Jetzt abgleichen' }}</NcButton>
				<NcButton v-if="gpxCount" :href="zipUrl">7 Tage als ZIP</NcButton>
				<NcButton type="tertiary" @click="disconnect">Trennen</NcButton>
			</div>
		</header>

		<p v-if="error" class="strava-error">{{ error }}</p>
		<p v-if="notice" class="strava-note">{{ notice }}</p>
		<p v-if="loading" class="strava-empty">Laden…</p>

		<template v-else-if="overview && !overview.configured">
			<p class="strava-empty">In den Verwaltungseinstellungen unter Strava den Auth-Broker eintragen. Danach kannst du dein Konto verbinden.</p>
		</template>

		<template v-else-if="overview && !overview.connected">
			<p class="strava-empty">Noch nicht verbunden. Die Aktivitäten landen nur in deinem Profil, im Ordner Strava.</p>
			<NcButton type="primary" :href="connectUrl">Mit Strava verbinden</NcButton>
		</template>

		<ActivityDetail
			v-else-if="selected"
			:activity="selected"
			:time-zone="overview.timeZone"
			@back="selectedId = null"
		/>

		<template v-else-if="overview">
			<p v-if="overview.athleteName" class="strava-muted">Verbunden als {{ overview.athleteName }}</p>
			<section class="strava-stats" aria-label="Summen der letzten 7 Tage">
				<div class="strava-stat"><span>Aktivitäten</span><strong>{{ overview.stats.count }}</strong></div>
				<div class="strava-stat"><span>Distanz</span><strong>{{ formatDistance(overview.stats.distance) }}</strong></div>
				<div class="strava-stat"><span>Bewegung</span><strong>{{ formatDuration(overview.stats.movingTime) }}</strong></div>
				<div class="strava-stat"><span>Höhe</span><strong>{{ formatElevation(overview.stats.elevation) }}</strong></div>
			</section>
			<ul v-if="overview.stats.bySport.length" class="strava-sports">
				<li v-for="group in overview.stats.bySport" :key="group.sport">
					{{ sportLabel(group.sport) }} · {{ group.count }} · {{ formatDistance(group.distance) }} · {{ formatElevation(group.elevation) }}
				</li>
			</ul>
			<p v-if="overview.activities.length === 0" class="strava-empty">
				Keine Aktivitäten in den letzten 7 Tagen. Der erste Abgleich holt die letzten 30 Tage.
			</p>
			<div v-else class="strava-list">
				<button
					v-for="activity in overview.activities"
					:key="activity.stravaId"
					type="button"
					class="strava-row"
					@click="selectedId = activity.stravaId"
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
		</template>
	</div>
</template>
