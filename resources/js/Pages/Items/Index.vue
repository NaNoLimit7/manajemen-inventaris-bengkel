<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    items: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? '');

const columns = [
    { key: 'code', label: 'Kode' },
    { key: 'name', label: 'Nama' },
    { key: 'category', label: 'Kategori' },
    { key: 'stock', label: 'Stok' },
    { key: 'sale_price', label: 'Harga Jual', class: 'text-right' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: 'Aksi', class: 'text-right' },
];

const isLowStock = (row) => {
    const stock = Number(row.current_stock ?? 0);
    const minimum = Number(row.min_stock ?? 0);

    return minimum > 0 && stock < minimum;
};

function applyFilters() {
    const params = {};

    if (search.value.trim() !== '') {
        params.search = search.value.trim();
    }

    if (categoryId.value !== '') {
        params.category_id = categoryId.value;
    }

    router.get('/items', params, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    search.value = '';
    categoryId.value = '';
    router.get('/items', {}, { preserveState: true, replace: true });
}

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        categoryId.value = filters.category_id ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Barang">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Barang</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Barang</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Kelola daftar barang, harga, stok minimum, dan status aktif.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    href="/items/import"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
                >
                    Import CSV
                </Link>
                <Link
                    href="/items/create"
                    class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                >
                    Tambah Barang
                </Link>
            </div>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_14rem_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div class="relative">
                <svg
                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fill-rule="evenodd"
                        d="M9 3.5a5.5 5.5 0 1 0 3.472 9.766l3.631 3.63a.75.75 0 1 0 1.06-1.06l-3.63-3.631A5.5 5.5 0 0 0 9 3.5ZM5 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z"
                        clip-rule="evenodd"
                    />
                </svg>
                <input
                    v-model="search"
                    type="search"
                    class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-10 pr-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    placeholder="Cari nama atau kode barang"
                />
            </div>

            <select
                v-model="categoryId"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">Semua kategori</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>

            <button
                type="submit"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Cari
            </button>
            <button
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="resetFilters"
            >
                Reset
            </button>
        </form>

        <div class="mt-6">
            <DataTable
                :columns="columns"
                :rows="items.data"
                empty-message="Belum ada barang. Tambahkan barang pertama untuk mulai mencatat stok."
            >
                <template #cell-code="{ row }">
                    <span class="font-mono text-xs font-medium text-slate-600">{{ row.code }}</span>
                </template>

                <template #cell-name="{ row }">
                    <Link :href="`/items/${row.id}`" class="font-medium text-slate-900 hover:text-blue-700">
                        {{ row.name }}
                    </Link>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ row.brand || 'Tanpa merek' }} · {{ row.unit }}
                    </p>
                </template>

                <template #cell-category="{ row }">
                    <span class="text-slate-600">{{ row.category?.name ?? '-' }}</span>
                </template>

                <template #cell-stock="{ row }">
                    <span
                        class="font-medium tabular-nums"
                        :class="isLowStock(row) ? 'text-red-600' : 'text-slate-800'"
                    >
                        {{ formatQuantity(row.current_stock) }} {{ row.unit }}
                    </span>
                    <p v-if="isLowStock(row)" class="mt-0.5 text-xs text-red-500">
                        Minimal {{ formatQuantity(row.min_stock) }}
                    </p>
                </template>

                <template #cell-sale_price="{ row }">
                    <span class="tabular-nums text-slate-700">{{ formatCurrency(row.sale_price) }}</span>
                </template>

                <template #cell-is_active="{ row }">
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                        :class="
                            row.is_active
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                : 'bg-slate-100 text-slate-500 ring-slate-200'
                        "
                    >
                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-2">
                        <Link
                            :href="`/items/${row.id}`"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Detail
                        </Link>
                        <Link
                            :href="`/items/${row.id}/edit`"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Ubah
                        </Link>
                    </div>
                </template>

                <template #pagination>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">
                            Menampilkan {{ items.from ?? 0 }}–{{ items.to ?? 0 }} dari
                            {{ items.total ?? 0 }} barang
                        </p>

                        <div v-if="items.links.length > 3" class="flex flex-wrap gap-1">
                            <template v-for="(link, index) in items.links" :key="index">
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
