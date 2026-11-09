<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
});

const currentStock = computed(() => {
    if (props.item.current_stock !== undefined && props.item.current_stock !== null) {
        return Number(props.item.current_stock);
    }

    return (props.item.batches ?? []).reduce(
        (total, batch) => total + Number(batch.quantity_remaining ?? 0),
        0,
    );
});

const isLowStock = computed(() => {
    const minimum = Number(props.item.min_stock ?? 0);

    return minimum > 0 && currentStock.value < minimum;
});

const canDelete = computed(
    () =>
        (props.item.batches?.length ?? 0) === 0 &&
        (props.item.stock_movements?.length ?? 0) === 0,
);

const batchColumns = [
    { key: 'expiry_date', label: 'Kedaluwarsa' },
    { key: 'received_on', label: 'Diterima' },
    { key: 'quantity_remaining', label: 'Sisa', class: 'text-right' },
];

const movementColumns = [
    { key: 'occurred_on', label: 'Tanggal' },
    { key: 'type', label: 'Jenis' },
    { key: 'quantity', label: 'Jumlah', class: 'text-right' },
    { key: 'notes', label: 'Catatan' },
];

const typeLabels = {
    in: 'Masuk',
    out: 'Keluar',
    adjustment: 'Penyesuaian',
    reversal: 'Koreksi',
};

const purposeLabels = {
    sold: 'Dijual',
    service: 'Dipakai servis',
    workshop_use: 'Pemakaian bengkel',
    damaged_lost: 'Rusak/hilang',
};

function formatSignedQuantity(value) {
    const number = Number(value ?? 0);
    const sign = number > 0 ? '+' : number < 0 ? '-' : '';

    return `${sign}${formatQuantity(Math.abs(number))} ${props.item.unit}`;
}

function destroyItem() {
    if (!window.confirm(`Hapus barang ${props.item.name}? Tindakan ini tidak dapat dibatalkan.`)) {
        return;
    }

    router.delete(`/items/${props.item.id}`);
}
</script>

<template>
    <AppLayout :title="item.name">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                    {{ item.category?.name ?? 'Tanpa kategori' }}
                </p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ item.name }}</h1>
                <p class="mt-1 font-mono text-sm text-slate-500">{{ item.code }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/items"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
                <Link
                    :href="`/items/${item.id}/edit`"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                >
                    Ubah
                </Link>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Stok saat ini</p>
                <p
                    class="mt-2 text-2xl font-semibold tabular-nums"
                    :class="isLowStock ? 'text-red-600' : 'text-slate-900'"
                >
                    {{ formatQuantity(currentStock) }} {{ item.unit }}
                </p>
                <p v-if="isLowStock" class="mt-1 text-xs text-red-500">
                    Di bawah minimum {{ formatQuantity(item.min_stock) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Stok minimum</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">
                    {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Harga jual standar</p>
                <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">
                    {{ formatCurrency(item.sale_price) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Status</p>
                <span
                    class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                    :class="
                        item.is_active
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                            : 'bg-slate-100 text-slate-500 ring-slate-200'
                    "
                >
                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>
        </div>

        <div class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Merek</p>
                <p class="mt-1 text-sm text-slate-800">{{ item.brand || 'Tanpa merek' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Satuan</p>
                <p class="mt-1 text-sm text-slate-800">{{ item.unit }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lokasi rak</p>
                <p class="mt-1 text-sm text-slate-800">{{ item.shelf_location || '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kedaluwarsa</p>
                <p class="mt-1 text-sm text-slate-800">{{ item.has_expiry ? 'Ya' : 'Tidak' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga beli terakhir</p>
                <p class="mt-1 text-sm text-slate-800">{{ formatCurrency(item.purchase_price) }}</p>
            </div>
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Batch Stok</h2>
                <span class="text-sm text-slate-500">{{ item.batches?.length ?? 0 }} batch</span>
            </div>

            <DataTable
                :columns="batchColumns"
                :rows="item.batches ?? []"
                empty-message="Belum ada batch stok untuk barang ini."
            >
                <template #cell-expiry_date="{ row }">
                    <span :class="row.expiry_date ? 'text-slate-700' : 'text-slate-400'">
                        {{ row.expiry_date ? formatDate(row.expiry_date) : 'Tanpa kedaluwarsa' }}
                    </span>
                </template>
                <template #cell-received_on="{ row }">
                    <span class="text-slate-600">{{ formatDate(row.received_on) }}</span>
                </template>
                <template #cell-quantity_remaining="{ row }">
                    <span class="font-medium tabular-nums text-slate-800">
                        {{ formatQuantity(row.quantity_remaining) }} {{ item.unit }}
                    </span>
                </template>
            </DataTable>
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Transaksi Terakhir</h2>
                <Link
                    href="/stock/documents"
                    class="text-sm font-medium text-blue-700 transition hover:text-blue-800"
                >
                    Lihat riwayat
                </Link>
            </div>

            <DataTable
                :columns="movementColumns"
                :rows="item.stock_movements ?? []"
                empty-message="Belum ada transaksi stok untuk barang ini."
            >
                <template #cell-occurred_on="{ row }">
                    <span class="text-slate-600">
                        {{ formatDate(row.stock_document?.occurred_on) }}
                    </span>
                </template>
                <template #cell-type="{ row }">
                    <span class="font-medium text-slate-800">
                        {{ typeLabels[row.stock_document?.type] ?? row.stock_document?.type ?? '-' }}
                    </span>
                    <p v-if="row.stock_document?.purpose" class="mt-0.5 text-xs text-slate-500">
                        {{ purposeLabels[row.stock_document.purpose] ?? row.stock_document.purpose }}
                    </p>
                </template>
                <template #cell-quantity="{ row }">
                    <span
                        class="font-semibold tabular-nums"
                        :class="Number(row.quantity) > 0 ? 'text-emerald-600' : 'text-red-600'"
                    >
                        {{ formatSignedQuantity(row.quantity) }}
                    </span>
                </template>
                <template #cell-notes="{ row }">
                    <span class="text-slate-600">{{ row.stock_document?.notes || '-' }}</span>
                </template>
            </DataTable>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-slate-500">
                {{
                    canDelete
                        ? 'Barang ini belum memiliki riwayat stok dan dapat dihapus.'
                        : 'Barang dengan riwayat stok tidak dapat dihapus. Nonaktifkan lewat Ubah.'
                }}
            </p>
            <button
                v-if="canDelete"
                type="button"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                @click="destroyItem"
            >
                Hapus Barang
            </button>
        </div>
    </AppLayout>
</template>
