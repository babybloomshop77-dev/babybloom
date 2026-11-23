<script setup>
/**
 * Фильтр «для кого»: Все / 👦 / 👧 / 🧒 (+ «Не указано», если withNone).
 * «Мальчик» и «Девочка» на сервере включают и унисекс.
 */
const model = defineModel({ type: String, default: '' });
const props = defineProps({ withNone: { type: Boolean, default: false } });

const options = [
    { value: '', label: 'Все' },
    { value: 'boy', label: '👦 Мальчик' },
    { value: 'girl', label: '👧 Девочка' },
    { value: 'unisex', label: '🧒 Унисекс' },
    ...(props.withNone ? [{ value: 'none', label: '❔ Не указано' }] : []),
];
</script>

<template>
    <div class="no-scrollbar -mx-3 w-[calc(100%+1.5rem)] overflow-x-auto px-3 sm:mx-0 sm:w-auto sm:px-0">
        <div class="flex w-max gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-200">
            <button
                v-for="o in options"
                :key="o.value"
                type="button"
                class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm"
                :class="model === o.value ? 'bg-pink-500 text-white' : 'text-gray-600 hover:bg-gray-100'"
                @click="model = o.value"
            >
                {{ o.label }}
            </button>
        </div>
    </div>
</template>
