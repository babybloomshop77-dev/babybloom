<script setup>
import { computed, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const mobileOpen = ref(false);
const toast = ref(null);
let toastTimer = null;

const nav = [
    { name: 'Главная', route: 'dashboard', match: 'dashboard', icon: 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10' },
    { name: 'Касса', route: 'cash.index', match: 'cash.*', icon: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z' },
    { name: 'Товары', route: 'products.index', match: 'products.*', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { name: 'Закупки', route: 'purchases.index', match: 'purchases.*', icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
    { name: 'Склад', route: 'stock.index', match: 'stock.*', icon: 'M4 6h16M4 10h16M4 14h16M4 18h16' },
    { name: 'Заказы', route: 'orders.index', match: 'orders.*', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
    { name: 'Продажи', route: 'sales.index', match: 'sales.*', icon: 'M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 .9 3 2-1.3 2-3 2m0-8c1.1 0 2.1.4 2.6 1M12 8V7m0 1v8m0 0v1m0-1c-1.1 0-2.1-.4-2.6-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
    { name: 'Расходы', route: 'expenses.index', match: 'expenses.*', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
];

// На телефоне внизу — самые частые разделы, остальное в «Ещё»
const bottomNav = ['dashboard', 'cash.index', 'stock.index', 'orders.index'].map((r) => nav.find((n) => n.route === r));
const moreActive = computed(() => !bottomNav.some((n) => route().current(n.match)));

watch(
    () => page.props.flash,
    (flash) => {
        const message = flash?.success || flash?.error;
        if (!message) return;
        toast.value = { message, error: !!flash.error };
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (toast.value = null), 2500);
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col border-r border-gray-200 bg-white transition-transform lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <Link :href="route('dashboard')" class="flex h-16 items-center gap-2 px-5">
                <span class="text-2xl">🌸</span>
                <span class="text-lg font-bold text-gray-900">Baby<span class="text-pink-500">Bloom</span></span>
            </Link>

            <nav class="flex-1 space-y-0.5 px-3 py-2">
                <Link
                    v-for="item in nav"
                    :key="item.route"
                    :href="route(item.route)"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="route().current(item.match) ? 'bg-pink-50 text-pink-600' : 'text-gray-600 hover:bg-gray-100'"
                    @click="mobileOpen = false"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    {{ item.name }}
                </Link>
            </nav>

            <div class="border-t p-3">
                <Link :href="route('profile.edit')" class="block truncate rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
                    👤 {{ $page.props.auth.user.name }}
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="w-full rounded-lg px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-100"
                >
                    Выйти
                </Link>
            </div>
        </aside>

        <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-black/30 lg:hidden" @click="mobileOpen = false" />

        <!-- Mobile top bar -->
        <div class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b bg-white px-4 lg:hidden">
            <button class="rounded-md p-1.5 text-gray-600 hover:bg-gray-100" @click="mobileOpen = true">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="font-bold">Baby<span class="text-pink-500">Bloom</span></span>
        </div>

        <main class="pb-24 lg:pb-0 lg:pl-60">
            <header v-if="$slots.header" class="border-b bg-white">
                <div class="px-4 py-5 sm:px-8">
                    <slot name="header" />
                </div>
            </header>
            <div class="px-3 py-4 sm:px-8 sm:py-6">
                <slot />
            </div>
        </main>

        <!-- Нижнее меню на телефоне -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-gray-200 bg-white/95 backdrop-blur lg:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
        >
            <Link
                v-for="item in bottomNav"
                :key="item.route"
                :href="route(item.route)"
                class="flex flex-col items-center gap-0.5 pb-2 pt-2.5 text-[11px] font-medium"
                :class="route().current(item.match) ? 'text-pink-600' : 'text-gray-500'"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                </svg>
                {{ item.name }}
            </Link>
            <button
                class="flex flex-col items-center gap-0.5 pb-2 pt-2.5 text-[11px] font-medium"
                :class="moreActive ? 'text-pink-600' : 'text-gray-500'"
                @click="mobileOpen = true"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                Ещё
            </button>
        </nav>

        <!-- Toast -->
        <Transition
            enter-from-class="opacity-0 translate-y-2"
            leave-to-class="opacity-0 translate-y-2"
            enter-active-class="transition"
            leave-active-class="transition"
        >
            <div
                v-if="toast"
                class="fixed inset-x-4 bottom-24 z-[60] rounded-lg px-4 py-3 text-center text-sm font-medium text-white shadow-lg lg:inset-x-auto lg:bottom-5 lg:right-5 lg:py-2.5"
                :class="toast.error ? 'bg-red-600' : 'bg-gray-900'"
            >
                {{ toast.message }}
            </div>
        </Transition>
    </div>
</template>
