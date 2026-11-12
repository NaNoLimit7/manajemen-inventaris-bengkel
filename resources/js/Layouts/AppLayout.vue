<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import FlashMessages from '../Components/FlashMessages.vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
});

const page = usePage();
const mobileOpen = ref(false);

const appName = computed(() => page.props.appName ?? 'Inventaris Bengkel');
const badgeCount = computed(() => page.props.badges?.total ?? 0);

const navItems = [
    { label: 'Dashboard', href: '/', exact: true },
    { label: 'Barang', href: '/items' },
    { label: 'Barang Masuk', href: '/stock/in' },
    { label: 'Barang Keluar', href: '/stock/out' },
    { label: 'Riwayat', href: '/stock/documents' },
    { label: 'Penyesuaian', href: '/stock/adjustments' },
    { label: 'Laporan', href: '/reports/stock', prefixes: ['/reports'] },
    { label: 'Supplier', href: '/suppliers' },
    { label: 'Pelanggan', href: '/customers' },
    { label: 'Pesanan', href: '/purchase-orders' },
    { label: 'Pengaturan', href: '/settings' },
];

const isActive = (item) => {
    const path = page.url.split('?')[0];

    if (item.prefixes) {
        return item.prefixes.some(
            (prefix) => path === prefix || path.startsWith(`${prefix}/`),
        );
    }

    return item.exact
        ? path === item.href
        : path === item.href || path.startsWith(`${item.href}/`);
};
</script>

<template>
    <Head :title="title || appName" />

    <div class="min-h-screen bg-slate-100">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-sm font-bold text-white"
                    >
                        IB
                    </span>
                    <span class="hidden whitespace-nowrap text-base font-semibold tracking-tight text-slate-900 2xl:block">
                        {{ appName }}
                    </span>
                </Link>

                <nav class="hidden items-center gap-0.5 overflow-x-auto lg:flex" aria-label="Navigasi utama">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="whitespace-nowrap rounded-lg px-2 py-2 text-xs font-medium transition xl:px-2.5"
                        :class="
                            isActive(item)
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex items-center gap-2">
                    <Link
                        href="/"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50"
                        title="Peringatan stok dan kedaluwarsa"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                            />
                        </svg>
                        <span
                            v-if="badgeCount > 0"
                            class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
                        >
                            {{ badgeCount }}
                        </span>
                    </Link>

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50 lg:hidden"
                        aria-label="Buka menu"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <nav v-if="mobileOpen" class="border-t border-slate-200 bg-white px-4 py-3 lg:hidden" aria-label="Navigasi ponsel">
                <div class="grid gap-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-xl px-3 py-2 text-sm font-medium transition"
                        :class="
                            isActive(item)
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                        "
                        @click="mobileOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </div>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-6xl overflow-x-clip px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <FlashMessages />
            <slot />
        </main>
    </div>
</template>
