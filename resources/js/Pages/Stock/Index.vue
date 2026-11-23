<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import Dialog from '@/Components/UI/Dialog.vue';
import Field from '@/Components/UI/Field.vue';
import GenderFilter from '@/Components/UI/GenderFilter.vue';
import { money, date, today, statusColors, imageFor, genderIcons } from '@/utils';

const props = defineProps({ items: Object, totals: Object, filters: Object, products: Array, purchases: Array, statuses: Object, genders: Object });

const filters = reactive({
    status: props.filters.status,
    product_id: props.filters.product_id || '',
    search: props.filters.search || '',
    gender: props.filters.gender || '',
});
let timer;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('stock.index'), filters, { preserveState: true, replace: true }), 300);
});

const tabs = { available: 'Все непроданные', ...props.statuses, all: 'Все' };

/* ---------- Добавление (сразу несколько штук) ---------- */
const showAdd = ref(false);
const addForm = useForm({
    product_id: '', purchase_id: '', buy_price: '', sell_price: '', arrived_at: today(),
    rows: [{ size: '', color: '', qty: 1 }],
});
const selectedProduct = computed(() => props.products.find((p) => p.id === Number(addForm.product_id)));
const productPurchases = computed(() =>
    props.purchases.filter((p) => !addForm.product_id || !p.product_id || p.product_id === Number(addForm.product_id)),
);

function openAdd() {
    addForm.reset();
    addForm.clearErrors();
    addForm.arrived_at = today();
    if (filters.product_id) {
        addForm.product_id = Number(filters.product_id);
        onAddProduct();
    }
    showAdd.value = true;
}

function onAddProduct() {
    const p = selectedProduct.value;
    if (!p) return;
    addForm.sell_price = p.sell_price ?? '';
    if (p.buy_price_amd !== null) addForm.buy_price = (p.buy_price_amd || 0) + (p.shipping_cost || 0);
}

function onAddPurchase() {
    const pur = props.purchases.find((p) => p.id === Number(addForm.purchase_id));
    if (pur) addForm.buy_price = Math.round((pur.price_amd + pur.shipping_cost) / Math.max(1, pur.quantity));
}

function submitAdd() {
    addForm.post(route('stock.store'), { preserveScroll: true, onSuccess: () => (showAdd.value = false) });
}

/* ---------- Редактирование ---------- */
const editing = ref(null);
const editForm = useForm({
    product_id: '', purchase_id: '', size: '', color: '', buy_price: '', sell_price: '',
    status: 'in_stock', reserved_for: '', arrived_at: '', sold_at: '', note: '',
});

function openEdit(item) {
    editing.value = item;
    editForm.clearErrors();
    Object.keys(editForm.data()).forEach((k) => (editForm[k] = item[k] ?? ''));
}

