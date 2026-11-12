<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ReportTabs from '../../Components/ReportTabs.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    movements: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const type = ref(props.filters.type ?? '');

const typeLabels = {
    in: 'Masuk',
    out: 'Keluar',
    adjustment: 'Penyesuaian',
    reversal: 'Koreksi',
};

const typeClasses = {
    in: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    out: 'bg-blue-50 text-blue-700 ring-blue-200',
    adjustment: 'bg-amber-50 text-amber-700 ring-amber-200',
    reversal: 'bg-red-50 text-red-700 ring-red-200',
};

function applyFilters() {
    const params = {};

    if (dateFrom.value !== '') {
        params.date_from = dateFrom.value;
    }

    if (dateTo.value !== '') {
        params.date_to = dateTo.value;
    }

    if (type.value !== '') {
        params.type = type.value;
    }

    router.get(`/reports/stock-card/${props.item.id}`, params, { preserveState: true, replace: true });
}

function resetFilters() {
    dateFrom.value = '';
    dateTo.value = '';
    type.value = '';
    router.get(`/reports/stock-card/${props.item.id}`, {}, { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();

    if (dateFrom.value !== '') {
        params.set('date_from', dateFrom.value);
    }

    if (dateTo.value !== '') {
        params.set('date_to', dateTo.value);
    }

    if (type.value !== '') {
        params.set('type', type.value);
    }

    const query = params.toString();

    return `/reports/stock-card/${props.item.id}.csv${query ? `?${query}` : ''}`;
}

function unitPrice(movement) {
    return movement.unit_price ?? movement.unit_cost ?? null;
}

function isLowStock() {
    return Number(props.item.min_stock ?? 0) > 0 && Number(props.item.current_stock ?? 0) < Number(props.item.min_stock);
}

watch(
    () => props.filters,
    (filters) => {
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
        type.value = filters.type ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout :title="`Kartu Stok ${item.name}`">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Laporan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ item.name }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ item.code }} · {{ item.category?.name ?? 'Tanpa kategori' }} · {{ item.unit }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    :href="exportUrl()"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Unduh CSV
                </a>
                <Link
                    href="/reports/stock"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Kembali ke Laporan Stok
                </Link>
            </div>
        </div>

        <ReportTabs class="mt-4" />

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stok saat ini</p>
                <p
                    class="mt-2 text-2xl font-bold tabular-nums"
                    :class="isLowStock() ? 'text-red-600' : 'text-slate-900'"
                >
                    {{ formatQuantity(item.current_stock) }} {{ item.unit }}
                </p>
                <p v-if="isLowStock()" class="mt-1 text-xs text-red-500">
                    Minimum {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stok minimum</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                </p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga beli terakhir</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatCurrency(item.purchase_price) }}
                </p>
            </div>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div>
                <label for="card-date-from" class="mb-1 block text-xs font-medium text-slate-500">
                    Dari tanggal
                </label>
                <input
                    id="card-date-from"
                    v-model="dateFrom"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="card-date-to" class="mb-1 block text-xs font-medium text-slate-500">
                    Sampai tanggal
                </label>
                <input
                    id="card-date-to"
                    v-model="dateTo"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="card-type" class="mb-1 block text-xs font-medium text-slate-500">Jenis dokumen</label>
                <select
                    id="card-type"
                    v-model="type"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Semua jenis</option>
                    <option value="in">Masuk</option>
                    <option value="out">Keluar</option>
                    <option value="adjustment">Penyesuaian</option>
                    <option value="reversal">Koreksi</option>
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

        <div class="mt-6 w-full max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Tanggal
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Jenis
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Catatan
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Masuk
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Keluar
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Saldo
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Harga
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="movements.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada pergerakan stok yang sesuai filter.
                            </td>
                        </tr>
                        <tr
                            v-for="movement in movements"
                            :key="movement.id"
                            class="align-top transition hover:bg-slate-50/70"
                        >
                            <td class="whitespace-nowrap px-4 py-4 text-slate-700">
                                {{ formatDate(movement.stock_document?.occurred_on) }}
                                <span
                                    class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset sm:hidden"
                                    :class="
                                        typeClasses[movement.stock_document?.type] ??
                                        'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{ typeLabels[movement.stock_document?.type] ?? movement.stock_document?.type }}
                                </span>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 sm:table-cell">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                    :class="
                                        typeClasses[movement.stock_document?.type] ??
                                        'bg-slate-100 text-slate-600 ring-slate-200'
                                    "
                                >
                                    {{ typeLabels[movement.stock_document?.type] ?? movement.stock_document?.type }}
                                </span>
                            </td>
                            <td class="hidden px-4 py-4 text-slate-600 sm:table-cell">
                                <span class="line-clamp-2 max-w-xs">{{ movement.stock_document?.notes || '-' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums text-emerald-700">
                                {{ Number(movement.quantity) > 0 ? formatQuantity(movement.quantity) : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right tabular-nums text-blue-700">
                                {{ Number(movement.quantity) < 0 ? formatQuantity(Math.abs(Number(movement.quantity))) : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right font-medium tabular-nums text-slate-900">
                                {{ formatQuantity(movement.running_balance) }}
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-right tabular-nums text-slate-600 sm:table-cell">
                                {{ unitPrice(movement) !== null ? formatCurrency(unitPrice(movement)) : '-' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
