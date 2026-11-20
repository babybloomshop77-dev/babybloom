<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import MonthFilter from '@/Components/UI/MonthFilter.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import { money, date, today, sourceColors } from '@/utils';

const props = defineProps({ purchases: Object, totals: Object, filters: Object, sources: Object, statuses: Object });

const filters = reactive({
    month: props.filters.month,
    source: props.filters.source || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
});
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('purchases.index'), filters, { preserveState: true, replace: true }), 300);
});

/* ---------- Массовый выбор ---------- */
const selected = ref([]);
const bulkDate = ref(today());
const allSelected = computed(() => props.purchases.data.length > 0 && selected.value.length === props.purchases.data.length);

function toggleAll() {
    selected.value = allSelected.value ? [] : props.purchases.data.map((p) => p.id);
}

function bulk(status) {
    router.post(
        route('purchases.bulk-status'),
        { ids: selected.value, status, date: bulkDate.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

function setOne(item, status) {
    router.post(route('purchases.bulk-status'), { ids: [item.id], status, date: today() }, { preserveScroll: true });
}

function destroy(item) {
    const extra = item.items.length ? `\nВсе ${item.stock_items_count} шт. этой закупки удалятся со склада.` : '';
    if (confirm(`Удалить закупку «${item.title}»?${extra}`)) router.delete(route('purchases.destroy', item.id), { preserveScroll: true });
}

const thumbs = (item) => item.items.map((i) => i.product?.images?.[0]?.url).filter(Boolean).slice(0, 4);
</script>

<template>
    <Head title="Закупки" />
    <AuthenticatedLayout>
        <PageHeader title="Закупки" subtitle="Заказы в Pinduoduo и у оптовика">
            <MonthFilter v-model="filters.month" />
            <Link :href="route('purchases.create')" class="btn-primary">+ Новая закупка</Link>
        </PageHeader>

        <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Товар" :value="money(totals.amd)" :hint="totals.yuan ? `¥ ${totals.yuan.toLocaleString('ru-RU')}` : null" />
            <StatCard label="Доставка" :value="money(totals.shipping)" />
            <StatCard label="Итого" :value="money(totals.amd + totals.shipping)" tone="pink" />
            <StatCard label="В пути" :value="`${totals.in_transit} закуп.`" />
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <input v-model="filters.search" type="search" class="filter-input" placeholder="Поиск…" />
            <select v-model="filters.status" class="filter-select">
                <option value="">Все статусы</option>
                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <select v-model="filters.source" class="filter-select">
                <option value="">Все источники</option>
                <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
            </select>
            <label class="flex w-full items-center gap-2 px-1 py-1 text-sm text-gray-600 md:hidden">
                <input type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" :checked="allSelected" @change="toggleAll" />
                Выбрать все
            </label>
        </div>

        <!-- Панель массовых действий -->
        <div
            v-if="selected.length"
            class="sticky top-16 z-10 lg:top-2 mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm text-white shadow-lg"
        >
            <span class="font-medium">Выбрано: {{ selected.length }}</span>
            <span class="ml-2 text-gray-400">дата доставки</span>
            <input v-model="bulkDate" type="date" class="rounded-md border-0 bg-gray-800 py-1 text-sm text-white" />
            <button class="btn btn-sm bg-emerald-500 text-white hover:bg-emerald-600" @click="bulk('delivered')">✅ Доставлено</button>
            <button class="btn btn-sm bg-violet-500 text-white hover:bg-violet-600" @click="bulk('ordered')">🚚 В пути</button>
            <button class="ml-auto text-gray-400 hover:text-white" @click="selected = []">Отмена</button>
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div
                v-for="item in purchases.data"
                :key="item.id"
                class="m-card"
                :class="{ '!border-pink-300 !bg-pink-50/60': selected.includes(item.id) }"
            >
                <div class="flex items-start gap-3">
                    <input v-model="selected" type="checkbox" :value="item.id" class="mt-1 h-5 w-5 rounded text-pink-500 focus:ring-pink-400" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <Link :href="route('purchases.edit', item.id)" class="font-medium leading-snug text-gray-900">{{ item.title }}</Link>
                            <div class="whitespace-nowrap text-right font-semibold tabular-nums">{{ money(item.total) }}</div>
                        </div>
                        <div class="mt-0.5 text-xs text-gray-500">
                            {{ date(item.purchased_at) }} · {{ item.quantity }} шт.
                            <span v-if="item.sold_count">· прод. {{ item.sold_count }}</span>
                            · доставка {{ money(item.shipping_cost) }}
                        </div>
                        <div v-if="item.items.length" class="mt-0.5 truncate text-xs text-gray-400">
                            {{ item.items.map((i) => i.product?.name).join(', ') }}
                        </div>
                        <span v-if="item.status === 'ordered'" class="badge mt-1 bg-violet-100 text-violet-700">🚚 В пути</span>
                        <div v-if="item.goods_paid_before" class="text-xs text-amber-600">товар оплачен ранее</div>
                    </div>
                </div>
                <div v-if="thumbs(item).length" class="mt-2 flex gap-1.5 pl-8">
                    <img v-for="src in thumbs(item)" :key="src" :src="src" class="h-11 w-11 rounded-md object-cover" />
                </div>
                <div class="mt-3 flex items-center gap-2 pl-8">
                    <button
                        v-if="item.status === 'ordered'"
                        class="btn flex-1 bg-violet-100 text-violet-700"
                        @click="setOne(item, 'delivered')"
                    >
                        ✅ Отметить доставлено
                    </button>
                    <span v-else class="flex-1 text-sm text-emerald-700">✅ Доставлено {{ date(item.delivered_at) }}</span>
                    <Link :href="route('purchases.edit', item.id)" class="btn-secondary px-4">✎</Link>
                    <button class="btn-danger px-4" @click="destroy(item)">✕</button>
                </div>
            </div>
            <div v-if="!purchases.data.length" class="m-card py-10 text-center text-gray-400">Закупок нет</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" :checked="allSelected" @change="toggleAll" />
                        </th>
                        <th>Дата</th>
                        <th>Закупка</th>
                        <th>Статус</th>
                        <th class="text-right">Штук</th>
                        <th class="text-right">Товар</th>
                        <th class="text-right">Доставка</th>
                        <th class="text-right">Всего</th>
                        <th class="text-right">За 1 шт</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in purchases.data" :key="item.id" :class="{ 'bg-pink-50/60': selected.includes(item.id) }">
                        <td>
                            <input v-model="selected" type="checkbox" :value="item.id" class="rounded text-pink-500 focus:ring-pink-400" />
                        </td>
                        <td class="whitespace-nowrap text-gray-500">{{ date(item.purchased_at) }}</td>
                        <td>
                            <div class="flex items-center gap-3">
                                <div v-if="thumbs(item).length" class="flex -space-x-2">
                                    <img v-for="src in thumbs(item)" :key="src" :src="src" class="h-9 w-9 rounded-md border-2 border-white object-cover" />
                                </div>
                                <div>
                                    <Link :href="route('purchases.edit', item.id)" class="font-medium text-gray-900 hover:text-pink-600">{{ item.title }}</Link>
                                    <div class="flex flex-wrap items-center gap-x-2 text-xs text-gray-400">
                                        <span class="badge" :class="sourceColors[item.source]">{{ sources[item.source] }}</span>
                                        <span v-if="item.items.length">{{ item.items.map((i) => i.product?.name).join(', ') }}</span>
                                        <span v-else-if="item.product">{{ item.product.name }}</span>
                                        <a v-if="item.url" :href="item.url" target="_blank" rel="noopener" class="text-orange-600 hover:underline">ссылка ↗</a>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap">
                            <button
                                v-if="item.status === 'ordered'"
                                class="badge bg-violet-100 text-violet-700 hover:bg-violet-200"
                                title="Нажмите, чтобы отметить доставленным"
                                @click="setOne(item, 'delivered')"
                            >
                                🚚 В пути
                            </button>
                            <span v-else class="badge bg-emerald-100 text-emerald-700">✅ Доставлено</span>
                            <div v-if="item.delivered_at" class="mt-0.5 text-xs text-gray-400">{{ date(item.delivered_at) }}</div>
                        </td>
                        <td class="text-right tabular-nums">
                            {{ item.quantity }}
                            <div v-if="item.sold_count" class="text-xs text-gray-400">прод. {{ item.sold_count }}</div>
                        </td>
                        <td class="whitespace-nowrap text-right tabular-nums">
                            {{ money(item.price_amd) }}
                            <div v-if="item.price_yuan" class="text-xs text-gray-400">¥ {{ item.price_yuan }}</div>
                        </td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ money(item.shipping_cost) }}</td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">
                            {{ money(item.total) }}
                            <div v-if="item.goods_paid_before" class="text-xs font-normal text-amber-600">товар оплачен ранее</div>
                        </td>
                        <td class="whitespace-nowrap text-right tabular-nums text-gray-500">{{ money(item.unit_cost) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <Link :href="route('purchases.edit', item.id)" class="btn-secondary btn-sm">✎</Link>
                            <button class="btn-danger btn-sm ml-1" @click="destroy(item)">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!purchases.data.length">
                        <td colspan="10" class="py-10 text-center text-gray-400">Закупок нет</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="purchases.links" /></div>
    </AuthenticatedLayout>
</template>
