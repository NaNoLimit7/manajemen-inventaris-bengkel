<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import DataTable from '../../Components/DataTable.vue';

const props = defineProps({
    suppliers: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const search = ref(props.filters.search ?? '');

const columns = [
    { key: 'name', label: 'Nama' },
    { key: 'contact', label: 'Kontak' },
    { key: 'history', label: 'Riwayat' },
    { key: 'is_active', label: 'Status' },
    { key: 'actions', label: 'Aksi', class: 'text-right' },
];

function applyFilters() {
    router.get(
        '/suppliers',
        search.value.trim() === '' ? {} : { search: search.value.trim() },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    search.value = '';
    router.get('/suppliers', {}, { preserveState: true, replace: true });
}

function destroy(supplier) {
    if (!window.confirm(`Hapus supplier ${supplier.name}?`)) {
        return;
    }

    router.delete(`/suppliers/${supplier.id}`, { preserveScroll: true });
}

watch(
    () => props.filters,
    (filters) => {
        search.value = filters.search ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Supplier">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Data</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Supplier</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Kelola supplier untuk barang masuk dan pesanan pembelian.
                </p>
            </div>

            <Link
                href="/suppliers/create"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
            >
                Tambah Supplier
            </Link>
        </div>

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="search"
                type="search"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="Cari nama atau kontak supplier"
            />

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
                :rows="suppliers.data"
                empty-message="Belum ada supplier. Tambahkan supplier pertama untuk mulai mencatat pembelian."
            >
                <template #cell-name="{ row }">
                    <span class="font-medium text-slate-900">{{ row.name }}</span>
                    <p v-if="row.notes" class="mt-0.5 text-xs text-slate-500">{{ row.notes }}</p>
                </template>

                <template #cell-contact="{ row }">
                    <span class="text-slate-600">{{ row.contact || '-' }}</span>
                </template>

                <template #cell-history="{ row }">
                    <span class="text-xs text-slate-500">
                        {{ row.stock_documents_count }} dokumen · {{ row.purchase_orders_count }} PO
                    </span>
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
                            :href="`/suppliers/${row.id}/edit`"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Ubah
                        </Link>
                        <button
                            type="button"
                            class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                            @click="destroy(row)"
                        >
                            Hapus
                        </button>
                    </div>
                </template>

                <template #pagination>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">
                            Menampilkan {{ suppliers.from ?? 0 }}–{{ suppliers.to ?? 0 }} dari
                            {{ suppliers.total ?? 0 }} supplier
                        </p>

                        <div v-if="suppliers.links.length > 3" class="flex flex-wrap gap-1">
                            <template v-for="(link, index) in suppliers.links" :key="index">
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
