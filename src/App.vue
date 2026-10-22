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
	error.value = 'Die Verbindung mit der activity tracking app ist fehlgeschlagen. Bitte erneut versuchen.'
}

const connectUrl = generateUrl('/apps/hot_tracks/oauth/start')
const zipUrl = generateUrl('/apps/hot_tracks/api/week/zip')

const selected = computed(() => {
	const rows = overview.value?.activities ?? []
	return rows.find((row) => row.activityId === selectedId.value) ?? null
})

const gpxCount = computed(() => (overview.value?.activities ?? []).filter((row) => row.hasGpx).length)

async function load() {
	loading.value = true
	try {
		const response = await axios.get(generateUrl('/apps/hot_tracks/api/overview'))
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
		const response = await axios.post(generateUrl('/apps/hot_tracks/api/sync'))
		const created = response.data.created ?? 0
		if (response.data.rateLimited) {
			notice.value = `Limit der activity tracking app erreicht. ${created} neue Aktivitäten sind gespeichert, der Rest folgt beim nächsten Lauf.`
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
	if (!window.confirm('Verbindung trennen? Die GPX-Dateien im Ordner Hot Tracks bleiben erhalten.')) return
	error.value = ''
	try {
		await axios.post(generateUrl('/apps/hot_tracks/api/disconnect'))
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
		<header class="hot-tracks-top">
			<div>
				<p class="hot-tracks-kicker">Letzte 7 Tage</p>
				<h2>Hot Tracks</h2>
			</div>
			<div v-if="overview?.connected && !selected" class="hot-tracks-actions">
				<NcButton :disabled="syncing" @click="syncNow">{{ syncing ? 'Gleich ab…' : 'Jetzt abgleichen' }}</NcButton>
				<NcButton v-if="gpxCount" :href="zipUrl">7 Tage als ZIP</NcButton>
				<NcButton type="tertiary" @click="disconnect">Trennen</NcButton>
			</div>
		</header>

		<p v-if="error" class="hot-tracks-error">{{ error }}</p>
		<p v-if="notice" class="hot-tracks-note">{{ notice }}</p>
		<p v-if="loading" class="hot-tracks-empty">Laden…</p>

		<template v-else-if="overview && !overview.configured">
			<p class="hot-tracks-empty">In den Verwaltungseinstellungen unter Hot Tracks den Auth-Broker eintragen. Danach kannst du dein Konto verbinden.</p>
		</template>

		<template v-else-if="overview && !overview.connected">
			<p class="hot-tracks-empty">Noch nicht verbunden. Die Aktivitäten landen nur in deinem Profil, im Ordner Hot Tracks.</p>
			<NcButton type="primary" :href="connectUrl">Mit der activity tracking app verbinden</NcButton>
		</template>

		<ActivityDetail
			v-else-if="selected"
			:activity="selected"
			:time-zone="overview.timeZone"
			@back="selectedId = null"
		/>

		<template v-else-if="overview">
			<p v-if="overview.athleteName" class="hot-tracks-muted">Verbunden als {{ overview.athleteName }}</p>
			<section class="hot-tracks-stats" aria-label="Summen der letzten 7 Tage">
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
			<p v-if="overview.activities.length === 0" class="hot-tracks-empty">
				Keine Aktivitäten in den letzten 7 Tagen. Der erste Abgleich holt die letzten 30 Tage.
			</p>
			<div v-else class="hot-tracks-list">
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
		</template>
	</div>
</template>
