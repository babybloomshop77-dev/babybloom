<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import Field from '@/Components/UI/Field.vue';
import TagInput from '@/Components/UI/TagInput.vue';
import { money } from '@/utils';

const props = defineProps({ product: Object, sources: Object, backUrl: String });
const p = props.product;

const form = useForm({
    name: p?.name ?? '',
    description: p?.description ?? '',
    source: p?.source ?? 'pinduoduo',
    pinduoduo_url: p?.pinduoduo_url ?? '',
    buy_price_yuan: p?.buy_price_yuan ?? '',
    buy_price_amd: p?.buy_price_amd ?? '',
    shipping_cost: p?.shipping_cost ?? '',
    sell_price: p?.sell_price ?? '',
    sizes: p?.sizes ?? [],
    colors: p?.colors ?? [],
    notes: p?.notes ?? '',
    is_active: p?.is_active ?? true,
    images: [],
    delete_images: [],
    back: props.backUrl, // куда вернуться после сохранения
    // цвет для каждого фото: '' = общее фото
    image_colors: Object.fromEntries((p?.images ?? []).map((img) => [img.id, img.color ?? ''])),
    new_image_colors: [],
});

const previews = ref([]);
const dragging = ref(false);

function addFiles(files) {
    for (const file of files) {
        if (!file.type.startsWith('image/')) continue;
        form.images.push(file);
        form.new_image_colors.push('');
        previews.value.push(URL.createObjectURL(file));
    }
}

function removeNew(i) {
    URL.revokeObjectURL(previews.value[i]);
    form.images.splice(i, 1);
    form.new_image_colors.splice(i, 1);
    previews.value.splice(i, 1);
}

function toggleDelete(id) {
    const i = form.delete_images.indexOf(id);
    i === -1 ? form.delete_images.push(id) : form.delete_images.splice(i, 1);
}

const cost = computed(() => (Number(form.buy_price_amd) || 0) + (Number(form.shipping_cost) || 0));
const margin = computed(() => (form.sell_price ? Number(form.sell_price) - cost.value : null));

const clean = (data) => {
    const nullable = ['buy_price_yuan', 'buy_price_amd', 'shipping_cost', 'sell_price'];
    nullable.forEach((k) => (data[k] = data[k] === '' ? null : data[k]));
    data.is_active = data.is_active ? 1 : 0;
    return data;
};

function submit() {
    if (p) {
        form.transform((data) => ({ ...clean(data), _method: 'put' })).post(route('products.update', p.id), { forceFormData: true });
    } else {
        form.transform(clean).post(route('products.store'), { forceFormData: true });
    }
}
</script>

