<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatDate } from '../../utils/formatters';

const props = defineProps({
    purchaseOrders: {
        type: Object,
        required: true,
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const status = ref(props.filters.status ?? '');
const supplierId = ref(props.filters.supplier_id ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'ordered', label: 'Dipesan' },
    { value: 'partially_received', label: 'Diterima sebagian' },
    { value: 'completed', label: 'Selesai' },
    { value: 'cancelled', label: 'Dibatalkan' },
];

const statusLabels = Object.fromEntries(statusOptions.map((option) => [option.value, option.label]));

const statusClasses = {
    draft: 'bg-slate-100 text-slate-700 ring-slate-200',
    ordered: 'bg-blue-50 text-blue-700 ring-blue-200',
    partially_received: 'bg-amber-50 text-amber-700 ring-amber-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};

const columns = [
    { key: 'number', label: 'Nomor' },
    { key: 'supplier', label: 'Supplier' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Dibuat' },
    { key: 'items', label: 'Barang', class: 'text-right' },
    { key: 'actions', label: 'Aksi', class: 'text-right' },
];

function applyFilters() {
    const params = {};

    if (status.value !== '') {
        params.status = status.value;
    }

    if (supplierId.value !== '') {
        params.supplier_id = supplierId.value;
    }

    if (dateFrom.value !== '') {
        params.date_from = dateFrom.value;
    }

    if (dateTo.value !== '') {
        params.date_to = dateTo.value;
    }

    router.get('/purchase-orders', params, { preserveState: true, replace: true });
}

function resetFilters() {
    status.value = '';
    supplierId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/purchase-orders', {}, { preserveState: true, replace: true });
}

watch(
    () => props.filters,
    (filters) => {
        status.value = filters.status ?? '';
        supplierId.value = filters.supplier_id ?? '';
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Pesanan Pembelian">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Pembelian</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Pesanan Pembelian</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Buat pesanan ke supplier berdasarkan barang yang menipis, lalu catat penerimaannya.
                </p>
            </div>

            <Link
                href="/purchase-orders/create"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
                Buat Pesanan
            </Link>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5"
            @submit.prevent="applyFilters"
        >
            <select
                v-model="status"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">Semua status</option>
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <select
                v-model="supplierId"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">Semua supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                    {{ supplier.name }}{{ supplier.is_active ? '' : ' (nonaktif)' }}
                </option>
            </select>

            <input
                v-model="dateFrom"
                type="date"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                aria-label="Dari tanggal"
            />
            <input
                v-model="dateTo"
                type="date"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                aria-label="Sampai tanggal"
            />

            <div class="flex gap-2">
                <button
                    type="submit"
                    class="flex-1 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                >
                    Filter
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </form>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :rows="purchaseOrders.data"
                empty-message="Belum ada pesanan pembelian."
            >
                <template #cell-number="{ row }">
                    <Link
                        :href="`/purchase-orders/${row.id}`"
                        class="font-mono text-sm font-medium text-slate-900 hover:text-blue-700"
                    >
                        {{ row.number }}
                    </Link>
                </template>

                <template #cell-supplier="{ row }">
                    <span class="text-slate-600">{{ row.supplier?.name ?? '-' }}</span>
                </template>

                <template #cell-status="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="statusClasses[row.status] ?? 'bg-slate-100 text-slate-500 ring-slate-200'"
                    >
                        {{ statusLabels[row.status] ?? row.status }}
                    </span>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-slate-600">{{ formatDate(row.created_at) }}</span>
                </template>

                <template #cell-items="{ row }">
                    <span class="text-slate-600">{{ row.items_count }} barang</span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <Link
                            :href="`/purchase-orders/${row.id}`"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Detail
                        </Link>
                    </div>
                </template>

                <template #pagination>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">
                            Menampilkan {{ purchaseOrders.from ?? 0 }}–{{ purchaseOrders.to ?? 0 }} dari
                            {{ purchaseOrders.total ?? 0 }} pesanan
                        </p>

                        <div v-if="purchaseOrders.links.length > 3" class="flex flex-wrap gap-1">
                            <template v-for="(link, index) in purchaseOrders.links" :key="index">
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
