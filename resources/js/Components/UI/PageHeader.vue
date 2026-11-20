<script setup>
defineProps({ title: String, subtitle: String });
</script>

<template>
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 sm:text-2xl">{{ title }}</h1>
            <p v-if="subtitle" class="mt-0.5 text-sm text-gray-500">{{ subtitle }}</p>
        </div>
        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <slot />
        </div>
    </div>
</template>
