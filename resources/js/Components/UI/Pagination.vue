<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ links: Array });
</script>

<template>
    <div v-if="links && links.length > 3" class="flex flex-wrap items-center gap-1">
        <template v-for="(link, i) in links" :key="i">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="rounded-md px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-pink-500 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'"
                v-html="link.label"
            />
            <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
        </template>
    </div>
</template>
