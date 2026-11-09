<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItemSearchSelect from '../../Components/ItemSearchSelect.vue';
import { formatQuantity } from '../../utils/formatters';

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
    purpose: '',
    notes: '',
    lines: [createLine()],
});

function createLine() {
    return {
        item_id: '',
        quantity: 1,
    };
}

const findItem = (itemId) =>
    props.items.find((item) => String(item.id) === String(itemId)) ?? null;

const lineItem = (line) => findItem(line.item_id);

const purposeOptions = [
    { value: 'service', label: 'Dipakai servis' },
    { value: 'workshop_use', label: 'Pemakaian bengkel' },
    { value: 'damaged_lost', label: 'Rusak/hilang' },
];

function selectItem(line, itemId) {
    line.item_id = itemId;
}

function addLine() {
    form.lines.push(createLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

const lineError = (index, field) => form.errors[`lines.${index}.${field}`] ?? '';

const lineExceedsStock = (line) => {
    const item = lineItem(line);

    return item ? Number(line.quantity) > Number(item.current_stock) : false;
};

function submit() {
    form.post('/stock/out', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="Barang Keluar">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Transaksi Stok</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Barang Keluar</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Catat barang yang dipakai servis, pemakaian bengkel, atau rusak/hilang.
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
                    <p>Periksa kembali data barang keluar yang diisi.</p>
                    <p v-if="form.errors.lines" class="mt-1 font-medium">
                        {{ form.errors.lines }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="occurred-on" class="mb-1 block text-sm font-medium text-slate-700">
                                Tanggal Transaksi
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
                            <label for="stock-out-purpose" class="mb-1 block text-sm font-medium text-slate-700">
                                Tujuan
                            </label>
                            <select
                                id="stock-out-purpose"
                                v-model="form.purpose"
                                required
                                class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.purpose
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                            >
                                <option value="" disabled>Pilih tujuan</option>
                                <option
                                    v-for="option in purposeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">
                                Penjualan ke pelanggan akan ditambahkan pada tahap berikutnya.
                            </p>
                            <p v-if="form.errors.purpose" class="mt-1 text-sm text-red-600">
                                {{ form.errors.purpose }}
                            </p>
                        </div>

                        <div class="sm:col-span-3">
                            <label for="stock-out-notes" class="mb-1 block text-sm font-medium text-slate-700">
                                Catatan
                            </label>
                            <textarea
                                id="stock-out-notes"
                                v-model="form.notes"
                                rows="3"
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
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Daftar Barang</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Sistem mengambil batch dengan kedaluwarsa terdekat lebih dulu.
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

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="sm:col-span-2">
                                    <ItemSearchSelect
                                        :model-value="line.item_id"
                                        :items="items"
                                        :error="lineError(index, 'item_id')"
                                        @update:model-value="selectItem(line, $event)"
                                    />
                                </div>

                                <div>
                                    <label
                                        :for="`quantity-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Jumlah
                                    </label>
                                    <div class="flex">
                                        <input
                                            :id="`quantity-${index}`"
                                            v-model="line.quantity"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            class="w-full rounded-l-xl border border-r-0 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :class="
                                                lineError(index, 'quantity') || lineExceedsStock(line)
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
                                        v-if="lineError(index, 'quantity')"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ lineError(index, 'quantity') }}
                                    </p>
                                </div>

                                <div
                                    v-if="lineItem(line)"
                                    class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:col-span-3"
                                >
                                    Stok saat ini:
                                    <span class="font-medium text-slate-800">
                                        {{ formatQuantity(lineItem(line).current_stock) }}
                                        {{ lineItem(line).unit }}
                                    </span>
                                    <span
                                        v-if="lineExceedsStock(line)"
                                        class="ml-2 font-medium text-red-600"
                                    >
                                        Jumlah melebihi stok tersedia.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        Stok tidak boleh negatif. Transaksi akan ditolak jika stok tidak cukup.
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
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Barang Keluar' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
