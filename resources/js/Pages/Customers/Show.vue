<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

defineProps({
    customer: {
        type: Object,
        required: true,
    },
    history: {
        type: Array,
        default: () => [],
    },
    totalPurchases: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <AppLayout :title="`Pelanggan ${customer.name}`">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Data</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                    {{ customer.name }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ customer.phone || 'Tanpa nomor telepon' }}
                    <span
                        class="ml-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="
                            customer.is_active
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                : 'bg-slate-100 text-slate-500 ring-slate-200'
                        "
                    >
                        {{ customer.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/customers"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
                <Link
                    :href="`/customers/${customer.id}/edit`"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Ubah
                </Link>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Total pembelian</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">
                    {{ formatCurrency(totalPurchases) }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Jumlah transaksi</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ history.length }}</p>
            </div>
        </div>

        <div v-if="customer.notes" class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ customer.notes }}</p>
        </div>

        <div class="mt-6">
            <h2 class="mb-3 text-base font-semibold text-slate-900">Riwayat Pembelian</h2>

            <div v-if="history.length === 0" class="rounded-2xl border border-slate-200 bg-white px-5 py-10 text-center shadow-sm">
                <p class="text-sm text-slate-500">Pelanggan ini belum memiliki riwayat pembelian.</p>
                <Link href="/stock/out" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-700">
                    Catat penjualan
                </Link>
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="transaction in history"
                    :key="transaction.id"
                    class="rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                        <div>
                            <Link
                                :href="`/stock/documents/${transaction.id}`"
                                class="text-sm font-semibold text-slate-900 hover:text-blue-700"
                            >
                                Dokumen #{{ transaction.id }}
                            </Link>
                            <span class="ml-2 text-xs text-slate-500">
                                {{ formatDate(transaction.occurred_on) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                v-if="transaction.is_cancelled"
                                class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-200"
                            >
                                Dibatalkan
                            </span>
                            <span class="text-sm font-semibold text-slate-900">
                                {{ formatCurrency(transaction.total) }}
                            </span>
                        </div>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li
                            v-for="(line, index) in transaction.items"
                            :key="index"
                            class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-slate-800">
                                    {{ line.item_name }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ line.item_code }} · {{ formatQuantity(line.quantity) }} {{ line.unit }}
                                    × {{ formatCurrency(line.unit_price) }}
                                </span>
                            </span>
                            <span class="text-sm tabular-nums text-slate-700">
                                {{ formatCurrency(line.value) }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
