<script setup>
import { computed, ref } from 'vue';
import { imageFor, money, genderIcons } from '@/utils';

/**
 * Мультиселект товаров со склада.
 * options: [{ id, product: { name, images }, size, color, sell_price, status, reserved_for }]
 * v-model: массив id выбранных товаров
 */
const props = defineProps({ options: { type: Array, default: () => [] } });
const model = defineModel({ type: Array, default: () => [] });

const open = ref(false);
const query = ref('');

const statusLabels = { processing: 'в обработке', reserved: 'бронь', sold: 'продан' };

const byId = computed(() => new Map(props.options.map((o) => [o.id, o])));
const selected = computed(() => model.value.map((id) => byId.value.get(id)).filter(Boolean));

// «2-3» находит и «2–3», и «2—3»: все виды тире приводим к обычному дефису
const normalize = (s) => String(s ?? '').toLowerCase().replace(/[‐-―−]/g, '-');

// Для кого: «Мальчик» и «Девочка» включают унисекс
const gender = ref('');
const genderTabs = [
    { value: '', label: 'Все' },
    { value: 'boy', label: '👦' },
    { value: 'girl', label: '👧' },
];
const matchesGender = (o) => !gender.value || [gender.value, 'unisex'].includes(o.product?.gender);

// Поиск по словам: «marvel 100 black» — каждое слово где-то в названии, размере или цвете
const filtered = computed(() => {
    const words = normalize(query.value).split(/\s+/).filter(Boolean);
    return props.options.filter((o) => {
        if (!matchesGender(o)) return false;
        if (!words.length) return true;
        const text = normalize(`${o.product?.name ?? ''} ${o.size ?? ''} ${o.color ?? ''}`);
        return words.every((w) => text.includes(w));
    });
});

const isSelected = (id) => model.value.includes(id);

function toggle(id) {
    model.value = isSelected(id) ? model.value.filter((x) => x !== id) : [...model.value, id];
}

const label = (o) => [o.size, o.color].filter(Boolean).join(' · ');
</script>

<template>
    <div class="space-y-2">
        <!-- Выбранные товары -->
        <div v-if="selected.length" class="space-y-1.5">
            <div
                v-for="o in selected"
                :key="o.id"
                class="flex items-center gap-2.5 rounded-lg border border-pink-200 bg-pink-50/60 p-1.5 pr-2"
            >
                <img v-if="imageFor(o.product, o.color)" :src="imageFor(o.product, o.color)" class="h-10 w-10 shrink-0 rounded-md object-cover" />
                <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white">🧸</div>
                <div class="min-w-0 flex-1 leading-tight">
                    <div class="truncate text-sm font-medium text-gray-900">{{ o.product?.name }}</div>
                    <div class="truncate text-xs text-gray-500">{{ label(o) || '—' }}</div>
                </div>
                <div class="whitespace-nowrap text-sm font-semibold tabular-nums">{{ money(o.sell_price) }}</div>
                <button type="button" class="ml-1 rounded-md px-2 py-1 text-gray-400 hover:bg-white hover:text-red-600" @click="toggle(o.id)">✕</button>
            </div>
        </div>

        <button
            type="button"
            class="flex w-full items-center justify-between rounded-lg border border-dashed border-gray-300 px-3 py-2.5 text-sm text-gray-600 hover:border-pink-300 hover:text-pink-600"
            @click="open = !open"
        >
            <span>{{ open ? 'Скрыть список' : '＋ Выбрать товары со склада' }}</span>
            <span class="text-xs text-gray-400">выбрано {{ selected.length }} из {{ options.length }}</span>
        </button>

        <!-- Список склада -->
        <div v-if="open" class="rounded-lg border border-gray-200">
            <div class="flex gap-2 border-b p-2">
                <input v-model="query" type="search" class="input min-w-0 flex-1" placeholder="Поиск: название, размер, цвет…" />
                <div class="flex shrink-0 gap-0.5 rounded-lg bg-gray-100 p-0.5">
                    <button
                        v-for="t in genderTabs"
                        :key="t.value"
                        type="button"
                        class="rounded-md px-2.5 text-sm"
                        :class="gender === t.value ? 'bg-white shadow-sm' : 'text-gray-500'"
                        @click="gender = t.value"
                    >
                        {{ t.label }}
                    </button>
                </div>
            </div>
            <div class="max-h-72 divide-y overflow-y-auto">
                <label
                    v-for="o in filtered"
                    :key="o.id"
                    class="flex cursor-pointer items-center gap-2.5 px-2.5 py-2 hover:bg-gray-50"
                    :class="{ 'bg-pink-50/60': isSelected(o.id) }"
                >
                    <input
                        type="checkbox"
                        class="h-5 w-5 shrink-0 rounded text-pink-500 focus:ring-pink-400"
                        :checked="isSelected(o.id)"
                        @change="toggle(o.id)"
                    />
                    <img v-if="imageFor(o.product, o.color)" :src="imageFor(o.product, o.color)" class="h-9 w-9 shrink-0 rounded-md object-cover" />
                    <div v-else class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-100 text-sm">🧸</div>
                    <div class="min-w-0 flex-1 leading-tight">
                        <div class="truncate text-sm text-gray-900">
                            <span v-if="genderIcons[o.product?.gender]">{{ genderIcons[o.product.gender] }} </span>{{ o.product?.name }}
                        </div>
                        <div class="truncate text-xs text-gray-500">
                            {{ label(o) || '—' }}
                            <span v-if="statusLabels[o.status]" class="ml-1 text-amber-600">
                                · {{ statusLabels[o.status] }}<template v-if="o.reserved_for"> ({{ o.reserved_for }})</template>
                            </span>
                        </div>
                    </div>
                    <div class="whitespace-nowrap text-sm tabular-nums text-gray-700">{{ money(o.sell_price) }}</div>
                </label>
                <div v-if="!filtered.length" class="px-3 py-6 text-center text-sm text-gray-400">Ничего не найдено</div>
            </div>
        </div>
    </div>
</template>
