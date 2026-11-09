<script setup>
import { Link } from '@inertiajs/vue3';
import MoneyInput from './MoneyInput.vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    hasStockHistory: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Simpan',
    },
    cancelHref: {
        type: String,
        default: '/items',
    },
});

defineEmits(['submit']);

const inputClass = (field) => [
    'w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100',
    props.form.errors[field]
        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
        : 'border-slate-300',
];
</script>

<template>
    <form class="space-y-6" @submit.prevent="$emit('submit')">
        <div
            v-if="Object.keys(form.errors).length > 0"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            Periksa kembali data yang diisi.
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="item-code" class="mb-1 block text-sm font-medium text-slate-700">
                    Kode Barang
                </label>
                <input
                    id="item-code"
                    v-model="form.code"
                    type="text"
                    maxlength="40"
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.code
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                    placeholder="Kosongkan untuk kode otomatis"
                />
                <p class="mt-1 text-xs text-slate-500">
                    Kosongkan untuk dibuat otomatis dengan format BRG-00001.
                </p>
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">
                    {{ form.errors.code }}
                </p>
            </div>

            <div>
                <label for="item-name" class="mb-1 block text-sm font-medium text-slate-700">
                    Nama Barang
                </label>
                <input
                    id="item-name"
                    v-model="form.name"
                    type="text"
                    maxlength="150"
                    required
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.name
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                    placeholder="Contoh: Oli Mesin 10W-40"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                    {{ form.errors.name }}
                </p>
            </div>

            <div>
                <label for="item-category" class="mb-1 block text-sm font-medium text-slate-700">
                    Kategori
                </label>
                <select
                    id="item-category"
                    v-model="form.category_id"
                    required
                    class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.category_id
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                >
                    <option value="" disabled>Pilih kategori</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.category_id }}
                </p>
            </div>

            <div>
                <label for="item-brand" class="mb-1 block text-sm font-medium text-slate-700">
                    Merek
                </label>
                <input
                    id="item-brand"
                    v-model="form.brand"
                    type="text"
                    maxlength="80"
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.brand
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                    placeholder="Opsional"
                />
                <p v-if="form.errors.brand" class="mt-1 text-sm text-red-600">
                    {{ form.errors.brand }}
                </p>
            </div>

            <div>
                <label for="item-unit" class="mb-1 block text-sm font-medium text-slate-700">
                    Satuan
                </label>
                <input
                    id="item-unit"
                    v-model="form.unit"
                    type="text"
                    maxlength="20"
                    required
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.unit
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                    placeholder="pcs, botol, liter, set"
                />
                <p v-if="form.errors.unit" class="mt-1 text-sm text-red-600">
                    {{ form.errors.unit }}
                </p>
            </div>

            <div>
                <label for="item-shelf" class="mb-1 block text-sm font-medium text-slate-700">
                    Lokasi Rak
                </label>
                <input
                    id="item-shelf"
                    v-model="form.shelf_location"
                    type="text"
                    maxlength="50"
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.shelf_location
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                    placeholder="Opsional, contoh: RAK-01"
                />
                <p v-if="form.errors.shelf_location" class="mt-1 text-sm text-red-600">
                    {{ form.errors.shelf_location }}
                </p>
            </div>

            <MoneyInput
                id="item-purchase-price"
                v-model="form.purchase_price"
                label="Harga Beli Terakhir"
                :error="form.errors.purchase_price"
            />

            <MoneyInput
                id="item-sale-price"
                v-model="form.sale_price"
                label="Harga Jual Standar"
                :error="form.errors.sale_price"
            />

            <div>
                <label for="item-min-stock" class="mb-1 block text-sm font-medium text-slate-700">
                    Stok Minimum
                </label>
                <input
                    id="item-min-stock"
                    v-model="form.min_stock"
                    type="number"
                    min="0"
                    step="0.01"
                    required
                    class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    :class="
                        form.errors.min_stock
                            ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                            : 'border-slate-300'
                    "
                />
                <p class="mt-1 text-xs text-slate-500">
                    Isi 0 jika tidak ingin peringatan stok menipis.
                </p>
                <p v-if="form.errors.min_stock" class="mt-1 text-sm text-red-600">
                    {{ form.errors.min_stock }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input
                    v-model="form.has_expiry"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    :disabled="hasStockHistory"
                />
                <span>
                    <span class="font-medium">Punya tanggal kedaluwarsa</span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        Aktifkan batch dan peringatan kedaluwarsa untuk barang ini.
                    </span>
                    <span v-if="hasStockHistory" class="mt-1 block text-xs font-medium text-amber-700">
                        Penanda ini tidak dapat diubah karena barang sudah memiliki riwayat stok.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3 text-sm text-slate-700">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                />
                <span>
                    <span class="font-medium">Barang aktif</span>
                    <span class="mt-0.5 block text-xs text-slate-500">
                        Barang nonaktif tidak ditampilkan sebagai pilihan transaksi baru.
                    </span>
                </span>
            </label>
        </div>

        <p v-if="form.errors.has_expiry" class="text-sm text-red-600">
            {{ form.errors.has_expiry }}
        </p>

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <Link
                :href="cancelHref"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
            >
                Batal
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-xl bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ form.processing ? 'Menyimpan...' : submitLabel }}
            </button>
        </div>
    </form>
</template>
