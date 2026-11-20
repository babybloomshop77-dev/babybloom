<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import MonthFilter from '@/Components/UI/MonthFilter.vue';
import Dialog from '@/Components/UI/Dialog.vue';
import Field from '@/Components/UI/Field.vue';
import { money, date, today } from '@/utils';

const props = defineProps({ movements: Array, balance: Number, totals: Object, filters: Object, categories: Object });

const filters = reactive({ month: props.filters.month, type: props.filters.type || '', search: props.filters.search || '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('cash.index'), filters, { preserveState: true, replace: true }), 300);
});

const sourceLinks = {
    expense: { label: 'Расходы', route: 'expenses.index' },
    purchase: { label: 'Закупки', route: 'purchases.index' },
    sale: { label: 'Продажи', route: 'sales.index' },
};

/* ---------- Быстрая запись ---------- */
const blank = { type: 'out', amount: '', title: '', category: 'other', moved_at: today(), note: '' };
const form = useForm({ ...blank });
const after = computed(() => {
    const amount = Number(form.amount) || 0;
    return props.balance + (form.type === 'in' ? amount : -amount);
});

function submit() {
    form.post(route('cash.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.moved_at = today();
        },
    });
}

/* ---------- Редактирование ---------- */
const editing = ref(null);
const editForm = useForm({ ...blank });

function openEdit(m) {
    editing.value = m;
    editForm.clearErrors();
    Object.assign(editForm, {
        type: m.type, amount: m.amount, title: m.title, category: m.category_key || 'other',
        moved_at: m.moved_at, note: m.note || '',
    });
}