function submitEdit() {
    editForm.put(route('stock.update', editing.value.id), { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

/* ---------- Продажа ---------- */
const selling = ref(null);
const sellForm = useForm({ price: '', sold_at: today(), customer_name: '', note: '' });

function openSell(item) {
    selling.value = item;
    sellForm.reset();
    sellForm.price = item.sell_price ?? '';
    sellForm.sold_at = today();
    sellForm.customer_name = item.reserved_for ?? '';
}

function submitSell() {
    sellForm.post(route('stock.sell', selling.value.id), { preserveScroll: true, onSuccess: () => (selling.value = null) });
}

/* ---------- Массовая смена статуса ---------- */
const selected = ref([]);
const bulkDate = ref(today());
const selectable = computed(() => props.items.data.filter((i) => i.status !== 'sold').map((i) => i.id));
const allSelected = computed(() => selectable.value.length > 0 && selected.value.length === selectable.value.length);

function toggleAll() {
    selected.value = allSelected.value ? [] : [...selectable.value];
}

function bulk(status) {
    router.post(
        route('stock.bulk-status'),
        { ids: selected.value, status, date: bulkDate.value },
        { preserveScroll: true, onSuccess: () => (selected.value = []) },
    );
}

watch(() => props.items, () => (selected.value = []));

function destroy(item) {
    if (confirm('Удалить эту единицу со склада?')) router.delete(route('stock.destroy', item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Склад" />
    <AuthenticatedLayout>
        <PageHeader title="Склад" subtitle="Каждая строка — одна штука товара">
            <button class="btn-primary" @click="openAdd">+ Добавить на склад</button>
        </PageHeader>

        <!-- Телефон: итоги одной строкой -->
        <div class="card mb-3 grid grid-cols-3 divide-x p-3 text-center sm:hidden">
            <div>
                <div class="text-[11px] uppercase text-gray-500">Штук</div>
                <div class="font-semibold tabular-nums">{{ totals.count }}</div>
            </div>
            <div>
                <div class="text-[11px] uppercase text-gray-500">Себест.</div>
                <div class="font-semibold tabular-nums">{{ money(totals.cost) }}</div>
            </div>
            <div>
                <div class="text-[11px] uppercase text-gray-500">Продажа</div>
                <div class="font-semibold tabular-nums text-pink-600">{{ money(totals.value) }}</div>
            </div>
        </div>
        <div class="mb-4 hidden grid-cols-3 gap-3 sm:grid">
            <StatCard label="Штук" :value="totals.count" />
            <StatCard label="Себестоимость" :value="money(totals.cost)" />
            <StatCard label="По цене продажи" :value="money(totals.value)" tone="pink" />
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <div class="no-scrollbar -mx-3 w-[calc(100%+1.5rem)] overflow-x-auto px-3 sm:mx-0 sm:w-auto sm:px-0">
                <div class="flex w-max gap-1 rounded-lg bg-white p-1 shadow-sm ring-1 ring-gray-200">
                    <button
                        v-for="(label, key) in tabs"
                        :key="key"
                        class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm"
                        :class="filters.status === key ? 'bg-pink-500 text-white' : 'text-gray-600 hover:bg-gray-100'"
                        @click="filters.status = key"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>
            <GenderFilter v-model="filters.gender" />
            <select v-model="filters.product_id" class="filter-select">
                <option value="">Все товары</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model="filters.search" type="search" class="input min-w-0 flex-1 sm:w-48 sm:flex-none" placeholder="Размер, цвет…" />
            <label class="flex w-full items-center gap-2 px-1 py-1 text-sm text-gray-600 md:hidden">
                <input type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" :checked="allSelected" @change="toggleAll" />
                Выбрать все
            </label>
        </div>

        <div
            v-if="selected.length"
            class="sticky top-16 z-10 lg:top-2 mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm text-white shadow-lg"
        >
            <span class="font-medium">Выбрано: {{ selected.length }} шт.</span>
            <span class="ml-2 text-gray-400">дата</span>
            <input v-model="bulkDate" type="date" class="rounded-md border-0 bg-gray-800 py-1 text-sm text-white" />
            <button class="btn btn-sm bg-emerald-500 text-white hover:bg-emerald-600" @click="bulk('in_stock')">✅ Доставлено / в наличии</button>
            <button class="btn btn-sm bg-violet-500 text-white hover:bg-violet-600" @click="bulk('ordered')">🚚 В пути</button>
            <button class="btn btn-sm bg-amber-500 text-white hover:bg-amber-600" @click="bulk('processing')">В обработке</button>
            <button class="btn btn-sm bg-blue-500 text-white hover:bg-blue-600" @click="bulk('reserved')">Бронь</button>
            <button class="ml-auto text-gray-400 hover:text-white" @click="selected = []">Отмена</button>
        </div>

        <!-- Телефон: карточки -->
        <div class="space-y-2 md:hidden">
            <div
                v-for="item in items.data"
                :key="item.id"
                class="m-card"
                :class="{ 'opacity-60': item.status === 'sold', '!border-pink-300 !bg-pink-50/60': selected.includes(item.id) }"
            >
                <div class="flex gap-3">
                    <label class="relative shrink-0">
                        <img v-if="item.product?.images?.length" :src="imageFor(item.product, item.color)" class="h-16 w-16 rounded-lg object-cover" />
                        <div v-else class="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-100 text-2xl">🧸</div>
                        <input
                            v-if="item.status !== 'sold'"
                            v-model="selected"
                            type="checkbox"
                            :value="item.id"
                            class="absolute -left-1.5 -top-1.5 h-5 w-5 rounded border-gray-300 bg-white text-pink-500 shadow focus:ring-pink-400"
                        />
                    </label>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <Link :href="route('products.edit', item.product_id)" class="font-medium leading-snug text-gray-900 underline decoration-gray-300 underline-offset-2"><span v-if="item.product?.gender" class="no-underline">{{ genderIcons[item.product.gender] }} </span>{{ item.product?.name }}</Link>
                            <div class="whitespace-nowrap font-semibold tabular-nums">{{ money(item.sell_price) }}</div>
                        </div>
                        <div class="mt-0.5 text-sm text-gray-600">{{ item.size || '—' }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                            <span class="badge" :class="statusColors[item.status]">{{ statuses[item.status] }}</span>
                            <span v-if="item.color" class="badge bg-gray-100 text-gray-600">{{ item.color }}</span>
                            <span v-if="item.reserved_for" class="text-gray-500">{{ item.reserved_for }}</span>
                            <span class="text-gray-400">себест. {{ money(item.buy_price) }}</span>
                        </div>
                        <div v-if="item.note" class="mt-0.5 text-xs text-gray-400">{{ item.note }}</div>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button v-if="item.status !== 'sold' && !item.order_id" class="btn-primary flex-1" @click="openSell(item)">Продать</button>
                    <Link v-else-if="item.order_id && item.status !== 'sold'" :href="route('orders.index', { status: 'all' })" class="flex-1 self-center text-xs text-pink-600">📦 в заказе → Заказы</Link>
                    <div v-else class="flex-1 self-center text-xs text-gray-400">продан {{ date(item.sold_at) }}</div>
                    <button class="btn-secondary px-4" @click="openEdit(item)">✎</button>
                    <button class="btn-danger px-4" @click="destroy(item)">✕</button>
                </div>
            </div>
            <div v-if="!items.data.length" class="m-card py-10 text-center text-gray-400">Ничего не найдено</div>
        </div>

        <div class="card hidden overflow-x-auto md:block">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" :checked="allSelected" @change="toggleAll" />
                        </th>
                        <th></th>
                        <th>Товар</th>
                        <th>Размер</th>
                        <th>Цвет</th>
                        <th class="text-right">Себест.</th>
                        <th class="text-right">Цена</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr
                        v-for="item in items.data"
                        :key="item.id"
                        :class="{ 'opacity-60': item.status === 'sold', 'bg-pink-50/60': selected.includes(item.id) }"
                    >
                        <td>
                            <input
                                v-if="item.status !== 'sold'"
                                v-model="selected"
                                type="checkbox"
                                :value="item.id"
                                class="rounded text-pink-500 focus:ring-pink-400"
                            />
                        </td>
                        <td class="w-12">
                            <img
                                v-if="item.product?.images?.length"
                                :src="imageFor(item.product, item.color)"
                                class="h-10 w-10 rounded-md object-cover"
                            />
                            <div v-else class="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100">🧸</div>
                        </td>
                        <td class="font-medium text-gray-900">
                            <Link :href="route('products.edit', item.product_id)" class="hover:text-pink-600"><span v-if="item.product?.gender">{{ genderIcons[item.product.gender] }} </span>{{ item.product?.name }}</Link>
                            <div v-if="item.note" class="text-xs font-normal text-gray-400">{{ item.note }}</div>
                        </td>
                        <td class="max-w-[16rem] text-gray-600">{{ item.size || '—' }}</td>
                        <td>{{ item.color || '—' }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums text-gray-500">{{ money(item.buy_price) }}</td>
                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ money(item.sell_price) }}</td>
                        <td>
                            <span class="badge" :class="statusColors[item.status]">{{ statuses[item.status] }}</span>
                            <div v-if="item.reserved_for" class="text-xs text-gray-500">{{ item.reserved_for }}</div>
                        </td>
                        <td class="whitespace-nowrap text-xs text-gray-500">
                            {{ item.status === 'sold' ? date(item.sold_at) : date(item.arrived_at) }}
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <button v-if="item.status !== 'sold' && !item.order_id" class="btn-primary btn-sm" @click="openSell(item)">Продать</button>
                            <span v-else-if="item.order_id && item.status !== 'sold'" class="mr-1 text-xs text-pink-600">📦 в заказе</span>
                            <button class="btn-secondary btn-sm ml-1" @click="openEdit(item)">✎</button>
                            <button class="btn-danger btn-sm ml-1" @click="destroy(item)">✕</button>
                        </td>
                    </tr>
                    <tr v-if="!items.data.length">
                        <td colspan="10" class="py-10 text-center text-gray-400">Ничего не найдено</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="items.links" /></div>

        <!-- Добавить -->
        <Dialog :show="showAdd" title="Добавить на склад" max-width="3xl" @close="showAdd = false">
            <form id="add-form" class="space-y-4" @submit.prevent="submitAdd">
                <div class="grid gap-4 sm:grid-cols-2">
                    <Field label="Товар" :error="addForm.errors.product_id">
                        <select v-model="addForm.product_id" class="input" required @change="onAddProduct">
                            <option value="">— выберите —</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </Field>
                    <Field label="Закупка (необязательно)" :error="addForm.errors.purchase_id">
                        <select v-model="addForm.purchase_id" class="input" @change="onAddPurchase">
                            <option value="">— нет —</option>
                            <option v-for="p in productPurchases" :key="p.id" :value="p.id">
                                {{ date(p.purchased_at) }} · {{ p.title }}
                            </option>
                        </select>
                    </Field>
                    <Field label="Себестоимость 1 шт ֏" :error="addForm.errors.buy_price">
                        <input v-model="addForm.buy_price" type="number" min="0" class="input" />
                    </Field>
                    <Field label="Цена продажи ֏" :error="addForm.errors.sell_price">
                        <input v-model="addForm.sell_price" type="number" min="0" class="input" />
                    </Field>
                    <Field label="Дата поступления" :error="addForm.errors.arrived_at">
                        <input v-model="addForm.arrived_at" type="date" class="input" />
                    </Field>
                </div>

                <div>
                    <div class="label">Размеры / цвета / количество</div>
                    <datalist id="sizes-list">
                        <option v-for="s in selectedProduct?.sizes ?? []" :key="s" :value="s" />
                    </datalist>
                    <datalist id="colors-list">
                        <option v-for="c in selectedProduct?.colors ?? []" :key="c" :value="c" />
                    </datalist>
                    <div v-for="(row, i) in addForm.rows" :key="i" class="mb-3 grid grid-cols-[1fr_4.5rem_3rem] gap-2 sm:mb-2 sm:flex">
                        <input v-model="row.size" list="sizes-list" class="input col-span-3 sm:flex-[2]" placeholder="Размер" />
                        <input v-model="row.color" list="colors-list" class="input min-w-0 sm:flex-1" placeholder="Цвет" />
                        <input v-model="row.qty" type="number" min="1" max="100" class="input sm:w-20" />
                        <button type="button" class="btn-danger" :disabled="addForm.rows.length === 1" @click="addForm.rows.splice(i, 1)">✕</button>
                    </div>
                    <button type="button" class="btn-secondary btn-sm" @click="addForm.rows.push({ size: '', color: '', qty: 1 })">
                        + ещё строка
                    </button>
                    <p v-if="addForm.errors.rows" class="mt-1 text-xs text-red-600">{{ addForm.errors.rows }}</p>
                </div>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="showAdd = false">Отмена</button>
                <button form="add-form" class="btn-primary" :disabled="addForm.processing">
                    Добавить {{ addForm.rows.reduce((s, r) => s + (Number(r.qty) || 0), 0) }} шт.
                </button>
            </template>
        </Dialog>

        <!-- Изменить -->
        <Dialog :show="!!editing" title="Изменить" @close="editing = null">
            <form id="edit-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitEdit">
                <Field label="Товар" :error="editForm.errors.product_id">
                    <select v-model="editForm.product_id" class="input">
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </Field>
                <Field label="Закупка">
                    <select v-model="editForm.purchase_id" class="input">
                        <option value="">— нет —</option>
                        <option v-for="p in purchases" :key="p.id" :value="p.id">{{ date(p.purchased_at) }} · {{ p.title }}</option>
                    </select>
                </Field>
                <Field label="Размер" :error="editForm.errors.size"><input v-model="editForm.size" class="input" /></Field>
                <Field label="Цвет" :error="editForm.errors.color"><input v-model="editForm.color" class="input" /></Field>
                <Field label="Себестоимость ֏"><input v-model="editForm.buy_price" type="number" min="0" class="input" /></Field>
                <Field label="Цена продажи ֏"><input v-model="editForm.sell_price" type="number" min="0" class="input" /></Field>
                <Field label="Статус" :error="editForm.errors.status">
                    <select v-model="editForm.status" class="input">
                        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                    </select>
                </Field>
                <Field label="Для кого (бронь)"><input v-model="editForm.reserved_for" class="input" placeholder="напр. Goqi mot" /></Field>
                <Field label="Дата поступления"><input v-model="editForm.arrived_at" type="date" class="input" /></Field>
                <Field label="Дата продажи"><input v-model="editForm.sold_at" type="date" class="input" /></Field>
                <Field label="Заметка" class="sm:col-span-2"><input v-model="editForm.note" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="editing = null">Отмена</button>
                <button form="edit-form" class="btn-primary" :disabled="editForm.processing">Сохранить</button>
            </template>
        </Dialog>

        <!-- Продать -->
        <Dialog :show="!!selling" title="Продажа" max-width="md" @close="selling = null">
            <div v-if="selling" class="mb-4 flex gap-3 rounded-lg bg-gray-50 p-3 text-sm">
                <img v-if="imageFor(selling.product, selling.color)" :src="imageFor(selling.product, selling.color)" class="h-16 w-16 shrink-0 rounded-lg object-cover" />
                <div>
                    <b>{{ selling.product?.name }}</b> · {{ selling.size }} · {{ selling.color }}
                    <div class="text-gray-500">Себестоимость: {{ money(selling.buy_price) }}</div>
                </div>
            </div>
            <form id="sell-form" class="space-y-4" @submit.prevent="submitSell">
                <Field label="Цена продажи ֏" :error="sellForm.errors.price">
                    <input v-model="sellForm.price" type="number" min="0" class="input" required />
                </Field>
                <Field label="Дата" :error="sellForm.errors.sold_at">
                    <input v-model="sellForm.sold_at" type="date" class="input" required />
                </Field>
                <Field label="Покупатель"><input v-model="sellForm.customer_name" class="input" /></Field>
                <Field label="Заметка"><input v-model="sellForm.note" class="input" /></Field>
            </form>
            <template #footer>
                <button class="btn-secondary" @click="selling = null">Отмена</button>
                <button form="sell-form" class="btn-primary" :disabled="sellForm.processing">Продано ✓</button>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
