<script setup>
import { ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatDate, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    preview: {
        type: Object,
        default: null,
    },
    rowErrors: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    file: null,
});

const preview = ref(props.preview);
const rowErrors = ref(props.rowErrors ?? []);

const previewColumns = [
    { key: 'line', label: 'Baris' },
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'category_name', label: 'Kategori' },
    { key: 'has_expiry', label: 'Kedaluwarsa' },
    { key: 'initial_stock', label: 'Stok Awal', class: 'text-right' },
    { key: 'expiry_date', label: 'Tgl Kedaluwarsa' },
];

watch(
    () => props.preview,
    (value) => {
        preview.value = value;
    },
);

watch(
    () => props.rowErrors,
    (value) => {
        rowErrors.value = value ?? [];
    },
);

function handleFileChange(event) {
    form.file = event.target.files?.[0] ?? null;
    preview.value = null;
    rowErrors.value = [];
}

function submitPreview() {
    if (!form.file) {
        return;
    }

    preview.value = null;
    rowErrors.value = [];

    form.post('/items/import/preview', {
        preserveState: true,
        forceFormData: true,
    });
}

function submitImport() {
    if (!preview.value) {
        return;
    }

    if (
        !window.confirm(
            `Import ${preview.value.summary.total_items} barang dari file ini?`,
        )
    ) {
        return;
    }

    form.post('/items/import', {
        forceFormData: true,
    });
}
</script>

<template>
    <AppLayout title="Import Barang">
        <div class="mx-auto max-w-5xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Barang</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        Import Barang dari CSV
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Masukkan daftar barang dan stok awal sekaligus dari buku catatan.
                    </p>
                </div>

                <Link
                    href="/items"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">1. Pilih File CSV</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Format UTF-8, pemisah koma atau titik koma, maksimal 2 MB.
                        </p>
                    </div>

                    <a
                        href="/items/import/template"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Unduh Template CSV
                    </a>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div>
                        <label for="items-csv" class="mb-1 block text-sm font-medium text-slate-700">
                            File CSV
                        </label>
                        <input
                            id="items-csv"
                            type="file"
                            accept=".csv,text/csv"
                            class="block w-full cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200"
                            @change="handleFileChange"
                        />
                        <p v-if="form.file" class="mt-1 text-xs text-slate-500">
                            File terpilih: {{ form.file.name }}
                        </p>
                        <p v-if="form.errors.file" class="mt-1 text-sm text-red-600">
                            {{ form.errors.file }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="!form.file || form.processing"
                        class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="submitPreview"
                    >
                        {{ form.processing ? 'Memeriksa...' : 'Pratinjau' }}
                    </button>
                </div>

                <p v-if="!preview && rowErrors.length === 0" class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                    Kolom template: <span class="font-mono">code, name, category, brand, unit,
                    purchase_price, sale_price, min_stock, shelf_location, has_expiry,
                    initial_stock, expiry_date</span>. Kategori dapat diisi slug
                    (<span class="font-mono">consumable</span>, <span class="font-mono">routine</span>,
                    <span class="font-mono">major</span>) atau nama kategori.
                </p>
            </div>

            <div
                v-if="rowErrors.length > 0"
                class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-5"
            >
                <h2 class="text-sm font-semibold text-red-800">
                    File belum dapat diimpor
                </h2>
                <p class="mt-1 text-sm text-red-700">
                    Perbaiki baris berikut, lalu unggah ulang file. Tidak ada data yang tersimpan.
                </p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-red-700">
                    <li v-for="error in rowErrors" :key="`${error.line}-${error.message}`">
                        Baris {{ error.line }}: {{ error.message }}
                    </li>
                </ul>
            </div>

            <div v-if="preview" class="mt-4 space-y-4">
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <h2 class="text-sm font-semibold text-emerald-800">
                        Pratinjau valid. Periksa ringkasan sebelum import.
                    </h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white/70 p-4 ring-1 ring-emerald-100">
                            <p class="text-xs font-medium text-emerald-700">Total barang</p>
                            <p class="mt-1 text-2xl font-semibold text-emerald-900">
                                {{ preview.summary.total_items }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-white/70 p-4 ring-1 ring-emerald-100">
                            <p class="text-xs font-medium text-emerald-700">Barang dengan stok awal</p>
                            <p class="mt-1 text-2xl font-semibold text-emerald-900">
                                {{ preview.summary.initial_stock_items }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-white/70 p-4 ring-1 ring-emerald-100">
                            <p class="text-xs font-medium text-emerald-700">Total stok awal</p>
                            <p class="mt-1 text-2xl font-semibold text-emerald-900">
                                {{ formatQuantity(preview.summary.total_initial_stock) }}
                            </p>
                        </div>
                    </div>
                </div>

                <DataTable
                    :columns="previewColumns"
                    :rows="preview.rows"
                    empty-message="Tidak ada baris data."
                >
                    <template #cell-line="{ row }">
                        <span class="text-slate-500">{{ row.line }}</span>
                    </template>
                    <template #cell-code="{ row }">
                        <span class="font-mono text-xs text-slate-600">
                            {{ row.code || 'Otomatis' }}
                        </span>
                    </template>
                    <template #cell-name="{ row }">
                        <span class="font-medium text-slate-800">{{ row.name }}</span>
                    </template>
                    <template #cell-category_name="{ row }">
                        <span class="text-slate-600">{{ row.category_name }}</span>
                    </template>
                    <template #cell-has_expiry="{ row }">
                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                            :class="
                                row.has_expiry
                                    ? 'bg-amber-50 text-amber-700 ring-amber-200'
                                    : 'bg-slate-100 text-slate-500 ring-slate-200'
                            "
                        >
                            {{ row.has_expiry ? 'Ya' : 'Tidak' }}
                        </span>
                    </template>
                    <template #cell-initial_stock="{ row }">
                        <span class="tabular-nums text-slate-700">
                            {{ formatQuantity(row.initial_stock) }}
                        </span>
                    </template>
                    <template #cell-expiry_date="{ row }">
                        <span class="text-slate-600">
                            {{ row.expiry_date ? formatDate(row.expiry_date) : '-' }}
                        </span>
                    </template>
                </DataTable>

                <p v-if="preview.truncated" class="text-sm text-slate-500">
                    Menampilkan 20 baris pertama dari {{ preview.summary.total_items }} baris.
                </p>

                <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-600">
                        Data akan disimpan dalam satu transaksi. Stok awal dibuat sebagai dokumen
                        barang masuk.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            :disabled="form.processing || !form.file"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                            @click="submitPreview"
                        >
                            Pratinjau Ulang
                        </button>
                        <button
                            type="button"
                            :disabled="form.processing || !form.file"
                            class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="submitImport"
                        >
                            {{ form.processing ? 'Mengimpor...' : 'Import Sekarang' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
