<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import { money, today, genderIcons } from '@/utils';

const props = defineProps({
    purchase: Object,
    products: Array,
    sources: Object,
    statuses: Object,
    lastRate: [Number, String],
    backUrl: String,
    genders: Object,
});
const p = props.purchase;
const isLegacy = !!p && !p.items?.length;

let keySeq = 0;
const previews = reactive({});

const newVariant = () => ({ size: '', color: '', qty: 1 });
const newLine = () => ({
    key: ++keySeq,
    id: null,
    mode: 'new',
    product_id: '',
    new_product: { name: '', gender: '', description: '', pinduoduo_url: '', images: [] },
    price_yuan: '',
    price_amd: '',
    sell_price: '',
    variants: [newVariant()],
    existing: [],
    product: null,
    delete: false,
});

const form = useForm({
    title: p?.title ?? '',
    source: p?.source ?? 'pinduoduo',
    status: p?.status ?? 'ordered',
    url: p?.url ?? '',
    yuan_rate: p?.yuan_rate ?? props.lastRate ?? '',
    shipping_cost: p?.shipping_cost ?? '',
    shipping_paid_at: p?.shipping_paid_at ?? '',
    goods_paid_before: p?.goods_paid_before ?? false,
    purchased_at: p?.purchased_at ?? today(),
    delivered_at: p?.delivered_at ?? '',
    notes: p?.notes ?? '',
    back: props.backUrl, // куда вернуться после сохранения
    quantity: p?.quantity ?? 1,
    price_amd: p?.price_amd ?? '',
    price_yuan: p?.price_yuan ?? '',
    lines: p
        ? (p.items ?? []).map((item) => ({
              ...newLine(),
              id: item.id,
              mode: 'existing',
              product_id: item.product_id,
              product: item.product,
              price_yuan: item.price_yuan ?? '',
              price_amd: item.price_amd,
              sell_price: item.sell_price ?? '',
              variants: [],
              existing: item.variants ?? [],
          }))
        : [newLine()],
});

const productById = (id) => props.products.find((x) => x.id === Number(id));

/* ---------- Расчёты ---------- */
const linePieces = (line) =>
    line.delete ? 0 : line.existing.reduce((s, v) => s + v.qty, 0) + line.variants.reduce((s, v) => s + (Number(v.qty) || 0), 0);
const totalPieces = computed(() => form.lines.reduce((s, l) => s + linePieces(l), 0));
const goodsTotal = computed(() => form.lines.reduce((s, l) => s + linePieces(l) * (Number(l.price_amd) || 0), 0));
const yuanTotal = computed(() => form.lines.reduce((s, l) => s + linePieces(l) * (Number(l.price_yuan) || 0), 0));
const share = computed(() => (totalPieces.value ? Math.round((Number(form.shipping_cost) || 0) / totalPieces.value) : 0));
const lineCost = (line) => (Number(line.price_amd) || 0) + share.value;
const goodsSum = computed(() => (isLegacy ? Number(form.price_amd) || 0 : goodsTotal.value));
const total = computed(() => goodsSum.value + (Number(form.shipping_cost) || 0));
// Старые закупки (до кассы) кассу не меняют
const affectsCash = !p || p.cash_tracked;
const fromCash = computed(() => (form.goods_paid_before ? 0 : goodsSum.value) + (Number(form.shipping_cost) || 0));

/* ---------- Действия со строками ---------- */
function onYuan(line) {
    const rate = Number(form.yuan_rate);
    if (rate && line.price_yuan !== '') line.price_amd = Math.round(Number(line.price_yuan) * rate);
}

function recalcAllYuan() {
    form.lines.forEach((l) => l.price_yuan !== '' && onYuan(l));
}

function onPickProduct(line) {
    const product = productById(line.product_id);
    if (!product) return;
    if (line.sell_price === '') line.sell_price = product.sell_price ?? '';
    if (line.price_yuan === '' && product.buy_price_yuan) {
        line.price_yuan = product.buy_price_yuan;
        onYuan(line);
    }
    if (line.price_amd === '' && product.buy_price_amd) line.price_amd = product.buy_price_amd;
}

function addImages(line, files) {
    previews[line.key] ??= [];
    for (const file of files) {
        if (!file.type.startsWith('image/')) continue;
        line.new_product.images.push(file);
        previews[line.key].push(URL.createObjectURL(file));
    }
}

function removeImage(line, i) {
    URL.revokeObjectURL(previews[line.key][i]);
    previews[line.key].splice(i, 1);
    line.new_product.images.splice(i, 1);
}