<template>
    <Head :title="p ? p.name : 'Новый товар'" />
    <AuthenticatedLayout>
        <PageHeader :title="p ? 'Редактировать товар' : 'Новый товар'">
            <Link :href="backUrl || route('products.index')" class="btn-secondary">← Назад</Link>
        </PageHeader>

        <form class="grid gap-4 sm:gap-6 xl:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-6 xl:col-span-2">
                <div class="card space-y-4 p-4 sm:p-5">
                    <Field label="Название" :error="form.errors.name">
                        <input v-model="form.name" class="input" placeholder="например: polo spartivka sumkov" required />
                    </Field>
                    <Field label="Описание" :error="form.errors.description">
                        <textarea v-model="form.description" rows="3" class="input" />
                    </Field>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field label="Источник" :error="form.errors.source">
                            <select v-model="form.source" class="input">
                                <option v-for="(label, key) in sources" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </Field>
                        <Field label="Ссылка (Pinduoduo)" :error="form.errors.pinduoduo_url" class="sm:col-span-2">
                            <input v-model="form.pinduoduo_url" class="input" placeholder="https://mobile.yangkeduo.com/…" />
                        </Field>
                    </div>
                </div>

                <div class="card space-y-4 p-4 sm:p-5">
                    <h2 class="font-semibold">Размеры и цвета</h2>
                    <Field label="Размеры (Enter — добавить)" :error="form.errors.sizes">
                        <TagInput v-model="form.sizes" placeholder="100 2–3 года, 110 3–4 года…" />
                    </Field>
                    <Field label="Цвета (Enter — добавить)" :error="form.errors.colors">
                        <TagInput v-model="form.colors" placeholder="black, cream, brown…" />
                    </Field>
                </div>

                <div class="card p-4 sm:p-5">
                    <h2 class="font-semibold">Фотографии</h2>
                    <p class="mb-3 text-xs text-gray-500">
                        Под фото выберите цвет — на складе у штуки этого цвета будет это фото. «Общее» — для всех цветов.
                        <span v-if="!form.colors.length">Сначала добавьте цвета выше.</span>
                    </p>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 sm:gap-3 lg:grid-cols-5">
                        <div v-for="img in p?.images ?? []" :key="img.id" :class="{ 'opacity-30': form.delete_images.includes(img.id) }">
                            <div class="relative aspect-square overflow-hidden rounded-lg border">
                                <img :src="img.url" class="h-full w-full object-cover" />
                                <button
                                    type="button"
                                    class="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-xs shadow"
                                    @click="toggleDelete(img.id)"
                                >
                                    {{ form.delete_images.includes(img.id) ? '↺' : '✕' }}
                                </button>
                            </div>
                            <select
                                v-model="form.image_colors[img.id]"
                                class="mt-1 block w-full rounded-md border-gray-300 py-1 pl-2 pr-6 text-base sm:text-xs focus:border-pink-400 focus:ring-pink-400"
                                :class="{ 'border-pink-300 bg-pink-50 text-pink-700': form.image_colors[img.id] }"
                            >
                                <option value="">Общее</option>
                                <option v-for="c in form.colors" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div v-for="(src, i) in previews" :key="src">
                            <div class="relative aspect-square overflow-hidden rounded-lg border-2 border-pink-300">
                                <img :src="src" class="h-full w-full object-cover" />
                                <button type="button" class="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-xs shadow" @click="removeNew(i)">✕</button>
                            </div>
                            <select
                                v-model="form.new_image_colors[i]"
                                class="mt-1 block w-full rounded-md border-gray-300 py-1 pl-2 pr-6 text-base sm:text-xs focus:border-pink-400 focus:ring-pink-400"
                                :class="{ 'border-pink-300 bg-pink-50 text-pink-700': form.new_image_colors[i] }"
                            >
                                <option value="">Общее</option>
                                <option v-for="c in form.colors" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <label
                            class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed text-center text-xs text-gray-400 hover:border-pink-300 hover:text-pink-500"
                            :class="{ 'border-pink-400 bg-pink-50': dragging }"
                            @dragover.prevent="dragging = true"
                            @dragleave="dragging = false"
                            @drop.prevent="dragging = false; addFiles($event.dataTransfer.files)"
                        >
                            <span class="text-2xl">＋</span>
                            Добавить фото
                            <input type="file" accept="image/*" multiple class="hidden" @change="addFiles($event.target.files); $event.target.value = ''" />
                        </label>
                    </div>
                    <p v-if="Object.keys(form.errors).some((k) => k.startsWith('images'))" class="mt-2 text-xs text-red-600">
                        Ошибка загрузки фото (макс. 10 МБ на фото)
                    </p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card space-y-4 p-4 sm:p-5">
                    <h2 class="font-semibold">Цены (за 1 шт.)</h2>
                    <div class="grid grid-cols-2 gap-3">
                        <Field label="Цена ¥" :error="form.errors.buy_price_yuan">
                            <input v-model="form.buy_price_yuan" type="number" step="0.01" min="0" class="input" />
                        </Field>
                        <Field label="Цена ֏" :error="form.errors.buy_price_amd">
                            <input v-model="form.buy_price_amd" type="number" min="0" class="input" />
                        </Field>
                        <Field label="Доставка ֏" :error="form.errors.shipping_cost">
                            <input v-model="form.shipping_cost" type="number" min="0" class="input" />
                        </Field>
                        <Field label="Цена продажи ֏" :error="form.errors.sell_price">
                            <input v-model="form.sell_price" type="number" min="0" class="input" />
                        </Field>
                    </div>
                    <div class="space-y-1 rounded-lg bg-gray-50 p-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Себестоимость</span><b>{{ money(cost) }}</b></div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Маржа</span>
                            <b :class="margin >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ money(margin) }}</b>
                        </div>
                    </div>
                </div>

                <div class="card space-y-4 p-4 sm:p-5">
                    <Field label="Заметки">
                        <textarea v-model="form.notes" rows="3" class="input" />
                    </Field>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded text-pink-500 focus:ring-pink-400" />
                        Активный товар
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full py-2.5" :disabled="form.processing">
                    {{ form.processing ? 'Сохранение…' : 'Сохранить' }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