function submitEdit() {
    editForm.put(route('cash.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

function destroy(m) {
    if (confirm(`Удалить «${m.title}» (${money(m.amount)})?`)) router.delete(route('cash.destroy', m.id), { preserveScroll: true });
}

const editable = (m) => !m.source_kind || m.source_kind === 'expense';
</script>

<template>
    <Head title="Касса" />
    <AuthenticatedLayout>
        <PageHeader title="Касса" subtitle="Сколько денег есть сейчас и история всех трат и поступлений" />

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Остаток -->
            <div class="rounded-xl bg-gradient-to-br from-pink-500 to-rose-500 p-4 text-white sm:p-5 shadow-sm">
                <div class="text-xs font-medium uppercase tracking-wide text-pink-100">Сейчас в кассе</div>
                <div class="mt-1 text-3xl font-bold sm:text-4xl tabular-nums">{{ money(balance) }}</div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-pink-100">Пришло {{ filters.month === 'all' ? 'всего' : 'за месяц' }}</div>
                        <div class="font-semibold tabular-nums">+{{ money(totals.in) }}</div>
                    </div>
                    <div>
                        <div class="text-pink-100">Потрачено {{ filters.month === 'all' ? 'всего' : 'за месяц' }}</div>
                        <div class="font-semibold tabular-nums">−{{ money(totals.out) }}</div>
                    </div>
                </div>
            </div>

            <!-- Быстрая запись -->
            <form class="card p-4 sm:p-5 lg:col-span-2" @submit.prevent="submit">
                <div class="mb-4 flex gap-1 rounded-lg bg-gray-100 p-1 text-sm font-medium sm:w-72">
                    <button
                        type="button"
                        class="flex-1 rounded-md py-1.5"
                        :class="form.type === 'out' ? 'bg-white text-red-600 shadow-sm' : 'text-gray-500'"
                        @click="form.type = 'out'"
                    >
                        − Заплатили
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md py-1.5"
                        :class="form.type === 'in' ? 'bg-white text-emerald-600 shadow-sm' : 'text-gray-500'"
                        @click="form.type = 'in'"
                    >
                        + Пополнили
                    </button>
                </div>

                <div class="grid gap-3 sm:grid-cols-6">
                    <Field label="Сумма ֏" :error="form.errors.amount" class="sm:col-span-2">
                        <input v-model="form.amount" type="number" min="1" class="input text-lg font-semibold" required placeholder="3000" />
                    </Field>
                    <Field :label="form.type === 'out' ? 'За что' : 'Откуда'" :error="form.errors.title" class="sm:col-span-4">
                        <input
                            v-model="form.title"
                            class="input"
                            required
                            :placeholder="form.type === 'out' ? 'напр. AI tool' : 'напр. пополнение'"
                        />
                    </Field>
                    <Field v-if="form.type === 'out'" label="Категория" class="sm:col-span-2">
                        <select v-model="form.category" class="input">
                            <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </Field>
                    <Field label="Дата" :error="form.errors.moved_at" class="sm:col-span-2">
                        <input v-model="form.moved_at" type="date" class="input" required />
                    </Field>
                    <Field label="Заметка" :class="form.type === 'out' ? 'sm:col-span-2' : 'sm:col-span-4'">
                        <input v-model="form.note" class="input" />
                    </Field>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-gray-500">
                        <template v-if="form.amount">
                            {{ money(balance) }}
                            <span :class="form.type === 'in' ? 'text-emerald-600' : 'text-red-600'">
                                {{ form.type === 'in' ? '+' : '−' }} {{ money(form.amount) }}
                            </span>
                            = <b :class="after < 0 ? 'text-red-600' : 'text-gray-900'">{{ money(after) }}</b>
                        </template>
                        <template v-else>Остаток после записи посчитается здесь</template>
                    </div>
                    <button class="btn-primary w-full py-2.5 sm:w-auto sm:py-2" :disabled="form.processing">Записать</button>
                </div>
            </form>
        </div>

        <!-- История -->
        <div class="mb-3 mt-8 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">История</h2>
            <div class="flex flex-wrap items-center gap-2">
                <input v-model="filters.search" type="search" class="filter-input" placeholder="Поиск…" />
                <select v-model="filters.type" class="filter-select">
                    <option value="">Все</option>
                    <option value="out">Расходы</option>
                    <option value="in">Приходы</option>
                </select>
                <MonthFilter v-model="filters.month" />
            </div>
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div v-for="m in movements" :key="m.id" class="m-card">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900">{{ m.title }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                            <span>{{ date(m.moved_at) }}</span>
                            <span v-if="m.category" class="badge bg-gray-100 text-gray-600">{{ m.category }}</span>
                            <Link v-if="m.source_kind" :href="route(sourceLinks[m.source_kind].route)" class="text-gray-400">
                                из «{{ sourceLinks[m.source_kind].label }}»
                            </Link>
                        </div>
                        <div v-if="m.note" class="text-xs text-gray-400">{{ m.note }}</div>
                    </div>
                    <div class="whitespace-nowrap text-right">
                        <div class="font-semibold tabular-nums" :class="m.type === 'in' ? 'text-emerald-600' : 'text-red-600'">
                            {{ m.type === 'in' ? '+' : '−' }}{{ money(m.amount) }}
                        </div>
                        <div class="text-xs text-gray-400">осталось {{ money(m.balance_after) }}</div>
                    </div>
                </div>
                <div v-if="editable(m)" class="mt-2 flex justify-end gap-2">
                    <button class="btn-secondary btn-sm px-4 py-1.5" @click="openEdit(m)">✎</button>
                    <button class="btn-danger btn-sm px-4 py-1.5" @click="destroy(m)">✕</button>
                </div>
            </div>
            <div v-if="!movements.length" class="m-card py-10 text-center text-gray-400">Записей нет</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Операция</th>
                        <th class="text-right">Было</th>
                        <th class="text-right">Сумма</th>
                        <th class="text-right">Осталось</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="m in movements" :key="m.id">
                        <td class="whitespace-nowrap text-gray-500">{{ date(m.moved_at) }}</td>
                        <td>
                            <div class="font-medium text-gray-900">{{ m.title }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs">
                                <span v-if="m.category" class="badge bg-gray-100 text-gray-600">{{ m.category }}</span>
                                <Link
                                    v-if="m.source_kind"
                                    :href="route(sourceLinks[m.source_kind].route)"
                                    class="text-gray-400 hover:text-pink-600"
                                >
                                    из «{{ sourceLinks[m.source_kind].label }}»
                                </Link>
                                <span v-if="m.note" class="text-gray-400">{{ m.note }}</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-right tabular-nums text-gray-400">
                            {{ money(m.balance_after + (m.type === 'in' ? -m.amount : m.amount)) }}
                        </td>
                        <td
                            class="whitespace-nowrap text-right font-semibold tabular-nums"
                            :class="m.type === 'in' ? 'text-emerald-600' : 'text-red-600'"
                        >
                            {{ m.type === 'in' ? '+' : '−' }}{{ money(m.amount) }}
                        </td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ money(m.balance_after) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <template v-if="editable(m)">
                                <button class="btn-secondary btn-sm" @click="openEdit(m)">✎</button>
                                <button class="btn-danger btn-sm ml-1" @click="destroy(m)">✕</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!movements.length">
                        <td colspan="6" class="py-10 text-center text-gray-400">Записей нет</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog :show="!!editing" title="Изменить запись" max-width="lg" @close="editing = null">
            <form id="cash-edit" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                <Field label="Сумма ֏" :error="editForm.errors.amount">
                    <input v-model="editForm.amount" type="number" min="1" class="input" required />
                </Field>
                <Field label="Дата" :error="editForm.errors.moved_at">
                    <input v-model="editForm.moved_at" type="date" class="input" required />
                </Field>
                <Field label="Описание" :error="editForm.errors.title" class="sm:col-span-2">
                    <input v-model="editForm.title" class="input" required />
                </Field>
                <Field v-if="editForm.type === 'out'" label="Категория" class="sm:col-span-2">
                    <select v-model="editForm.category" class="input">
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                </Field>
                <Field label="Заметка" class="sm:col-span-2"><input v-model="editForm.note" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="editing = null">Отмена</button>
                <button form="cash-edit" class="btn-primary" :disabled="editForm.processing">Сохранить</button>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
