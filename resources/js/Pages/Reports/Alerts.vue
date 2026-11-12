<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ReportTabs from '../../Components/ReportTabs.vue';
import { formatDate, formatQuantity } from '../../utils/formatters';

defineProps({
    counts: {
        type: Object,
        default: () => ({}),
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

function daysUntil(date) {
    if (!date) {
        return null;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const target = new Date(date);
    target.setHours(0, 0, 0, 0);

    return Math.round((target - today) / 86_400_000);
}

function expiryNote(date) {
    const days = daysUntil(date);

    if (days === null) {
        return '-';
    }

    if (days === 0) {
        return 'Hari ini';
    }

    return days > 0 ? `${days} hari lagi` : `${Math.abs(days)} hari lalu`;
}
</script>

<template>
    <AppLayout title="Laporan Peringatan">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Laporan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Peringatan Stok</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Barang menipis dan batch yang mendekati atau sudah melewati tanggal kedaluwarsa.
                </p>
            </div>

            <a
                href="/reports/alerts.csv"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Unduh CSV
            </a>
        </div>

        <ReportTabs class="mt-4" />

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Stok menipis</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-amber-900">{{ counts.low_stock ?? 0 }}</p>
                <p class="mt-1 text-xs text-amber-700">Barang di bawah stok minimum</p>
            </div>
            <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-orange-700">Mendekati kedaluwarsa</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-orange-900">{{ counts.expiring ?? 0 }}</p>
                <p class="mt-1 text-xs text-orange-700">Batch dengan stok masih tersisa</p>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Sudah kedaluwarsa</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-red-900">{{ counts.expired ?? 0 }}</p>
                <p class="mt-1 text-xs text-red-700">Batch dengan stok masih tersisa</p>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Barang Menipis</h2>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Barang
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Stok
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Minimum
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="lowStockItems.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                Tidak ada barang menipis.
                            </td>
                        </tr>
                        <tr v-for="item in lowStockItems" :key="item.id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ item.name }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums text-red-600">
                                {{ formatQuantity(item.current_stock) }} {{ item.unit }}
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600 sm:table-cell">
                                {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="`/items/${item.id}`"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Detail
                                    </Link>
                                    <Link
                                        :href="`/reports/stock-card/${item.id}`"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                    >
                                        Kartu Stok
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-for="section in [
                { key: 'expiring', title: 'Batch Mendekati Kedaluwarsa', rows: expiringBatches },
                { key: 'expired', title: 'Batch Sudah Kedaluwarsa', rows: expiredBatches },
            ]"
            :key="section.key"
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">{{ section.title }}</h2>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-4">
                                Barang
                            </th>
                            <th class="whitespace-nowrap px-3 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-4">
                                Kedaluwarsa
                            </th>
                            <th class="whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:px-4">
                                Sisa stok
                            </th>
                            <th class="hidden whitespace-nowrap px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell sm:px-4">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="section.rows.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                Tidak ada batch pada kategori ini.
                            </td>
                        </tr>
                        <tr v-for="batch in section.rows" :key="batch.id" class="hover:bg-slate-50/70">
                            <td class="px-3 py-3 sm:px-4">
                                <p class="font-medium text-slate-900">{{ batch.item_name ?? '-' }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ batch.item_code ?? '-' }}</p>
                                <Link
                                    :href="`/reports/stock-card/${batch.item_id}`"
                                    class="mt-1 block text-xs font-medium text-blue-600 sm:hidden"
                                >
                                    Kartu Stok
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 sm:px-4">
                                <p class="text-slate-700">{{ formatDate(batch.expiry_date) }}</p>
                                <p
                                    class="mt-0.5 text-xs"
                                    :class="section.key === 'expired' ? 'text-red-500' : 'text-orange-600'"
                                >
                                    {{ expiryNote(batch.expiry_date) }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums text-slate-700 sm:px-4">
                                {{ formatQuantity(batch.quantity_remaining) }} {{ batch.unit ?? '' }}
                            </td>
                            <td class="hidden whitespace-nowrap px-3 py-3 text-right sm:table-cell sm:px-4">
                                <Link
                                    :href="`/reports/stock-card/${batch.item_id}`"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    Kartu Stok
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
