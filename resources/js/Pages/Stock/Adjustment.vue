<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItemSearchSelect from '../../Components/ItemSearchSelect.vue';
import { formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    defaultDate: {
        type: String,
        required: true,
    },
});

const form = useForm({
    occurred_on: props.defaultDate,
    notes: '',
    lines: [createLine()],
});

function createLine() {
    return {
        item_id: '',
        stock_batch_id: '',
        counted_quantity: 0,
    };
}

const findItem = (itemId) =>
    props.items.find((item) => String(item.id) === String(itemId)) ?? null;

const lineItem = (line) => findItem(line.item_id);

const lineBatch = (line) => {
    const item = lineItem(line);

    if (!item) {
        return null;
    }

    return item.batches.find((batch) => String(batch.id) === String(line.stock_batch_id)) ?? null;
};

function selectItem(line, itemId) {
    line.item_id = itemId;
    line.stock_batch_id = '';

    const item = findItem(itemId);

    if (!item) {
        return;
    }

    if (item.batches.length === 1) {
        line.stock_batch_id = item.batches[0].id;
        line.counted_quantity = item.batches[0].quantity_remaining;
    }
}

const batchOptions = (line) => lineItem(line)?.batches ?? [];

const lineCurrentQuantity = (line) => {
    const batch = lineBatch(line);

    if (batch) {
        return batch.quantity_remaining;
    }

    return lineItem(line)?.current_stock ?? 0;
};

const lineDelta = (line) => {
    const item = lineItem(line);

    if (!item) {
        return 0;
    }

    return (Number(line.counted_quantity) || 0) - lineCurrentQuantity(line);
};

function addLine() {
    form.lines.push(createLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

const lineError = (index, field) => form.errors[`lines.${index}.${field}`] ?? '';

const hasDelta = computed(() =>
    form.lines.some((line) => lineItem(line) && lineDelta(line) !== 0),
);

function submit() {
    form.post('/stock/adjustments', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="Penyesuaian Stok">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Transaksi Stok</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Penyesuaian Stok</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Catat hasil hitung fisik per batch dan tuliskan alasan penyesuaian.
                    </p>
                </div>

                <Link
                    href="/"
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
                    <p>Periksa kembali data penyesuaian yang diisi.</p>
                    <p v-if="form.errors.lines" class="mt-1 font-medium">
                        {{ form.errors.lines }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="occurred-on" class="mb-1 block text-sm font-medium text-slate-700">
                                Tanggal Penyesuaian
                            </label>
                            <input
                                id="occurred-on"
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
                            <label for="adjustment-notes" class="mb-1 block text-sm font-medium text-slate-700">
                                Alasan Penyesuaian
                            </label>
                            <textarea
                                id="adjustment-notes"
                                v-model="form.notes"
                                rows="2"
                                required
                                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.notes
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                                placeholder="Contoh: hasil stok opname bulanan"
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                {{ form.errors.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Daftar Barang</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Masukkan jumlah fisik hasil hitung. Selisih nol tidak membuat pergerakan stok.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addLine"
                        >
                            Tambah Baris
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(line, index) in form.lines"
                            :key="index"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">
                                    Barang {{ index + 1 }}
                                </h3>
                                <button
                                    v-if="form.lines.length > 1"
                                    type="button"
                                    class="text-sm font-medium text-red-600 transition hover:text-red-700"
                                    @click="removeLine(index)"
                                >
                                    Hapus
                                </button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <ItemSearchSelect
                                        :model-value="line.item_id"
                                        :items="items"
                                        :error="lineError(index, 'item_id')"
                                        @update:model-value="selectItem(line, $event)"
                                    />
                                </div>

                                <div v-if="lineItem(line)">
                                    <label
                                        :for="`batch-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Batch Stok
                                    </label>
                                    <select
                                        v-if="lineItem(line).has_expiry"
                                        :id="`batch-${index}`"
                                        v-model="line.stock_batch_id"
                                        required
                                        class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                        :class="
                                            lineError(index, 'stock_batch_id')
                                                ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                                : 'border-slate-300'
                                        "
                                    >
                                        <option value="" disabled>Pilih batch</option>
                                        <option
                                            v-for="batch in batchOptions(line)"
                                            :key="batch.id"
                                            :value="batch.id"
                                        >
                                            {{ batch.expiry_date ? formatDate(batch.expiry_date) : 'Tanpa kedaluwarsa' }}
                                            — sisa {{ formatQuantity(batch.quantity_remaining) }}
                                            {{ lineItem(line).unit }}
                                        </option>
                                    </select>
                                    <div
                                        v-else
                                        class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                    >
                                        Batch tanpa kedaluwarsa
                                    </div>
                                    <p
                                        v-if="lineError(index, 'stock_batch_id')"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ lineError(index, 'stock_batch_id') }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        :for="`counted-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Hasil Hitung Fisik
                                    </label>
                                    <div class="flex">
                                        <input
                                            :id="`counted-${index}`"
                                            v-model="line.counted_quantity"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            required
                                            class="w-full rounded-l-xl border border-r-0 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :class="
                                                lineError(index, 'counted_quantity')
                                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                                    : 'border-slate-300'
                                            "
                                        />
                                        <span
                                            class="flex items-center rounded-r-xl border border-slate-300 bg-slate-50 px-3 text-xs text-slate-500"
                                        >
                                            {{ lineItem(line)?.unit ?? 'satuan' }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="lineError(index, 'counted_quantity')"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ lineError(index, 'counted_quantity') }}
                                    </p>
                                </div>

                                <div
                                    v-if="lineItem(line)"
                                    class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:col-span-2"
                                >
                                    Stok tercatat:
                                    <span class="font-medium text-slate-800">
                                        {{ formatQuantity(lineCurrentQuantity(line)) }}
                                        {{ lineItem(line).unit }}
                                    </span>
                                    <span class="mx-2 text-slate-300">·</span>
                                    Selisih:
                                    <span
                                        class="font-semibold"
                                        :class="lineDelta(line) === 0 ? 'text-slate-700' : lineDelta(line) > 0 ? 'text-emerald-700' : 'text-red-700'"
                                    >
                                        {{ lineDelta(line) > 0 ? '+' : '' }}{{ formatQuantity(lineDelta(line)) }}
                                        {{ lineItem(line).unit }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        <span v-if="hasDelta">Selisih akan dicatat sebagai pergerakan stok.</span>
                        <span v-else>Belum ada selisih yang akan dicatat.</span>
                    </p>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            href="/"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Penyesuaian' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
