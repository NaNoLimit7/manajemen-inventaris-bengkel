<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

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

const purposeLabels = {
    sold: 'Dijual',
    service: 'Dipakai servis',
    workshop_use: 'Pemakaian bengkel',
    damaged_lost: 'Rusak/hilang',
};

const movementColumns = [
    { key: 'item', label: 'Barang' },
    { key: 'batch', label: 'Batch' },
    { key: 'quantity', label: 'Jumlah', class: 'text-right' },
    { key: 'price', label: 'Harga', class: 'text-right' },
];

function formatSignedQuantity(movement) {
    const quantity = Number(movement.quantity ?? 0);
    const sign = quantity > 0 ? '+' : quantity < 0 ? '-' : '';

    return `${sign}${formatQuantity(Math.abs(quantity))} ${movement.item?.unit ?? ''}`.trim();
}
</script>

<template>
    <AppLayout :title="`Transaksi #${document.id}`">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Riwayat Transaksi</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                    Detail Transaksi #{{ document.id }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ formatDate(document.occurred_on) }} ·
                    {{ typeLabels[document.type] ?? document.type }}
                    <span v-if="document.purpose">
                        · {{ purposeLabels[document.purpose] ?? document.purpose }}
                    </span>
                </p>
            </div>

            <Link
                href="/stock/documents"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                Kembali
            </Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Jenis</p>
                <span
                    class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                    :class="typeClasses[document.type] ?? 'bg-slate-100 text-slate-500 ring-slate-200'"
                >
                    {{ typeLabels[document.type] ?? document.type }}
                </span>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Tanggal</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ formatDate(document.occurred_on) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Referensi</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ document.reference_number || '-' }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Jumlah baris</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ document.stock_movements?.length ?? 0 }}
                </p>
            </div>
        </div>

        <div
            v-if="document.notes"
            class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ document.notes }}</p>
        </div>

        <div class="mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900">Rincian Barang</h2>
                <span class="text-sm text-slate-500">
                    {{ document.stock_movements?.length ?? 0 }} baris
                </span>
            </div>

            <DataTable
                :columns="movementColumns"
                :rows="document.stock_movements ?? []"
                empty-message="Tidak ada rincian barang pada transaksi ini."
            >
                <template #cell-item="{ row }">
                    <Link
                        :href="`/items/${row.item?.id}`"
                        class="font-medium text-slate-900 hover:text-blue-700"
                    >
                        {{ row.item?.name ?? 'Barang tidak ditemukan' }}
                    </Link>
                    <p class="mt-0.5 font-mono text-xs text-slate-500">
                        {{ row.item?.code ?? '-' }}
                    </p>
                </template>

                <template #cell-batch="{ row }">
                    <span class="text-slate-600">
                        {{
                            row.stock_batch?.expiry_date
                                ? formatDate(row.stock_batch.expiry_date)
                                : 'Tanpa kedaluwarsa'
                        }}
                    </span>
                </template>

                <template #cell-quantity="{ row }">
                    <span
                        class="font-semibold tabular-nums"
                        :class="Number(row.quantity) > 0 ? 'text-emerald-600' : 'text-red-600'"
                    >
                        {{ formatSignedQuantity(row) }}
                    </span>
                </template>

                <template #cell-price="{ row }">
                    <span class="tabular-nums text-slate-700">
                        <template v-if="document.type === 'in'">
                            {{ formatCurrency(row.unit_cost) }}
                        </template>
                        <template v-else-if="document.purpose === 'sold'">
                            {{ formatCurrency(row.unit_price) }}
                        </template>
                        <template v-else>-</template>
                    </span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
