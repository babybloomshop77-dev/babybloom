<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { money, sourceColors, imageFor } from '@/utils';

const props = defineProps({ products: Object, filters: Object, sources: Object });

const filters = reactive({ search: props.filters.search || '', source: props.filters.source || '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('products.index'), filters, { preserveState: true, replace: true }), 300);
});

function destroy(product) {
    if (confirm(`Удалить «${product.name}»? Склад этого товара тоже удалится.`)) {
        router.delete(route('products.destroy', product.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Товары" />
    <AuthenticatedLayout>
        <PageHeader title="Товары" :subtitle="`Всего: ${products.total}`">
            <input v-model="filters.search" type="search" class="filter-input" placeholder="Поиск по названию…" />
            <select v-model="filters.source" class="filter-select">
                <option value="">Все источники</option>
                <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
            </select>
            <Link :href="route('products.create')" class="btn-primary">+ Добавить товар</Link>
        </PageHeader>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            <div v-for="product in products.data" :key="product.id" class="card flex flex-col overflow-hidden">
                <Link :href="route('products.edit', product.id)" class="relative block aspect-[4/3] bg-gray-100">
                    <img
                        v-if="product.images.length"
                        :src="imageFor(product)"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                    <div v-else class="flex h-full items-center justify-center text-4xl text-gray-300">🧸</div>
                    <span class="badge absolute left-2 top-2" :class="sourceColors[product.source]">
                        {{ sources[product.source] }}
                    </span>
                    <span
                        v-if="product.images.length > 1"
                        class="badge absolute right-2 top-2 bg-black/50 text-white"
                    >
                        📷 {{ product.images.length }}
                    </span>
                </Link>

                <div class="flex flex-1 flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">{{ product.name }}</h3>
                        <span class="whitespace-nowrap font-semibold text-pink-600">{{ money(product.sell_price) }}</span>
                    </div>

                    <div class="mt-1 text-xs text-gray-500">
                        Себестоимость: {{ money(product.cost_price) }}
                        <span v-if="product.buy_price_yuan"> · ¥{{ product.buy_price_yuan }}</span>
                        <span v-if="product.cost_price && product.sell_price" class="text-emerald-600">
                            · маржа {{ money(product.sell_price - product.cost_price) }}
                        </span>
                    </div>

                    <div v-if="product.sizes?.length" class="mt-2 flex flex-wrap gap-1">
                        <span v-for="s in product.sizes" :key="s" class="badge bg-gray-100 text-gray-600">{{ s }}</span>
                    </div>
                    <div v-if="product.colors?.length" class="mt-1 flex flex-wrap gap-1">
                        <span v-for="c in product.colors" :key="c" class="badge bg-pink-50 text-pink-600">{{ c }}</span>
                    </div>

                    <div class="mt-auto flex items-center justify-between pt-3 text-xs">
                        <Link :href="route('stock.index', { product_id: product.id, status: 'all' })" class="text-gray-500 hover:text-pink-600">
                            На складе: <b class="text-gray-800">{{ product.in_stock_count }}</b><span v-if="product.ordered_count" class="text-violet-600"> · в пути {{ product.ordered_count }}</span> · продано: {{ product.sold_count }}
                        </Link>
                        <a
                            v-if="product.pinduoduo_url"
                            :href="product.pinduoduo_url"
                            target="_blank"
                            rel="noopener"
                            class="text-orange-600 hover:underline"
                        >Pinduoduo ↗</a>
                    </div>

                    <div class="mt-3 flex gap-2 border-t pt-3">
                        <Link :href="route('products.edit', product.id)" class="btn-secondary btn-sm flex-1">Изменить</Link>
                        <button class="btn-danger btn-sm" @click="destroy(product)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!products.data.length" class="card p-10 text-center text-gray-400">Товаров пока нет</div>

        <div class="mt-6"><Pagination :links="products.links" /></div>
    </AuthenticatedLayout>
</template>
