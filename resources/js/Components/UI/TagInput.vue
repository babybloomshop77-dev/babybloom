<script setup>
import { ref } from 'vue';

const model = defineModel({ type: Array, default: () => [] });
defineProps({ placeholder: String });
const text = ref('');

function add() {
    const value = text.value.trim();
    if (value && !model.value.includes(value)) {
        model.value = [...model.value, value];
    }
    text.value = '';
}

function remove(i) {
    model.value = model.value.filter((_, idx) => idx !== i);
}
</script>

<template>
    <div class="rounded-lg border border-gray-300 bg-white p-1.5 focus-within:border-pink-400 focus-within:ring-1 focus-within:ring-pink-400">
        <div class="flex flex-wrap gap-1.5">
            <span
                v-for="(tag, i) in model"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-md bg-pink-50 px-2 py-1 text-xs text-pink-700"
            >
                {{ tag }}
                <button type="button" class="text-pink-400 hover:text-pink-700" @click="remove(i)">✕</button>
            </span>
            <input
                v-model="text"
                type="text"
                class="min-w-[10rem] flex-1 border-0 p-1 text-sm focus:ring-0"
                :placeholder="placeholder"
                @keydown.enter.prevent="add"
                @blur="add"
            />
        </div>
    </div>
</template>
