<script setup>
// Vue 2.7: v-model an Komponenten = value + input (nicht modelValue).
defineProps({
	value: { type: String, default: 'all' },
	options: { type: Array, default: () => [] },
	label: { type: String, default: 'Sportart' },
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
			<option value="all">Alle</option>
			<option v-for="option in options" :key="option.id" :value="option.id">
				{{ option.label }}
			</option>
		</select>
	</label>
</template>
