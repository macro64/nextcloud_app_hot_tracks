import { getCanonicalLocale, t } from '@nextcloud/l10n'

export const SPORT_LABELS = {
	AlpineSki: 'Alpine ski',
	BackcountrySki: 'Backcountry ski',
	Badminton: 'Badminton',
	Basketball: 'Basketball',
	Canoeing: 'Canoeing',
	Cricket: 'Cricket',
	Crossfit: 'CrossFit',
	Dance: 'Dance',
	EBikeRide: 'E-bike',
	Elliptical: 'Elliptical',
	EMountainBikeRide: 'E-mountain bike',
	Golf: 'Golf',
	GravelRide: 'Gravel',
	Handcycle: 'Handcycle',
	HighIntensityIntervalTraining: 'HIIT',
	Hike: 'Hike',
	IceSkate: 'Ice skate',
	InlineSkate: 'Inline skate',
	Kayaking: 'Kayaking',
	Kitesurf: 'Kitesurf',
	MountainBikeRide: 'Mountain bike',
	NordicSki: 'Nordic ski',
	Padel: 'Padel',
	PhysicalTherapy: 'Physical therapy',
	Pickleball: 'Pickleball',
	Pilates: 'Pilates',
	Racquetball: 'Racquetball',
	Ride: 'Ride',
	RockClimbing: 'Rock climbing',
	RollerSki: 'Roller ski',
	Rowing: 'Rowing',
	Run: 'Run',
	Sail: 'Sail',
	Skateboard: 'Skateboard',
	Snowboard: 'Snowboard',
	Snowshoe: 'Snowshoe',
	Soccer: 'Soccer',
	Squash: 'Squash',
	StairStepper: 'Stair stepper',
	StandUpPaddling: 'SUP',
	Surfing: 'Surfing',
	Swim: 'Swim',
	TableTennis: 'Table tennis',
	Tennis: 'Tennis',
	TrailRun: 'Trail run',
	Velomobile: 'Velomobile',
	VirtualRide: 'Virtual ride',
	VirtualRow: 'Virtual row',
	VirtualRun: 'Virtual run',
	Volleyball: 'Volleyball',
	Walk: 'Walk',
	WeightTraining: 'Weight training',
	Wheelchair: 'Wheelchair',
	Windsurf: 'Windsurf',
	Workout: 'Workout',
	Yoga: 'Yoga',
}

const PACE_SPORTS = new Set([
	'Run',
	'TrailRun',
	'VirtualRun',
	'Walk',
	'Hike',
	'Snowshoe',
])

function locale() {
	try {
		return getCanonicalLocale() || undefined
	} catch (error) {
		return undefined
	}
}

export function sportLabel(sport) {
	const key = SPORT_LABELS[sport]
	if (key) {
		return t('hot_tracks', key)
	}
	return sport || t('hot_tracks', 'Activity')
}

export function usesPace(sport) {
	return PACE_SPORTS.has(sport)
}

export function formatDistance(meters) {
	const km = Number(meters) / 1000
	if (!Number.isFinite(km)) return '–'
	const digits = km >= 10 ? 1 : 2
	return `${km.toLocaleString(locale(), { minimumFractionDigits: digits, maximumFractionDigits: digits })} km`
}

export function formatDuration(seconds) {
	const total = Math.max(0, Math.round(Number(seconds) || 0))
	const hours = Math.floor(total / 3600)
	const minutes = Math.floor((total % 3600) / 60)
	const secs = total % 60
	if (hours > 0) {
		return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
	}
	return `${minutes}:${String(secs).padStart(2, '0')}`
}

export function formatElevation(meters) {
	const value = Math.round(Number(meters) || 0)
	return `${value.toLocaleString(locale())} m`
}

export function formatWhen(unixSeconds, timeZone) {
	const date = new Date(Number(unixSeconds) * 1000)
	return new Intl.DateTimeFormat(locale(), {
		timeZone: timeZone || undefined,
		weekday: 'short',
		day: 'numeric',
		month: 'short',
		hour: '2-digit',
		minute: '2-digit',
	}).format(date)
}

export function tempoLabel(activity) {
	const distance = Number(activity.distance) || 0
	const moving = Number(activity.movingTime) || 0
	if (distance <= 0 || moving <= 0) return '–'
	if (usesPace(activity.sport)) {
		const minutesPerKm = (moving / 60) / (distance / 1000)
		let minutes = Math.floor(minutesPerKm)
		let seconds = Math.round((minutesPerKm - minutes) * 60)
		if (seconds === 60) {
			minutes += 1
			seconds = 0
		}
		return `${minutes}:${String(seconds).padStart(2, '0')} /km`
	}
	const kmh = (distance / 1000) / (moving / 3600)
	return `${kmh.toLocaleString(locale(), { maximumFractionDigits: 1, minimumFractionDigits: 1 })} km/h`
}
