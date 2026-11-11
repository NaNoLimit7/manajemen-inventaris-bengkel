<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const reverseForm = useForm({
    reason: '',
});

const canReverse = computed(
    () =>
        props.document.type !== 'reversal' &&
        !props.document.reversed_by &&
        (props.document.stock_movements?.length ?? 0) > 0,
);

function submitReverse() {
    if (!window.confirm('Batalkan dokumen transaksi ini? Stok akan dikembalikan.')) {
        return;
    }

    reverseForm.post(`/stock/documents/${props.document.id}/reverse`, {
        preserveScroll: true,
    });
}

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

            <div v-if="document.supplier" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Supplier</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ document.supplier.name }}
                </p>
            </div>

            <div v-if="document.customer" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Pelanggan</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ document.customer.name }}
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

        <div
            v-if="document.reversed_by"
            class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-5"
        >
            <p class="text-sm font-semibold text-red-800">Dokumen ini sudah dibatalkan.</p>
            <p class="mt-1 text-sm text-red-700">
                Dokumen pembalik:
                <Link
                    :href="`/stock/documents/${document.reversed_by.id}`"
                    class="font-medium underline hover:text-red-900"
                >
                    #{{ document.reversed_by.id }}
                </Link>
                · {{ formatDate(document.reversed_by.occurred_on) }}
            </p>
        </div>

        <div
            v-if="document.reverses"
            class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-5"
        >
            <p class="text-sm font-semibold text-amber-900">Dokumen pembalik</p>
            <p class="mt-1 text-sm text-amber-800">
                Membatalkan transaksi
                <Link
                    :href="`/stock/documents/${document.reverses.id}`"
                    class="font-medium underline hover:text-amber-900"
                >
                    #{{ document.reverses.id }}
                </Link>
                · {{ formatDate(document.reverses.occurred_on) }}
            </p>
        </div>

        <div
            v-if="canReverse"
            class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <h2 class="text-sm font-semibold text-slate-900">Batalkan Dokumen</h2>
            <p class="mt-1 text-sm text-slate-600">
                Pembatalan membuat dokumen pembalik dan mengembalikan stok. Tindakan ini tidak dapat dibatalkan.
            </p>

            <form class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="submitReverse">
                <div class="flex-1">
                    <label for="reverse-reason" class="mb-1 block text-sm font-medium text-slate-700">
                        Alasan Pembatalan
                    </label>
                    <input
                        id="reverse-reason"
                        v-model="reverseForm.reason"
                        type="text"
                        maxlength="1000"
                        required
                        class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        :class="
                            reverseForm.errors.reason
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                : 'border-slate-300'
                        "
                        placeholder="Contoh: salah input jumlah"
                    />
                    <p v-if="reverseForm.errors.reason" class="mt-1 text-sm text-red-600">
                        {{ reverseForm.errors.reason }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="reverseForm.processing"
                    class="rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ reverseForm.processing ? 'Membatalkan...' : 'Batalkan Dokumen' }}
                </button>
            </form>
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
