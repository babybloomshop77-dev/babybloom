<script setup>
const model = defineModel({ type: String });
</script>

<template>
    <div class="flex w-full items-center gap-2 sm:w-auto">
        <input
            type="month"
            class="input min-w-0 flex-1 sm:w-40 sm:flex-none"
            :value="model === 'all' ? '' : model"
            @change="model = $event.target.value || 'all'"
        />
        <button
            type="button"
            class="btn-secondary btn-sm whitespace-nowrap py-2 sm:py-1"
            :class="{ '!border-pink-400 !text-pink-600': model === 'all' }"
            @click="model = 'all'"
        >
            За всё время
        </button>
    </div>
</template>
