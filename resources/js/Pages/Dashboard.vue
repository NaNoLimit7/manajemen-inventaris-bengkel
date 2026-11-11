<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { formatCurrency, formatDate, formatQuantity } from '../utils/formatters';

defineProps({
    summary: {
        type: Object,
        default: () => ({ low_stock: 0, expiring: 0, expired: 0, total: 0 }),
    },
    salesSummary: {
        type: Object,
        default: () => ({ total_sales: 0, transaction_count: 0 }),
    },
    lowStockItems: {
        type: Array,
        default: () => [],
    },
    expiringBatches: {
        type: Array,
        default: () => [],
    },
    expiredBatches: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                Ringkasan Bengkel
            </p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Dashboard
            </h1>
            <p class="mt-3 max-w-2xl text-slate-600">
                Pantau kondisi stok dan mulai catat barang masuk atau keluar dari menu di atas.
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    href="/items"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-5 transition hover:border-amber-300"
                >
                    <p class="text-sm font-medium text-amber-700">Stok menipis</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ summary.low_stock }}</p>
                </Link>
                <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
                    <p class="text-sm font-medium text-orange-700">Mendekati kedaluwarsa</p>
                    <p class="mt-2 text-2xl font-semibold text-orange-900">{{ summary.expiring }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <p class="text-sm font-medium text-red-700">Sudah kedaluwarsa</p>
                    <p class="mt-2 text-2xl font-semibold text-red-900">{{ summary.expired }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <p class="text-sm font-medium text-slate-500">Penjualan bulan ini</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ formatCurrency(salesSummary.total_sales) }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ salesSummary.transaction_count }} transaksi
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Stok menipis</h2>
                    <Link href="/items" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                        Lihat barang
                    </Link>
                </div>
                <div v-if="lowStockItems.length === 0" class="px-5 py-8 text-center text-sm text-slate-500">
                    Semua stok barang berada di atas batas minimum.
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="item in lowStockItems" :key="item.id" class="px-5 py-3">
                        <Link
                            :href="`/items/${item.id}`"
                            class="flex items-center justify-between gap-3 transition hover:text-blue-700"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800">
                                    {{ item.name }}
                                </span>
                                <span class="text-xs text-slate-500">{{ item.code }}</span>
                            </span>
                            <span class="shrink-0 text-right text-sm">
                                <span class="font-semibold text-amber-700">
                                    {{ formatQuantity(item.current_stock) }} {{ item.unit }}
                                </span>
                                <span class="block text-xs text-slate-500">
                                    minimum {{ formatQuantity(item.min_stock) }}
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">Mendekati kedaluwarsa</h2>
                    <Link
                        href="/stock/documents"
                        class="text-sm font-medium text-blue-600 hover:text-blue-700"
                    >
                        Riwayat
                    </Link>
                </div>
                <div v-if="expiringBatches.length === 0" class="px-5 py-8 text-center text-sm text-slate-500">
                    Tidak ada batch yang mendekati kedaluwarsa.
                </div>
                <ul v-else class="divide-y divide-slate-100">
                    <li v-for="batch in expiringBatches" :key="batch.id" class="px-5 py-3">
                        <Link
                            :href="`/items/${batch.item_id}`"
                            class="flex items-center justify-between gap-3 transition hover:text-blue-700"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800">
                                    {{ batch.item_name }}
                                </span>
                                <span class="text-xs text-slate-500">{{ batch.item_code }}</span>
                            </span>
                            <span class="shrink-0 text-right text-sm">
                                <span class="font-semibold text-orange-700">
                                    {{ formatDate(batch.expiry_date) }}
                                </span>
                                <span class="block text-xs text-slate-500">
                                    sisa {{ formatQuantity(batch.quantity_remaining) }} {{ batch.unit }}
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <h2 class="text-base font-semibold text-slate-900">Sudah kedaluwarsa</h2>
                <Link
                    href="/stock/documents"
                    class="text-sm font-medium text-blue-600 hover:text-blue-700"
                >
                    Riwayat
                </Link>
            </div>
            <div v-if="expiredBatches.length === 0" class="px-5 py-8 text-center text-sm text-slate-500">
                Tidak ada batch kedaluwarsa yang masih bersisa.
            </div>
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="batch in expiredBatches" :key="batch.id" class="px-5 py-3">
                    <Link
                        :href="`/items/${batch.item_id}`"
                        class="flex items-center justify-between gap-3 transition hover:text-blue-700"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-800">
                                {{ batch.item_name }}
                            </span>
                            <span class="text-xs text-slate-500">{{ batch.item_code }}</span>
                        </span>
                        <span class="shrink-0 text-right text-sm">
                            <span class="font-semibold text-red-700">
                                {{ formatDate(batch.expiry_date) }}
                            </span>
                            <span class="block text-xs text-slate-500">
                                sisa {{ formatQuantity(batch.quantity_remaining) }} {{ batch.unit }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Mulai dari sini</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Data barang contoh sudah tersedia di environment local. Gunakan menu Barang untuk
                melihat daftar stok, lalu catat transaksi harian dari menu Barang Masuk dan Barang
                Keluar.
            </p>
        </div>
    </AppLayout>
</template>
