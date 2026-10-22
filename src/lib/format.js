export const SPORT_LABELS = {
	Run: 'Laufen',
	TrailRun: 'Trailrun',
	VirtualRun: 'Laufen (virtuell)',
	Ride: 'Radfahren',
	MountainBikeRide: 'Mountainbike',
	GravelRide: 'Gravel',
	EBikeRide: 'E-Bike',
	EMountainBikeRide: 'E-Mountainbike',
	VirtualRide: 'Radfahren (virtuell)',
	Hike: 'Wandern',
	Walk: 'Gehen',
	Swim: 'Schwimmen',
	AlpineSki: 'Ski Alpin',
	BackcountrySki: 'Skitour',
	NordicSki: 'Langlauf',
	Snowboard: 'Snowboard',
	Workout: 'Training',
	WeightTraining: 'Krafttraining',
	Yoga: 'Yoga',
	RockClimbing: 'Klettern',
}

const PACE_SPORTS = new Set(['Run', 'TrailRun', 'VirtualRun', 'Walk', 'Hike'])

export function sportLabel(sport) {
	return SPORT_LABELS[sport] || sport || 'Aktivität'
}

export function usesPace(sport) {
	return PACE_SPORTS.has(sport)
}

export function formatDistance(meters) {
	const km = Number(meters) / 1000
	if (!Number.isFinite(km)) return '–'
	const digits = km >= 10 ? 1 : 2
	return `${km.toLocaleString('de-DE', { minimumFractionDigits: digits, maximumFractionDigits: digits })} km`
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
	return `${value.toLocaleString('de-DE')} m`
}

export function formatWhen(unixSeconds, timeZone) {
	const date = new Date(Number(unixSeconds) * 1000)
	return new Intl.DateTimeFormat('de-DE', {
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
	return `${kmh.toLocaleString('de-DE', { maximumFractionDigits: 1, minimumFractionDigits: 1 })} km/h`
}
