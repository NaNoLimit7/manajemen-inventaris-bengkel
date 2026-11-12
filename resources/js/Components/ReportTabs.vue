<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const tabs = [
    {
        label: 'Stok',
        href: '/reports/stock',
        prefixes: ['/reports/stock', '/reports/stock-card'],
    },
    { label: 'Penjualan', href: '/reports/sales' },
    { label: 'Peringatan', href: '/reports/alerts' },
    { label: 'Audit', href: '/reports/audit' },
];

const path = computed(() => page.url.split('?')[0]);

function isActive(tab) {
    const prefixes = tab.prefixes ?? [tab.href];

    return prefixes.some(
        (prefix) => path.value === prefix || path.value.startsWith(`${prefix}/`),
    );
}
</script>

<template>
    <nav
        class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1 shadow-sm"
        aria-label="Navigasi laporan"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="
                isActive(tab)
                    ? 'bg-blue-600 text-white shadow-sm'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
