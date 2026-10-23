<script setup>
import { t } from '@nextcloud/l10n'

// Vue 2.7: v-model on components = value + input (not modelValue).
defineProps({
	value: { type: String, default: 'all' },
	options: { type: Array, default: () => [] },
	label: { type: String, default: () => t('hot_tracks', 'Sport') },
})

const emit = defineEmits(['input'])

function onChange(event) {
	emit('input', event.target.value)
}
</script>

<template>
	<label class="hot-tracks-sport-filter">
		<span class="hot-tracks-visually-hidden">{{ label }}</span>
		<select :value="value" @change="onChange">
			<option value="all">{{ t('hot_tracks', 'All') }}</option>
			<option v-for="option in options" :key="option.id" :value="option.id">
				{{ option.label }}
			</option>
		</select>
	</label>
</template>
