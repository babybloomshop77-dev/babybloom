<script setup>
import { reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import MonthFilter from '@/Components/UI/MonthFilter.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Dialog from '@/Components/UI/Dialog.vue';
import Field from '@/Components/UI/Field.vue';
import { money, date, today } from '@/utils';

const props = defineProps({ sales: Object, totals: Object, filters: Object });

const filters = reactive({ month: props.filters.month, search: props.filters.search || '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('sales.index'), filters, { preserveState: true, replace: true }), 300);
});

const editing = ref(null);
const show = ref(false);
const form = useForm({ product: '', price: '', cost: '', sold_at: today(), customer_name: '', note: '' });

function open(item = null) {
    editing.value = item;
    form.clearErrors();
    if (item) {
        Object.keys(form.data()).forEach((k) => (form[k] = item[k] ?? ''));
    } else {
        form.reset();
        form.sold_at = today();
    }
    show.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };
    editing.value ? form.put(route('sales.update', editing.value.id), options) : form.post(route('sales.store'), options);
}

function destroy(sale) {
    const extra = (sale.stock_item_id ? '\nТовар вернётся на склад.' : '') + (sale.order_id ? '\nЗаказ снова станет невыполненным.' : '');
    if (confirm(`Удалить продажу «${sale.product}»?${extra}`)) router.delete(route('sales.destroy', sale.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Продажи" />
    <AuthenticatedLayout>
        <PageHeader title="Продажи" subtitle="Продать товар со склада можно на странице «Склад» — он попадёт сюда автоматически">
            <MonthFilter v-model="filters.month" />
            <button class="btn-primary" @click="open()">+ Продажа</button>
        </PageHeader>

        <div class="mb-4 grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
            <StatCard label="Продаж" :value="totals.count" />
            <StatCard label="Выручка" :value="money(totals.revenue)" tone="pink" />
            <StatCard label="Себестоимость (известная)" :value="money(totals.cost)" />
            <StatCard label="Средний чек" :value="money(totals.count ? Math.round(totals.revenue / totals.count) : 0)" />
        </div>

        <div class="mb-3">
            <input v-model="filters.search" type="search" class="filter-input" placeholder="Товар или покупатель…" />
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div v-for="sale in sales.data" :key="sale.id" class="m-card">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900">{{ sale.product }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                            <span>{{ date(sale.sold_at) }}</span>
                            <span v-if="sale.customer_name">· {{ sale.customer_name }}</span>
                            <span v-if="sale.stock_item_id" class="badge bg-green-50 text-green-600">склад</span>
                            <span v-if="sale.order_id" class="badge bg-pink-50 text-pink-600">заказ</span>
                        </div>
                        <div v-if="sale.note" class="text-xs text-gray-400">{{ sale.note }}</div>
                    </div>
                    <div class="whitespace-nowrap text-right">
                        <div class="font-semibold tabular-nums">{{ money(sale.price) }}</div>
                        <div v-if="sale.cost !== null && sale.price !== null" class="text-xs text-emerald-600">
                            +{{ money(sale.price - sale.cost) }}
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex justify-end gap-2">
                    <button class="btn-secondary btn-sm px-4 py-1.5" @click="open(sale)">✎</button>
                    <button class="btn-danger btn-sm px-4 py-1.5" @click="destroy(sale)">✕</button>
                </div>
            </div>
            <div v-if="!sales.data.length" class="m-card py-10 text-center text-gray-400">Нет продаж за период</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Товар</th>
                        <th>Покупатель</th>
                        <th class="text-right">Цена</th>
                        <th class="text-right">Себест.</th>
                        <th class="text-right">Прибыль</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="sale in sales.data" :key="sale.id">
                        <td class="whitespace-nowrap text-gray-500">{{ date(sale.sold_at) }}</td>
                        <td class="font-medium text-gray-900">
                            {{ sale.product }}
                            <span v-if="sale.stock_item_id" class="badge ml-1 bg-green-50 text-green-600">склад</span>
                            <span v-if="sale.order_id" class="badge ml-1 bg-pink-50 text-pink-600">заказ</span>
                            <div v-if="sale.note" class="text-xs font-normal text-gray-400">{{ sale.note }}</div>
                        </td>
                        <td class="text-gray-600">{{ sale.customer_name }}</td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ money(sale.price) }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums text-gray-500">{{ money(sale.cost) }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums text-emerald-600">
                            {{ sale.cost !== null && sale.price !== null ? money(sale.price - sale.cost) : '—' }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button class="btn-secondary btn-sm" @click="open(sale)">✎</button>
                            <button class="btn-danger btn-sm ml-1" @click="destroy(sale)">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!sales.data.length">
                        <td colspan="7" class="py-10 text-center text-gray-400">Нет продаж за период</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="sales.links" /></div>

        <Dialog :show="show" :title="editing ? 'Изменить продажу' : 'Новая продажа'" @close="show = false">
            <form id="sale-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <Field label="Товар" :error="form.errors.product" class="sm:col-span-2">
                    <input v-model="form.product" class="input" required />
                </Field>
                <Field label="Цена ֏" :error="form.errors.price"><input v-model="form.price" type="number" min="0" class="input" /></Field>
                <Field label="Себестоимость ֏" :error="form.errors.cost"><input v-model="form.cost" type="number" min="0" class="input" /></Field>
                <Field label="Дата" :error="form.errors.sold_at"><input v-model="form.sold_at" type="date" class="input" required /></Field>
                <Field label="Покупатель" :error="form.errors.customer_name"><input v-model="form.customer_name" class="input" /></Field>
                <Field label="Заметка" class="sm:col-span-2"><input v-model="form.note" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="show = false">Отмена</button>
                <button form="sale-form" class="btn-primary" :disabled="form.processing">Сохранить</button>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
