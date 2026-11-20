<script setup>
defineProps({ label: String, value: [String, Number], hint: String, tone: { type: String, default: 'gray' } });

const tones = {
    gray: 'text-gray-900',
    green: 'text-emerald-600',
    red: 'text-red-600',
    pink: 'text-pink-600',
};
</script>

<template>
    <div class="card min-w-0 p-3 sm:p-4">
        <div class="truncate text-[11px] font-medium uppercase tracking-wide text-gray-500 sm:text-xs">{{ label }}</div>
        <div class="mt-1 truncate text-lg font-semibold tabular-nums sm:text-2xl" :class="tones[tone]">{{ value }}</div>
        <div v-if="hint" class="mt-0.5 text-xs text-gray-400">{{ hint }}</div>
    </div>
</template>
