<script setup>
defineProps({ label: String, error: String });
</script>

<template>
    <label class="block">
        <span v-if="label" class="label">{{ label }}</span>
        <slot />
        <span v-if="error" class="mt-1 block text-xs text-red-600">{{ error }}</span>
    </label>
</template>
