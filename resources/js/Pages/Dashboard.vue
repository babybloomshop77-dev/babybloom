<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import MonthFilter from '@/Components/UI/MonthFilter.vue';
import { money, date } from '@/utils';

const props = defineProps({
    month: String,
    stats: Object,
    expensesByCategory: Object,
    recentSales: Array,
    openOrders: Array,
    categories: Object,
});

const month = ref(props.month);
watch(month, (value) => router.get(route('dashboard'), { month: value }, { preserveState: true }));
</script>

<template>
    <Head title="Главная" />
    <AuthenticatedLayout>
        <PageHeader title="Главная" subtitle="Сводка по продажам, расходам и складу">
            <MonthFilter v-model="month" />
        </PageHeader>

        <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
            <StatCard label="Продажи" :value="money(stats.revenue)" :hint="`${stats.sales_count} шт.`" tone="pink" />
            <StatCard label="Закупки" :value="money(stats.purchases)" hint="товар + доставка" />
            <StatCard label="Расходы" :value="money(stats.expenses)" hint="реклама, упаковка и др." />
            <StatCard
                label="Прибыль"
                :value="money(stats.profit)"
                hint="продажи − закупки − расходы"
                :tone="stats.profit >= 0 ? 'green' : 'red'"
            />
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-5">
            <Link :href="route('cash.index')" class="block transition hover:opacity-90">
                <StatCard label="Сейчас в кассе hoooooo" :value="money(stats.cash)" hint="история → Касса" tone="pink" />
            </Link>
            <StatCard label="На складе" :value="`${stats.stock_count} шт.`" :hint="stats.in_transit ? `+ ${stats.in_transit} шт. в пути` : null" />
            <StatCard label="Себестоимость склада" :value="money(stats.stock_cost)" />
            <StatCard label="Склад по цене продажи" :value="money(stats.stock_value)" />
            <StatCard label="Открытые заказы" :value="stats.open_orders" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="card overflow-hidden xl:col-span-2">
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h2 class="font-semibold">Последние продажи</h2>
                    <Link :href="route('sales.index')" class="text-sm text-pink-600 hover:underline">Все →</Link>
                </div>
                <table class="table">
                    <tbody>
                        <tr v-for="sale in recentSales" :key="sale.id">
                            <td class="whitespace-nowrap text-xs text-gray-400 sm:text-sm">{{ date(sale.sold_at) }}</td>
                            <td>{{ sale.product }}</td>
                            <td class="hidden text-gray-500 sm:table-cell">{{ sale.customer_name }}</td>
                            <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ money(sale.price) }}</td>
                        </tr>
                        <tr v-if="!recentSales.length">
                            <td class="py-6 text-center text-gray-400">Нет продаж за период</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="space-y-6">
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b px-4 py-3">
                        <h2 class="font-semibold">Открытые заказы</h2>
                        <Link :href="route('orders.index')" class="text-sm text-pink-600 hover:underline">Все →</Link>
                    </div>
                    <ul class="divide-y text-sm">
                        <li v-for="order in openOrders" :key="order.id" class="px-4 py-2.5">
                            <div class="flex justify-between gap-2">
                                <span class="font-medium">{{ order.customer_name }}</span>
                                <span class="tabular-nums">{{ money(order.price) }}</span>
                            </div>
                            <div class="truncate text-gray-500">{{ order.items }}</div>
                        </li>
                        <li v-if="!openOrders.length" class="px-4 py-6 text-center text-gray-400">Нет открытых заказов</li>
                    </ul>
                </div>

                <div class="card p-4">
                    <h2 class="mb-3 font-semibold">Расходы по категориям</h2>
                    <div v-for="(label, key) in categories" :key="key" class="flex justify-between py-1 text-sm">
                        <span class="text-gray-600">{{ label }}</span>
                        <span class="tabular-nums">{{ money(expensesByCategory[key] || 0) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
