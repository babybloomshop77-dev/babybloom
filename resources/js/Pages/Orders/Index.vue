<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import Dialog from '@/Components/UI/Dialog.vue';
import Field from '@/Components/UI/Field.vue';
import StockPicker from '@/Components/UI/StockPicker.vue';
import { money, date, today } from '@/utils';

const props = defineProps({ orders: Object, stock: Array, filters: Object, channels: Object });

const filters = reactive({ status: props.filters.status, search: props.filters.search || '' });
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('orders.index'), filters, { preserveState: true, replace: true }), 300);
});
const tabs = { open: 'Открытые', done: 'Выполненные', all: 'Все' };

const FIELDS = ['items', 'customer_name', 'channel', 'address', 'phone', 'price', 'ordered_at', 'is_done', 'notes'];

const editing = ref(null);
const show = ref(false);
const form = useForm({
    items: '', customer_name: '', channel: 'instagram', address: '', phone: '',
    price: '', ordered_at: today(), is_done: false, notes: '',
    stock_item_ids: [],
});

/* ---------- Товары со склада ---------- */
// Варианты для выбора: товары этого заказа (при редактировании) + свободные на складе
const pickerOptions = computed(() => {
    const own = editing.value?.stock_items ?? [];
    const ownIds = new Set(own.map((i) => i.id));
    return [...own, ...props.stock.filter((i) => !ownIds.has(i.id))];
});
const selectedItems = computed(() => {
    const byId = new Map(pickerOptions.value.map((i) => [i.id, i]));
    return form.stock_item_ids.map((id) => byId.get(id)).filter(Boolean);
});
const itemsTotal = computed(() => selectedItems.value.reduce((s, i) => s + (Number(i.sell_price) || 0), 0));
const itemsText = computed(() => {
    const text = selectedItems.value.map((i) => [i.product?.name, i.size, i.color].filter(Boolean).join(' ')).join(', ');
    return text.length > 250 ? `${text.slice(0, 249)}…` : text;
});

// Сумма и «что заказали» заполняются сами, пока вы их не изменили вручную
const priceTouched = ref(false);
const itemsTouched = ref(false);

watch(
    () => [...form.stock_item_ids],
    () => {
        if (!priceTouched.value) form.price = selectedItems.value.length ? itemsTotal.value : '';
        if (!itemsTouched.value) form.items = itemsText.value;
    },
);

function applyItemsTotal() {
    form.price = itemsTotal.value;
    priceTouched.value = false;
}

/* ---------- Модалка ---------- */
function open(order = null) {
    editing.value = order;
    form.clearErrors();
    if (order) {
        FIELDS.forEach((k) => (form[k] = order[k] ?? ''));
        const own = order.stock_items ?? [];
        const ownTotal = own.reduce((s, i) => s + (Number(i.sell_price) || 0), 0);
        itemsTouched.value = true;
        priceTouched.value = !own.length || Number(order.price) !== ownTotal;
        form.stock_item_ids = own.map((i) => i.id);
    } else {
        itemsTouched.value = false;
        priceTouched.value = false;
        form.reset();
        form.stock_item_ids = [];
        form.ordered_at = today();
    }
    show.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (show.value = false) };
    editing.value ? form.put(route('orders.update', editing.value.id), options) : form.post(route('orders.store'), options);
}

const toggle = (order) => router.patch(route('orders.toggle', order.id), {}, { preserveScroll: true });
function destroy(order) {
    const extra = order.stock_items?.length ? `\nТовары (${order.stock_items.length} шт.) вернутся на склад.` : '';
    if (confirm(`Удалить заказ ${order.customer_name}?${extra}`)) router.delete(route('orders.destroy', order.id), { preserveScroll: true });
}

const itemLabel = (s) => [s.product?.name, s.size, s.color].filter(Boolean).join(' · ');
</script>

