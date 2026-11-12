<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ReportTabs from '../../Components/ReportTabs.vue';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search ?? '');
const categoryId = ref(props.filters.category_id ?? '');
const isActive = ref(props.filters.is_active ?? '');

function applyFilters() {
    const params = {};

    if (search.value.trim() !== '') {
        params.search = search.value.trim();
    }

    if (categoryId.value !== '') {
        params.category_id = categoryId.value;
    }

    if (isActive.value !== '') {
        params.is_active = isActive.value;
    }

    router.get('/reports/stock', params, { preserveState: true, replace: true });
}

function resetFilters() {
    search.value = '';
    categoryId.value = '';
    isActive.value = '';
    router.get('/reports/stock', {}, { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();

    if (search.value.trim() !== '') {
        params.set('search', search.value.trim());
    }

    if (categoryId.value !== '') {
        params.set('category_id', categoryId.value);
    }

    if (isActive.value !== '') {
        params.set('is_active', isActive.value);
    }

    const query = params.toString();

    return `/reports/stock.csv${query ? `?${query}` : ''}`;
}

function isLowStock(item) {
    return Number(item.min_stock ?? 0) > 0 && Number(item.current_stock ?? 0) < Number(item.min_stock);
}

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
        categoryId.value = filters.category_id ?? '';
        isActive.value = filters.is_active ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Laporan Stok">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Laporan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Stok dan Nilai Persediaan</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Pantau stok saat ini dan nilai persediaan berdasarkan harga beli terakhir.
                </p>
            </div>

            <a
                :href="exportUrl()"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                Unduh CSV
            </a>
        </div>

        <ReportTabs class="mt-4" />

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah barang</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ summary.item_count ?? 0 }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Barang sesuai filter</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total nilai persediaan</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-slate-900">
                    {{ formatCurrency(summary.total_inventory_value) }}
                </p>
                <p class="mt-1 text-xs text-slate-500">Stok × harga beli terakhir</p>
            </div>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto_auto]"
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

            <select
                v-model="isActive"
                class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            >
                <option value="">Semua status</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>

            <button
                type="submit"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Terapkan
            </button>
            <button
                type="button"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                @click="resetFilters"
            >
                Reset
            </button>
        </form>

        <div class="mt-6 w-full max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="w-full overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Barang
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Kategori
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Stok
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Minimum
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Nilai Persediaan
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="items.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada barang yang sesuai filter.
                            </td>
                        </tr>
                        <tr v-for="item in items" :key="item.id" class="align-top transition hover:bg-slate-50/70">
                            <td class="px-4 py-4">
                                <p class="font-medium text-slate-900">{{ item.name }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ item.code }}</p>
                                <span
                                    v-if="!item.is_active"
                                    class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500"
                                >
                                    Nonaktif
                                </span>
                                <Link
                                    :href="`/reports/stock-card/${item.id}`"
                                    class="mt-1 block text-xs font-medium text-blue-600 sm:hidden"
                                >
                                    Kartu Stok
                                </Link>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-slate-600 sm:table-cell">
                                {{ item.category?.name ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <span
                                    class="font-medium tabular-nums"
                                    :class="isLowStock(item) ? 'text-red-600' : 'text-slate-800'"
                                >
                                    {{ formatQuantity(item.current_stock) }} {{ item.unit }}
                                </span>
                                <p v-if="isLowStock(item)" class="mt-0.5 text-xs text-red-500">Di bawah minimum</p>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-right tabular-nums text-slate-600 sm:table-cell">
                                {{ formatQuantity(item.min_stock) }} {{ item.unit }}
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-right tabular-nums text-slate-700 sm:table-cell">
                                {{ formatCurrency(item.inventory_value) }}
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-4 text-right sm:table-cell">
                                <Link
                                    :href="`/reports/stock-card/${item.id}`"
                                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                >
                                    Kartu Stok
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
