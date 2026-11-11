<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatCurrency, formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    purchaseOrder: {
        type: Object,
        required: true,
    },
});

const statusLabels = {
    draft: 'Draft',
    ordered: 'Dipesan',
    partially_received: 'Diterima sebagian',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

const statusClasses = {
    draft: 'bg-slate-100 text-slate-700 ring-slate-200',
    ordered: 'bg-blue-50 text-blue-700 ring-blue-200',
    partially_received: 'bg-amber-50 text-amber-700 ring-amber-200',
    completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
};

const itemColumns = [
    { key: 'item', label: 'Barang' },
    { key: 'ordered', label: 'Dipesan', class: 'text-right' },
    { key: 'received', label: 'Diterima', class: 'text-right' },
    { key: 'remaining', label: 'Sisa', class: 'text-right' },
    { key: 'unit_price', label: 'Harga', class: 'text-right' },
    { key: 'subtotal', label: 'Subtotal', class: 'text-right' },
];

const hasReceipt = computed(() =>
    (props.purchaseOrder.items ?? []).some((line) => Number(line.quantity_received) > 0),
);

const canEdit = computed(() => props.purchaseOrder.status === 'draft');
const canOrder = computed(() => props.purchaseOrder.status === 'draft');
const canReceive = computed(() =>
    ['ordered', 'partially_received'].includes(props.purchaseOrder.status),
);
const canCancel = computed(
    () =>
        props.purchaseOrder.status === 'draft' ||
        (props.purchaseOrder.status === 'ordered' && !hasReceipt.value),
);

const total = computed(() =>
    (props.purchaseOrder.items ?? []).reduce(
        (sum, line) => sum + Number(line.quantity_ordered) * Number(line.unit_price),
        0,
    ),
);

const remainingQuantity = (line) =>
    Math.max(Number(line.quantity_ordered) - Number(line.quantity_received), 0);

function markOrdered() {
    if (!window.confirm('Tandai pesanan ini sudah dipesan ke supplier?')) {
        return;
    }

    router.post(`/purchase-orders/${props.purchaseOrder.id}/order`, {}, { preserveScroll: true });
}

function cancelOrder() {
    if (!window.confirm('Batalkan pesanan pembelian ini?')) {
        return;
    }

    router.post(`/purchase-orders/${props.purchaseOrder.id}/cancel`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="`Pesanan ${purchaseOrder.number}`">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Pembelian</p>
                <h1 class="mt-1 font-mono text-2xl font-bold tracking-tight text-slate-900">
                    {{ purchaseOrder.number }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ purchaseOrder.supplier?.name ?? 'Supplier tidak ditemukan' }}
                    <span v-if="purchaseOrder.ordered_on">
                        · dipesan {{ formatDate(purchaseOrder.ordered_on) }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/purchase-orders"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
                <Link
                    v-if="canEdit"
                    :href="`/purchase-orders/${purchaseOrder.id}/edit`"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Ubah
                </Link>
                <button
                    v-if="canOrder"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                    @click="markOrdered"
                >
                    Tandai Dipesan
                </button>
                <Link
                    v-if="canReceive"
                    :href="`/purchase-orders/${purchaseOrder.id}/receive`"
                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                >
                    Terima Barang
                </Link>
                <button
                    v-if="canCancel"
                    type="button"
                    class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
                    @click="cancelOrder"
                >
                    Batalkan
                </button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Status</p>
                <span
                    class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                    :class="statusClasses[purchaseOrder.status] ?? 'bg-slate-100 text-slate-500 ring-slate-200'"
                >
                    {{ statusLabels[purchaseOrder.status] ?? purchaseOrder.status }}
                </span>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Dibuat</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ formatDate(purchaseOrder.created_at) }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Jumlah barang</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">
                    {{ purchaseOrder.items?.length ?? 0 }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Total pesanan</p>
                <p class="mt-2 text-lg font-semibold text-slate-900">{{ formatCurrency(total) }}</p>
            </div>
        </div>

        <div
            v-if="purchaseOrder.notes"
            class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan</p>
            <p class="mt-1 text-sm leading-6 text-slate-700">{{ purchaseOrder.notes }}</p>
        </div>

        <div class="mt-6">
            <h2 class="mb-3 text-base font-semibold text-slate-900">Rincian Barang</h2>

            <DataTable
                :columns="itemColumns"
                :rows="purchaseOrder.items ?? []"
                empty-message="Pesanan ini belum memiliki barang."
            >
                <template #cell-item="{ row }">
                    <Link
                        v-if="row.item"
                        :href="`/items/${row.item.id}`"
                        class="font-medium text-slate-900 hover:text-blue-700"
                    >
                        {{ row.item.name }}
                    </Link>
                    <span v-else class="text-slate-500">Barang tidak ditemukan</span>
                    <p class="mt-0.5 font-mono text-xs text-slate-500">
                        {{ row.item?.code ?? '-' }}
                    </p>
                </template>

                <template #cell-ordered="{ row }">
                    <span class="tabular-nums text-slate-700">
                        {{ formatQuantity(row.quantity_ordered) }} {{ row.item?.unit ?? '' }}
                    </span>
                </template>

                <template #cell-received="{ row }">
                    <span class="tabular-nums text-slate-700">
                        {{ formatQuantity(row.quantity_received) }} {{ row.item?.unit ?? '' }}
                    </span>
                </template>

                <template #cell-remaining="{ row }">
                    <span class="tabular-nums text-slate-700">
                        {{ formatQuantity(remainingQuantity(row)) }} {{ row.item?.unit ?? '' }}
                    </span>
                </template>

                <template #cell-unit_price="{ row }">
                    <span class="tabular-nums text-slate-700">{{ formatCurrency(row.unit_price) }}</span>
                </template>

                <template #cell-subtotal="{ row }">
                    <span class="font-medium tabular-nums text-slate-900">
                        {{ formatCurrency(Number(row.quantity_ordered) * Number(row.unit_price)) }}
                    </span>
                </template>
            </DataTable>
        </div>

        <div v-if="purchaseOrder.stock_documents?.length" class="mt-6">
            <h2 class="mb-3 text-base font-semibold text-slate-900">Penerimaan</h2>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <ul class="divide-y divide-slate-100">
                    <li
                        v-for="document in purchaseOrder.stock_documents"
                        :key="document.id"
                        class="flex items-center justify-between gap-3 px-5 py-3"
                    >
                        <span>
                            <Link
                                :href="`/stock/documents/${document.id}`"
                                class="text-sm font-medium text-slate-900 hover:text-blue-700"
                            >
                                Dokumen #{{ document.id }}
                            </Link>
                            <span class="ml-2 text-xs text-slate-500">
                                {{ formatDate(document.occurred_on) }}
                            </span>
                        </span>
                        <span
                            v-if="document.reference_number"
                            class="text-xs text-slate-500"
                        >
                            {{ document.reference_number }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