<template>
    <Head title="Заказы" />
    <AuthenticatedLayout>
        <PageHeader title="Заказы клиентов">
            <button class="btn-primary" @click="open()">+ Заказ</button>
        </PageHeader>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="flex w-full gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-200 sm:w-auto">
                <button
                    v-for="(label, key) in tabs"
                    :key="key"
                    class="flex-1 whitespace-nowrap rounded-md px-3 py-1.5 text-sm sm:flex-none"
                    :class="filters.status === key ? 'bg-pink-500 text-white' : 'text-gray-600 hover:bg-gray-100'"
                    @click="filters.status = key"
                >
                    {{ label }}
                </button>
            </div>
            <input v-model="filters.search" type="search" class="filter-input" placeholder="Имя, товар, телефон…" />
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div v-for="order in orders.data" :key="order.id" class="m-card" :class="{ 'opacity-60': order.is_done }">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="font-medium text-gray-900" :class="{ 'line-through': order.is_done }">{{ order.items }}</div>
                        <div class="mt-0.5 text-sm text-gray-600">
                            {{ order.customer_name }}
                            <span v-if="order.channel" class="badge ml-1 bg-gray-100 text-gray-500">{{ channels[order.channel] }}</span>
                        </div>
                    </div>
                    <div class="whitespace-nowrap text-right">
                        <div class="font-semibold tabular-nums">{{ money(order.price) }}</div>
                        <div v-if="order.sale" class="text-xs text-emerald-600">в кассе ✓</div>
                    </div>
                </div>
                <div v-if="order.stock_items?.length" class="mt-1.5 flex flex-wrap gap-1">
                    <span v-for="s in order.stock_items" :key="s.id" class="badge bg-pink-50 font-normal text-pink-700">📦 {{ itemLabel(s) }}</span>
                </div>
                <div v-if="order.address" class="mt-1 text-sm text-gray-500">📍 {{ order.address }}</div>
                <div class="mt-1 flex items-center justify-between text-sm">
                    <a v-if="order.phone" :href="`tel:${order.phone}`" class="font-medium text-pink-600">📞 {{ order.phone }}</a>
                    <span class="ml-auto text-xs text-gray-400">{{ date(order.ordered_at) }}</span>
                </div>
                <div class="mt-3 flex gap-2">
                    <button
                        class="btn flex-1"
                        :class="order.is_done ? 'border border-gray-300 bg-white text-gray-600' : 'bg-emerald-500 text-white'"
                        @click="toggle(order)"
                    >
                        {{ order.is_done ? '↺ Не выполнен' : '✓ Выполнен' }}
                    </button>
                    <button class="btn-secondary px-4" @click="open(order)">✎</button>
                    <button class="btn-danger px-4" @click="destroy(order)">✕</button>
                </div>
            </div>
            <div v-if="!orders.data.length" class="m-card py-10 text-center text-gray-400">Заказов нет</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">✓</th>
                        <th>Дата</th>
                        <th>Что заказали</th>
                        <th>Клиент</th>
                        <th>Адрес</th>
                        <th>Телефон</th>
                        <th class="text-right">Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="order in orders.data" :key="order.id" :class="{ 'opacity-50': order.is_done }">
                        <td>
                            <input
                                type="checkbox"
                                :checked="order.is_done"
                                class="h-5 w-5 rounded text-pink-500 focus:ring-pink-400"
                                @change="toggle(order)"
                            />
                        </td>
                        <td class="whitespace-nowrap text-gray-500">{{ date(order.ordered_at) }}</td>
                        <td class="max-w-md">
                            <div class="font-medium text-gray-900" :class="{ 'line-through': order.is_done }">{{ order.items }}</div>
                            <div v-if="order.stock_items?.length" class="mt-1 flex flex-wrap gap-1">
                                <span v-for="s in order.stock_items" :key="s.id" class="badge bg-pink-50 font-normal text-pink-700">📦 {{ itemLabel(s) }}</span>
                            </div>
                        </td>
                        <td>
                            {{ order.customer_name }}
                            <span v-if="order.channel" class="badge ml-1 bg-gray-100 text-gray-500">{{ channels[order.channel] }}</span>
                        </td>
                        <td class="text-gray-600">{{ order.address }}</td>
                        <td class="whitespace-nowrap">
                            <a v-if="order.phone" :href="`tel:${order.phone}`" class="text-pink-600 hover:underline">{{ order.phone }}</a>
                        </td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">
                            {{ money(order.price) }}
                            <div v-if="order.sale" class="text-xs font-normal text-emerald-600">в кассе ✓</div>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button class="btn-secondary btn-sm" @click="open(order)">✎</button>
                            <button class="btn-danger btn-sm ml-1" @click="destroy(order)">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td colspan="8" class="py-10 text-center text-gray-400">Заказов нет</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="orders.links" /></div>

        <Dialog :show="show" :title="editing ? 'Изменить заказ' : 'Новый заказ'" max-width="3xl" @close="show = false">
            <form id="order-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                <Field label="Товары со склада" :error="form.errors.stock_item_ids" class="sm:col-span-2">
                    <StockPicker v-model="form.stock_item_ids" :options="pickerOptions" />
                </Field>
                <Field label="Что заказали" :error="form.errors.items" class="sm:col-span-2">
                    <input
                        v-model="form.items"
                        class="input"
                        required
                        placeholder="заполнится по выбранным товарам, можно написать вручную"
                        @input="itemsTouched = true"
                    />
                </Field>
                <Field label="Имя клиента" :error="form.errors.customer_name">
                    <input v-model="form.customer_name" class="input" required />
                </Field>
                <Field label="Откуда" :error="form.errors.channel">
                    <select v-model="form.channel" class="input">
                        <option value="">—</option>
                        <option v-for="(label, key) in channels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </Field>
                <Field label="Адрес" :error="form.errors.address" class="sm:col-span-2">
                    <input v-model="form.address" class="input" />
                </Field>
                <Field label="Телефон" :error="form.errors.phone"><input v-model="form.phone" class="input" placeholder="098-00-00-00" /></Field>
                <Field label="Сумма ֏" :error="form.errors.price">
                    <input v-model="form.price" type="number" min="0" class="input" required @input="priceTouched = true" />
                    <span v-if="selectedItems.length" class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                        По товарам: <b class="tabular-nums text-gray-700">{{ money(itemsTotal) }}</b>
                        <button
                            v-if="Number(form.price) !== itemsTotal"
                            type="button"
                            class="text-pink-600 hover:underline"
                            @click="applyItemsTotal"
                        >
                            ↺ подставить
                        </button>
                        <span v-if="Number(form.price) !== itemsTotal && form.price !== ''" class="tabular-nums">
                            ({{ Number(form.price) < itemsTotal ? 'скидка' : 'наценка' }} {{ money(Math.abs(Number(form.price) - itemsTotal)) }})
                        </span>
                    </span>
                </Field>
                <Field label="Дата" :error="form.errors.ordered_at"><input v-model="form.ordered_at" type="date" class="input" /></Field>
                <label class="flex items-center gap-2 self-end pb-2 text-sm">
                    <input v-model="form.is_done" type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" /> Выполнен
                    <span class="text-xs text-gray-400">(сумма в кассу, товары станут проданными)</span>
                </label>
                <Field label="Заметки" class="sm:col-span-2"><textarea v-model="form.notes" rows="2" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="show = false">Отмена</button>
                <button form="order-form" class="btn-primary" :disabled="form.processing">Сохранить</button>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
