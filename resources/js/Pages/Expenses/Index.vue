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

const props = defineProps({ expenses: Object, total: Number, byCategory: Object, filters: Object, categories: Object });

const filters = reactive({ month: props.filters.month, category: props.filters.category || '', search: props.filters.search || '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('expenses.index'), filters, { preserveState: true, replace: true }), 300);
});

const editing = ref(null);
const show = ref(false);
const form = useForm({ title: '', category: 'other', amount: '', spent_at: today(), note: '' });

function open(item = null) {
    editing.value = item;
    form.clearErrors();
    if (item) {
        Object.keys(form.data()).forEach((k) => (form[k] = item[k] ?? ''));
    } else {
        form.reset();
        form.spent_at = today();
    }
    show.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };
    editing.value ? form.put(route('expenses.update', editing.value.id), options) : form.post(route('expenses.store'), options);
}

function destroy(item) {
    if (confirm(`Удалить расход «${item.title}»?`)) router.delete(route('expenses.destroy', item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Расходы" />
    <AuthenticatedLayout>
        <PageHeader title="Расходы" subtitle="Реклама, доставка, упаковка и прочие траты. Закупки товара — на странице «Закупки»">
            <MonthFilter v-model="filters.month" />
            <button class="btn-primary" @click="open()">+ Расход</button>
        </PageHeader>

        <div class="mb-4 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-3 xl:grid-cols-6">
            <StatCard label="Всего" :value="money(total)" tone="pink" />
            <StatCard v-for="(label, key) in categories" :key="key" :label="label" :value="money(byCategory[key] || 0)" />
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            <input v-model="filters.search" type="search" class="filter-input" placeholder="Поиск…" />
            <select v-model="filters.category" class="filter-select">
                <option value="">Все категории</option>
                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div v-for="item in expenses.data" :key="item.id" class="m-card flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-gray-900">{{ item.title }}</div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                        <span>{{ date(item.spent_at) }}</span>
                        <span class="badge bg-gray-100 text-gray-600">{{ categories[item.category] }}</span>
                    </div>
                    <div v-if="item.note" class="text-xs text-gray-400">{{ item.note }}</div>
                </div>
                <div class="text-right">
                    <div class="whitespace-nowrap font-semibold tabular-nums text-red-600">−{{ money(item.amount) }}</div>
                    <div class="mt-1 flex justify-end gap-1">
                        <button class="btn-secondary btn-sm px-3 py-1.5" @click="open(item)">✎</button>
                        <button class="btn-danger btn-sm px-3 py-1.5" @click="destroy(item)">✕</button>
                    </div>
                </div>
            </div>
            <div v-if="!expenses.data.length" class="m-card py-10 text-center text-gray-400">Расходов нет за период</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>На что</th>
                        <th>Категория</th>
                        <th class="text-right">Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="item in expenses.data" :key="item.id">
                        <td class="whitespace-nowrap text-gray-500">{{ date(item.spent_at) }}</td>
                        <td class="font-medium text-gray-900">
                            {{ item.title }}
                            <div v-if="item.note" class="text-xs font-normal text-gray-400">{{ item.note }}</div>
                        </td>
                        <td><span class="badge bg-gray-100 text-gray-600">{{ categories[item.category] }}</span></td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ money(item.amount) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <button class="btn-secondary btn-sm" @click="open(item)">✎</button>
                            <button class="btn-danger btn-sm ml-1" @click="destroy(item)">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!expenses.data.length">
                        <td colspan="5" class="py-10 text-center text-gray-400">Расходов нет за период</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="expenses.links" /></div>

        <Dialog :show="show" :title="editing ? 'Изменить расход' : 'Новый расход'" max-width="lg" @close="show = false">
            <form id="expense-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <Field label="На что" :error="form.errors.title" class="sm:col-span-2">
                    <input v-model="form.title" class="input" required placeholder="напр. Реклама в Instagram" />
                </Field>
                <Field label="Категория" :error="form.errors.category">
                    <select v-model="form.category" class="input">
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                </Field>
                <Field label="Сумма ֏" :error="form.errors.amount"><input v-model="form.amount" type="number" min="0" class="input" required /></Field>
                <Field label="Дата" :error="form.errors.spent_at"><input v-model="form.spent_at" type="date" class="input" required /></Field>
                <Field label="Заметка" class="sm:col-span-2"><textarea v-model="form.note" rows="2" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="show = false">Отмена</button>
                <button form="expense-form" class="btn-primary" :disabled="form.processing">Сохранить</button>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
