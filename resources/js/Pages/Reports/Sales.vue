<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ReportTabs from '../../Components/ReportTabs.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const groupBy = ref(props.filters.group_by ?? 'day');

function applyFilters() {
    router.get(
        '/reports/sales',
        {
            date_from: dateFrom.value,
            date_to: dateTo.value,
            group_by: groupBy.value,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    router.get('/reports/sales', {}, { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams({
        date_from: dateFrom.value,
        date_to: dateTo.value,
        group_by: groupBy.value,
    });

    return `/reports/sales.csv?${params.toString()}`;
}

function formatPeriod(period) {
    if (props.report.group_by === 'month') {
        const [year, month] = period.split('-');
        const date = new Date(Number(year), Number(month) - 1, 1);

        return date.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
    }

    return formatDate(period);
}

watch(
    () => props.filters,
    (filters) => {
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
        groupBy.value = filters.group_by ?? 'day';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Laporan Penjualan">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Laporan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Penjualan per Periode</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Ringkasan nilai penjualan, barang terlaris, dan pembelian per pelanggan.
                </p>
            </div>

            <a
                :href="exportUrl()"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Unduh CSV
            </a>
        </div>

        <ReportTabs class="mt-4" />

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div>
                <label for="sales-date-from" class="mb-1 block text-xs font-medium text-slate-500">
                    Dari tanggal
                </label>
                <input
                    id="sales-date-from"
                    v-model="dateFrom"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="sales-date-to" class="mb-1 block text-xs font-medium text-slate-500">
                    Sampai tanggal
                </label>
                <input
                    id="sales-date-to"
                    v-model="dateTo"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="sales-group" class="mb-1 block text-xs font-medium text-slate-500">Pengelompokan</label>
                <select
                    id="sales-group"
                    v-model="groupBy"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="day">Harian</option>
                    <option value="month">Bulanan</option>
                </select>
            </div>

            <button
                type="submit"
                class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Terapkan
            </button>
            <button
                type="button"
                class="self-end rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="resetFilters"
            >
                Reset
            </button>
        </form>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total penjualan</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatCurrency(report.total_sales) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ formatDate(report.date_from) }} – {{ formatDate(report.date_to) }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah transaksi</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ report.transaction_count }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Transaksi batal tidak dihitung</p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-3">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">Penjualan per {{ report.group_by === 'month' ? 'bulan' : 'hari' }}</h2>
                </div>
                <div class="w-full overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Periode
                                </th>
                                <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Transaksi
                                </th>
                                <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Nilai
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="report.series.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">
                                    Belum ada penjualan pada periode ini.
                                </td>
                            </tr>
                            <tr v-for="row in report.series" :key="row.period" class="hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ formatPeriod(row.period) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600">
                                    {{ row.transaction_count }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900">
                                    {{ formatCurrency(row.total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">Barang Terlaris</h2>
                </div>
                <ul class="divide-y divide-slate-100">
                    <li v-if="report.best_sellers.length === 0" class="px-4 py-8 text-center text-sm text-slate-500">
                        Belum ada barang terjual.
                    </li>
                    <li
                        v-for="(item, index) in report.best_sellers"
                        :key="item.item_id"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-blue-700"
                        >
                            {{ index + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-medium text-slate-900">{{ item.item_name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatQuantity(item.quantity) }} {{ item.unit }} · {{ formatCurrency(item.total) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Penjualan per Barang</h2>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Barang
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Jumlah terjual
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Nilai
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="report.items.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">
                                Belum ada barang terjual pada periode ini.
                            </td>
                        </tr>
                        <tr v-for="item in report.items" :key="item.item_id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ item.item_name }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.item_code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-700">
                                {{ formatQuantity(item.quantity) }} {{ item.unit }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900">
                                {{ formatCurrency(item.total) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Pembelian per Pelanggan</h2>
            </div>
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Pelanggan
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Transaksi
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="report.customers.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">
                                Belum ada pembelian pelanggan pada periode ini.
                            </td>
                        </tr>
                        <tr v-for="customer in report.customers" :key="customer.customer_id" class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ customer.customer_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600">
                                {{ customer.transaction_count }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-900">
                                {{ formatCurrency(customer.total) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
