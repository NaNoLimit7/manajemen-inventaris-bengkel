<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import MoneyInput from '../../Components/MoneyInput.vue';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    purchaseOrder: {
        type: Object,
        required: true,
    },
    defaultDate: {
        type: String,
        required: true,
    },
});

const remainingQuantity = (line) =>
    Math.max(Number(line.quantity_ordered) - Number(line.quantity_received), 0);

const form = useForm({
    occurred_on: props.defaultDate,
    reference_number: '',
    notes: '',
    lines: props.purchaseOrder.items.map((line) => ({
        purchase_order_item_id: line.id,
        item_id: line.item_id,
        item_name: line.item?.name ?? '',
        unit: line.item?.unit ?? '',
        has_expiry: Boolean(line.item?.has_expiry),
        quantity_ordered: Number(line.quantity_ordered),
        quantity_received: Number(line.quantity_received),
        quantity: remainingQuantity(line),
        unit_cost: Number(line.unit_price),
        expiry_date: '',
    })),
});

const lineError = (index, field) => form.errors[`lines.${index}.${field}`] ?? '';

const totalCost = computed(() =>
    form.lines.reduce(
        (sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.unit_cost) || 0),
        0,
    ),
);

function submit() {
    form.transform(() => ({
        occurred_on: form.occurred_on,
        reference_number: form.reference_number,
        notes: form.notes,
        lines: form.lines.map((line) => ({
            purchase_order_item_id: line.purchase_order_item_id,
            quantity: line.quantity,
            unit_cost: line.unit_cost,
            expiry_date: line.expiry_date,
        })),
    })).post(`/purchase-orders/${props.purchaseOrder.id}/receive`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="`Terima ${purchaseOrder.number}`">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Penerimaan PO</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        Terima Barang
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        <span class="font-mono">{{ purchaseOrder.number }}</span> ·
                        {{ purchaseOrder.supplier?.name ?? 'Supplier tidak ditemukan' }}
                    </p>
                </div>

                <Link
                    :href="`/purchase-orders/${purchaseOrder.id}`"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    <p>Periksa kembali data penerimaan yang diisi.</p>
                    <p v-if="form.errors.lines" class="mt-1 font-medium">{{ form.errors.lines }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="receive-date" class="mb-1 block text-sm font-medium text-slate-700">
                                Tanggal Penerimaan
                            </label>
                            <input
                                id="receive-date"
                                v-model="form.occurred_on"
                                type="date"
                                required
                                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.occurred_on
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                            />
                            <p v-if="form.errors.occurred_on" class="mt-1 text-sm text-red-600">
                                {{ form.errors.occurred_on }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="receive-reference" class="mb-1 block text-sm font-medium text-slate-700">
                                Nomor Faktur
                            </label>
                            <input
                                id="receive-reference"
                                v-model="form.reference_number"
                                type="text"
                                maxlength="60"
                                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.reference_number
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                                placeholder="Opsional"
                            />
                            <p v-if="form.errors.reference_number" class="mt-1 text-sm text-red-600">
                                {{ form.errors.reference_number }}
                            </p>
                        </div>

                        <div class="sm:col-span-3">
                            <label for="receive-notes" class="mb-1 block text-sm font-medium text-slate-700">
                                Catatan
                            </label>
                            <textarea
                                id="receive-notes"
                                v-model="form.notes"
                                rows="2"
                                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.notes
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                                placeholder="Opsional"
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                {{ form.errors.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-900">Barang Diterima</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Isi jumlah yang benar-benar diterima. Baris dengan jumlah 0 tidak dicatat.
                    </p>

                    <div class="mt-4 space-y-4">
                        <div
                            v-for="(line, index) in form.lines"
                            :key="line.purchase_order_item_id"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">
                                        {{ line.item_name }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Dipesan {{ formatQuantity(line.quantity_ordered) }} {{ line.unit }}
                                        · sudah diterima {{ formatQuantity(line.quantity_received) }}
                                        · sisa {{ formatQuantity(remainingQuantity(line)) }} {{ line.unit }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label
                                        :for="`receive-quantity-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Jumlah Diterima
                                    </label>
                                    <div class="flex">
                                        <input
                                            :id="`receive-quantity-${index}`"
                                            v-model="line.quantity"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full rounded-l-xl border border-r-0 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :class="
                                                lineError(index, 'quantity')
                                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                                    : 'border-slate-300'
                                            "
                                        />
                                        <span
                                            class="flex items-center rounded-r-xl border border-slate-300 bg-slate-50 px-3 text-xs text-slate-500"
                                        >
                                            {{ line.unit || 'satuan' }}
                                        </span>
                                    </div>
                                    <p v-if="lineError(index, 'quantity')" class="mt-1 text-sm text-red-600">
                                        {{ lineError(index, 'quantity') }}
                                    </p>
                                </div>

                                <MoneyInput
                                    :id="`receive-cost-${index}`"
                                    v-model="line.unit_cost"
                                    label="Harga Beli / Unit"
                                    :error="lineError(index, 'unit_cost')"
                                />

                                <div v-if="line.has_expiry">
                                    <label
                                        :for="`receive-expiry-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Kedaluwarsa
                                    </label>
                                    <input
                                        :id="`receive-expiry-${index}`"
                                        v-model="line.expiry_date"
                                        type="date"
                                        class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        :class="
                                            lineError(index, 'expiry_date')
                                                ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                                : 'border-slate-300'
                                        "
                                    />
                                    <p
                                        v-if="lineError(index, 'expiry_date')"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ lineError(index, 'expiry_date') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Total penerimaan</p>
                        <p class="text-lg font-semibold text-slate-900">{{ formatCurrency(totalCost) }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="`/purchase-orders/${purchaseOrder.id}`"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Penerimaan' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