function removeLine(i) {
    const line = form.lines[i];
    if (line.id) {
        line.delete = !line.delete;
    } else {
        form.lines.splice(i, 1);
    }
}

const lineError = (i, field) => form.errors[`lines.${i}.${field}`];
const lineHasErrors = (i) => Object.keys(form.errors).some((k) => k.startsWith(`lines.${i}.`));

/* ---------- Сохранение ---------- */
const nullIfEmpty = (v) => (v === '' ? null : v);

function submit() {
    form.transform((data) => ({
        ...data,
        yuan_rate: nullIfEmpty(data.yuan_rate),
        shipping_cost: nullIfEmpty(data.shipping_cost),
        shipping_paid_at: Number(data.shipping_cost) > 0 ? nullIfEmpty(data.shipping_paid_at) : null,
        goods_paid_before: data.goods_paid_before ? 1 : 0,
        delivered_at: data.status === 'delivered' ? nullIfEmpty(data.delivered_at) : null,
        quantity: isLegacy ? data.quantity : null,
        price_amd: isLegacy ? nullIfEmpty(data.price_amd) : null,
        price_yuan: isLegacy ? nullIfEmpty(data.price_yuan) : null,
        lines: isLegacy
            ? []
            : data.lines.map((l) => ({
                  id: l.id,
                  delete: l.delete ? 1 : 0,
                  mode: l.id ? 'existing' : l.mode,
                  product_id: l.mode === 'existing' ? l.product_id : null,
                  new_product: !l.id && l.mode === 'new' ? l.new_product : null,
                  price_amd: l.price_amd,
                  price_yuan: nullIfEmpty(l.price_yuan),
                  sell_price: nullIfEmpty(l.sell_price),
                  variants: l.variants.filter((v) => Number(v.qty) > 0),
              })),
        ...(p ? { _method: 'put' } : {}),
    }));

    form.post(p ? route('purchases.update', p.id) : route('purchases.store'), { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="p ? p.title : 'Новая закупка'" />
    <AuthenticatedLayout>
        <PageHeader :title="p ? 'Закупка: ' + p.title : 'Новая закупка'" subtitle="Товары сразу появятся в «Товарах» и на складе">
            <Link :href="backUrl || route('purchases.index')" class="btn-secondary">← Назад</Link>
        </PageHeader>

        <form id="purchase-form" class="grid gap-4 pb-20 sm:gap-6 xl:grid-cols-[1fr_20rem] xl:pb-0" @submit.prevent="submit">
            <div class="min-w-0 space-y-5">
                <!-- Заказ -->
                <div class="card p-4 sm:p-5">
                    <h2 class="mb-4 font-semibold">Заказ</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Field label="Название заказа" :error="form.errors.title" class="sm:col-span-2">
                            <input v-model="form.title" class="input" required placeholder="напр. Pinduoduo 12 ноября" />
                        </Field>
                        <Field label="Источник" :error="form.errors.source">
                            <select v-model="form.source" class="input">
                                <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </Field>
                        <Field label="Дата заказа" :error="form.errors.purchased_at">
                            <input v-model="form.purchased_at" type="date" class="input" required />
                        </Field>
                        <Field label="Статус" :error="form.errors.status">
                            <div class="flex gap-1 rounded-lg bg-gray-100 p-1 text-sm font-medium">
                                <button
                                    v-for="(label, key) in statuses"
                                    :key="key"
                                    type="button"
                                    class="flex-1 rounded-md py-1.5"
                                    :class="form.status === key ? (key === 'ordered' ? 'bg-white text-violet-600 shadow-sm' : 'bg-white text-emerald-600 shadow-sm') : 'text-gray-500'"
                                    @click="form.status = key"
                                >
                                    {{ key === 'ordered' ? '🚚' : '✅' }} {{ label }}
                                </button>
                            </div>
                        </Field>
                        <Field v-if="form.status === 'delivered'" label="Дата доставки" :error="form.errors.delivered_at">
                            <input v-model="form.delivered_at" type="date" class="input" :placeholder="today()" />
                        </Field>
                        <Field label="Общая доставка ֏" :error="form.errors.shipping_cost">
                            <input
                                v-model="form.shipping_cost"
                                type="number"
                                min="0"
                                class="input"
                                placeholder="можно указать позже"
                                @input="Number(form.shipping_cost) > 0 && !form.shipping_paid_at && (form.shipping_paid_at = today())"
                            />
                        </Field>
                        <Field v-if="Number(form.shipping_cost) > 0" label="Доставку оплатили" :error="form.errors.shipping_paid_at">
                            <input v-model="form.shipping_paid_at" type="date" class="input" />
                        </Field>
                        <Field v-if="!isLegacy" label="Курс ¥ → ֏" :error="form.errors.yuan_rate">
                            <input v-model="form.yuan_rate" type="number" step="0.01" min="0" class="input" placeholder="напр. 54" @change="recalcAllYuan" />
                        </Field>
                        <Field label="Ссылка на заказ" class="sm:col-span-2">
                            <input v-model="form.url" class="input" />
                        </Field>
                        <Field label="Заметки" class="sm:col-span-2">
                            <input v-model="form.notes" class="input" />
                        </Field>
                    </div>

                    <label
                        v-if="affectsCash"
                        class="mt-4 flex cursor-pointer items-start gap-3 rounded-lg border p-3 text-sm"
                        :class="form.goods_paid_before ? 'border-amber-300 bg-amber-50' : 'border-gray-200'"
                    >
                        <input v-model="form.goods_paid_before" type="checkbox" class="mt-0.5 rounded text-pink-500 focus:ring-pink-400" />
                        <span>
                            <b>Товар уже оплачен (учтён в кассе)</b>
                            <span class="block text-gray-500">
                                Отметьте, если за товар заплатили раньше и эта сумма уже вычтена из кассы. Из кассы спишется только доставка.
                            </span>
                        </span>
                    </label>
                </div>

                <!-- Старая закупка -->
                <div v-if="isLegacy" class="card p-4 sm:p-5">
                    <h2 class="mb-1 font-semibold">Суммы</h2>
                    <p class="mb-4 text-sm text-gray-500">Старая закупка (до появления строк товаров) — суммы вводятся вручную.</p>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field label="Кол-во" :error="form.errors.quantity"><input v-model="form.quantity" type="number" min="1" class="input" /></Field>
                        <Field label="Цена ֏ (всего)" :error="form.errors.price_amd"><input v-model="form.price_amd" type="number" min="0" class="input" /></Field>
                        <Field label="Цена ¥ (всего)"><input v-model="form.price_yuan" type="number" step="0.01" min="0" class="input" /></Field>
                    </div>
                </div>

                <!-- Товары -->
                <template v-else>
                    <p v-if="form.errors.lines" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ form.errors.lines }}</p>

                    <div
                        v-for="(line, i) in form.lines"
                        :key="line.key"
                        class="card overflow-hidden"
                        :class="{ 'opacity-50': line.delete, 'ring-2 ring-red-300': lineHasErrors(i) }"
                    >
                        <div class="flex items-center justify-between gap-2 border-b bg-gray-50 px-3 py-2.5 sm:px-5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-pink-100 text-xs font-bold text-pink-600">{{ i + 1 }}</span>
                                <template v-if="line.id">
                                    <span class="font-semibold">{{ line.product?.name }}</span>
                                    <Link :href="route('products.edit', line.product_id)" class="text-xs text-gray-400 hover:text-pink-600">открыть товар ↗</Link>
                                </template>
                                <div v-else class="flex gap-1 rounded-lg bg-white p-0.5 text-xs font-medium ring-1 ring-gray-200">
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1"
                                        :class="line.mode === 'new' ? 'bg-pink-500 text-white' : 'text-gray-500'"
                                        @click="line.mode = 'new'"
                                    >
                                        Новый товар
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md px-2.5 py-1"
                                        :class="line.mode === 'existing' ? 'bg-pink-500 text-white' : 'text-gray-500'"
                                        @click="line.mode = 'existing'"
                                    >
                                        Из каталога
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="btn-danger btn-sm" @click="removeLine(i)">
                                <template v-if="line.delete">↺<span class="hidden sm:inline"> Вернуть</span></template><template v-else>✕<span class="hidden sm:inline"> Убрать</span></template>
                            </button>
                        </div>

                        <div v-if="!line.delete" class="grid gap-4 p-3 sm:gap-5 sm:p-5 lg:grid-cols-[11rem_1fr]">
                            <!-- Фото -->
                            <div>
                                <template v-if="!line.id && line.mode === 'new'">
                                    <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-6 lg:grid-cols-2">
                                        <div v-for="(src, j) in previews[line.key] ?? []" :key="src" class="relative aspect-square overflow-hidden rounded-md border">
                                            <img :src="src" class="h-full w-full object-cover" />
                                            <button type="button" class="absolute right-0.5 top-0.5 rounded bg-white/90 px-1 text-xs" @click="removeImage(line, j)">✕</button>
                                        </div>
                                        <label
                                            class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed text-center text-xs text-gray-400 hover:border-pink-300 hover:text-pink-500"
                                            @dragover.prevent
                                            @drop.prevent="addImages(line, $event.dataTransfer.files)"
                                        >
                                            <span class="text-xl">＋</span>фото
                                            <input type="file" accept="image/*" multiple class="hidden" @change="addImages(line, $event.target.files); $event.target.value = ''" />
                                        </label>
                                    </div>
                                    <p v-if="lineError(i, 'new_product.images.0')" class="mt-1 text-xs text-red-600">Фото слишком большое (макс. 10 МБ)</p>
                                </template>
                                <template v-else>
                                    <img
                                        v-if="(line.product || productById(line.product_id))?.images?.length"
                                        :src="(line.product || productById(line.product_id)).images[0].url"
                                        class="aspect-square w-full rounded-lg object-cover"
                                    />
                                    <div v-else class="flex aspect-square w-full items-center justify-center rounded-lg bg-gray-100 text-3xl">🧸</div>
                                </template>
                            </div>

                            <div class="min-w-0 space-y-4">
                                <!-- Товар -->
                                <div v-if="!line.id" class="grid gap-3 sm:grid-cols-2">
                                    <template v-if="line.mode === 'new'">
                                        <Field label="Название товара" :error="lineError(i, 'new_product.name')">
                                            <input v-model="line.new_product.name" class="input" placeholder="напр. polo spartivka" />
                                        </Field>
                                        <Field label="Ссылка Pinduoduo">
                                            <input v-model="line.new_product.pinduoduo_url" class="input" placeholder="https://mobile.yangkeduo.com/…" />
                                        </Field>
                                        <Field label="Для кого">
                                            <select v-model="line.new_product.gender" class="input">
                                                <option value="">— не указано —</option>
                                                <option v-for="(label, key) in genders" :key="key" :value="key">{{ genderIcons[key] }} {{ label }}</option>
                                            </select>
                                        </Field>
                                        <Field label="Описание">
                                            <input v-model="line.new_product.description" class="input" />
                                        </Field>
                                    </template>
                                    <Field v-else label="Товар из каталога" :error="lineError(i, 'product_id')" class="sm:col-span-2">
                                        <select v-model="line.product_id" class="input" @change="onPickProduct(line)">
                                            <option value="">— выберите —</option>
                                            <option v-for="pr in products" :key="pr.id" :value="pr.id">{{ pr.name }}</option>
                                        </select>
                                    </Field>
                                </div>

                                <!-- Цены -->
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                    <Field label="Цена 1 шт ¥">
                                        <input v-model="line.price_yuan" type="number" step="0.01" min="0" class="input" @input="onYuan(line)" />
                                    </Field>
                                    <Field label="Цена 1 шт ֏" :error="lineError(i, 'price_amd')">
                                        <input v-model="line.price_amd" type="number" min="0" class="input" required />
                                    </Field>
                                    <Field label="Продавать за ֏">
                                        <input v-model="line.sell_price" type="number" min="0" class="input" />
                                    </Field>
                                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs leading-5">
                                        <div class="text-gray-500">Себест. с доставкой</div>
                                        <div class="text-sm font-semibold">{{ money(lineCost(line)) }}</div>
                                        <div v-if="line.sell_price" :class="line.sell_price - lineCost(line) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                            маржа {{ money(line.sell_price - lineCost(line)) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Размеры -->
                                <div>
                                    <div class="label">Размеры / цвета / кол-во</div>
                                    <datalist :id="`sizes-${line.key}`">
                                        <option v-for="s in (line.product || productById(line.product_id))?.sizes ?? []" :key="s" :value="s" />
                                    </datalist>
                                    <datalist :id="`colors-${line.key}`">
                                        <option v-for="c in (line.product || productById(line.product_id))?.colors ?? []" :key="c" :value="c" />
                                    </datalist>

                                    <div v-for="v in line.existing" :key="v.size + v.color" class="mb-1.5 flex flex-wrap items-center gap-2 text-sm sm:flex-nowrap">
                                        <span class="w-full rounded-lg bg-gray-50 px-3 py-2 sm:w-auto sm:flex-[2]">{{ v.size || '—' }}</span>
                                        <span class="flex-1 rounded-lg bg-gray-50 px-3 py-2">{{ v.color || '—' }}</span>
                                        <span class="w-20 rounded-lg bg-gray-50 px-3 py-2 text-center">{{ v.qty }} шт</span>
                                        <span class="w-24 text-xs text-gray-400">{{ v.sold ? `продано ${v.sold}` : 'на складе' }}</span>
                                    </div>

                                    <div v-for="(v, j) in line.variants" :key="j" class="mb-3 grid grid-cols-[1fr_4.5rem_3rem] gap-2 sm:mb-1.5 sm:flex">
                                        <input v-model="v.size" :list="`sizes-${line.key}`" class="input col-span-3 sm:flex-[2]" placeholder="Размер, напр. 100 2–3 года" />
                                        <input v-model="v.color" :list="`colors-${line.key}`" class="input min-w-0 sm:flex-1" placeholder="Цвет" />
                                        <input v-model="v.qty" type="number" min="1" class="input sm:w-20" />
                                        <button type="button" class="btn-danger sm:w-24" @click="line.variants.splice(j, 1)">✕</button>
                                    </div>
                                    <button type="button" class="btn-secondary btn-sm" @click="line.variants.push(newVariant())">+ размер / цвет</button>
                                    <p v-if="lineError(i, 'variants')" class="mt-1 text-xs text-red-600">{{ lineError(i, 'variants') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn-secondary w-full border-dashed py-3" @click="form.lines.push(newLine())">
                        + Добавить товар в закупку
                    </button>
                </template>
            </div>

            <!-- Итог -->
            <div>
                <div class="card sticky top-4 space-y-3 p-5 text-sm">
                    <h2 class="font-semibold">Итого</h2>
                    <template v-if="!isLegacy">
                        <div class="flex justify-between"><span class="text-gray-500">Штук</span><b>{{ totalPieces }}</b></div>
                        <div class="flex justify-between"><span class="text-gray-500">Товар</span><b>{{ money(goodsTotal) }}</b></div>
                        <div v-if="yuanTotal" class="flex justify-between"><span class="text-gray-500">В юанях</span><span>¥ {{ yuanTotal.toFixed(2) }}</span></div>
                    </template>
                    <div class="flex justify-between"><span class="text-gray-500">Доставка</span><b>{{ money(Number(form.shipping_cost) || 0) }}</b></div>
                    <div v-if="!isLegacy" class="flex justify-between text-xs text-gray-400">
                        <span>доставка на 1 шт</span><span>{{ money(share) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-3 text-base">
                        <span>Всего</span><b class="text-pink-600">{{ money(total) }}</b>
                    </div>
                    <div v-if="affectsCash" class="rounded-lg bg-gray-50 p-3 text-xs leading-5">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Из кассы</span>
                            <b class="text-red-600">−{{ money(fromCash) }}</b>
                        </div>
                        <div class="text-gray-500">
                            товар: {{ form.goods_paid_before ? 'уже оплачен' : money(goodsSum) }}<br />
                            доставка: {{ Number(form.shipping_cost) > 0 ? money(form.shipping_cost) : 'пока не указана' }}
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400">Старая закупка — кассу не меняет.</p>
                    <p class="text-xs text-gray-400">Себестоимость штук всегда = цена + доля доставки, независимо от оплаты.</p>
                    <button type="submit" class="btn-primary w-full py-2.5" :disabled="form.processing">
                        {{ form.processing ? 'Сохранение…' : 'Сохранить закупку' }}
                    </button>
                    <p v-if="Object.keys(form.errors).length" class="text-xs text-red-600">Проверьте поля, отмеченные красным</p>
                </div>
            </div>
        </form>

        <!-- Телефон: кнопка «Сохранить» всегда под рукой -->
        <div
            class="fixed inset-x-0 z-20 flex items-center gap-3 border-t bg-white px-4 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] xl:hidden"
            style="bottom: calc(4rem + env(safe-area-inset-bottom))"
        >
            <div class="min-w-0 flex-1 leading-tight">
                <div class="text-xs text-gray-500">{{ isLegacy ? 'Всего' : `${totalPieces} шт. · всего` }}</div>
                <div class="font-semibold text-pink-600">{{ money(total) }}</div>
            </div>
            <button type="submit" form="purchase-form" class="btn-primary px-5 py-2.5" :disabled="form.processing">
                {{ form.processing ? '…' : 'Сохранить' }}
            </button>
        </div>
    </AuthenticatedLayout>
</template>
