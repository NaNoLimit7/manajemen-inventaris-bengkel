<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatDate } from '../../utils/formatters';

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    items: {
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
const itemId = ref(props.filters.item_id ?? '');

const columns = [
    { key: 'occurred_on', label: 'Tanggal' },
    { key: 'type', label: 'Jenis' },
    { key: 'reference_number', label: 'Referensi' },
    { key: 'notes', label: 'Catatan' },
    { key: 'stock_movements_count', label: 'Baris', class: 'text-right' },
    { key: 'actions', label: 'Aksi', class: 'text-right' },
];

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

    if (itemId.value !== '') {
        params.item_id = itemId.value;
    }

    router.get('/stock/documents', params, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    dateFrom.value = '';
    dateTo.value = '';
    itemId.value = '';
    router.get('/stock/documents', {}, { preserveState: true, replace: true });
}

watch(
    () => props.filters,
    (filters) => {
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
        itemId.value = filters.item_id ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Riwayat Transaksi">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Transaksi Stok</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Riwayat Transaksi</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Lihat seluruh transaksi barang masuk dan keluar beserta detailnya.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/stock/in"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Barang Masuk
                </Link>
                <Link
                    href="/stock/out"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                >
                    Barang Keluar
                </Link>
            </div>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div>
                <label for="date-from" class="mb-1 block text-xs font-medium text-slate-500">
                    Dari tanggal
                </label>
                <input
                    id="date-from"
                    v-model="dateFrom"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="date-to" class="mb-1 block text-xs font-medium text-slate-500">
                    Sampai tanggal
                </label>
                <input
                    id="date-to"
                    v-model="dateTo"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="item-filter" class="mb-1 block text-xs font-medium text-slate-500">
                    Barang
                </label>
                <select
                    id="item-filter"
                    v-model="itemId"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Semua barang</option>
                    <option v-for="item in items" :key="item.id" :value="item.id">
                        {{ item.code }} — {{ item.name }}
                    </option>
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

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :rows="documents.data"
                empty-message="Belum ada transaksi yang sesuai filter."
            >
                <template #cell-occurred_on="{ row }">
                    <span class="text-slate-700">{{ formatDate(row.occurred_on) }}</span>
                </template>

                <template #cell-type="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="typeClasses[row.type] ?? 'bg-slate-100 text-slate-500 ring-slate-200'"
                    >
                        {{ typeLabels[row.type] ?? row.type }}
                    </span>
                </template>

                <template #cell-reference_number="{ row }">
                    <span class="text-slate-600">{{ row.reference_number || '-' }}</span>
                </template>

                <template #cell-notes="{ row }">
                    <span class="line-clamp-2 max-w-xs text-slate-600">{{ row.notes || '-' }}</span>
                </template>

                <template #cell-stock_movements_count="{ row }">
                    <span class="tabular-nums text-slate-700">{{ row.stock_movements_count }}</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link
                            :href="`/stock/documents/${row.id}`"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Detail
                        </Link>
                    </div>
                </template>

                <template #pagination>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">
                            Menampilkan {{ documents.from ?? 0 }}–{{ documents.to ?? 0 }} dari
                            {{ documents.total ?? 0 }} transaksi
                        </p>

                        <div v-if="documents.links.length > 3" class="flex flex-wrap gap-1">
                            <template v-for="(link, index) in documents.links" :key="index">
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                                    :class="
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                    "
                                    v-html="link.label"
                                />
                                <span
                                    v-else
                                    class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-400"
                                    v-html="link.label"
                                />
                            </template>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
