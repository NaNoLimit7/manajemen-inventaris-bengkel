<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ReportTabs from '../../Components/ReportTabs.vue';
import { formatDateTime } from '../../utils/formatters';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    events: {
        type: Array,
        default: () => [],
    },
    types: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const event = ref(props.filters.event ?? '');
const type = ref(props.filters.type ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const expanded = reactive({});

const eventLabels = {
    'item.created': 'Barang dibuat',
    'item.updated': 'Barang diubah',
    'item.deleted': 'Barang dihapus',
    'supplier.created': 'Supplier dibuat',
    'supplier.updated': 'Supplier diubah',
    'supplier.deleted': 'Supplier dihapus',
    'customer.created': 'Pelanggan dibuat',
    'customer.updated': 'Pelanggan diubah',
    'customer.deleted': 'Pelanggan dihapus',
    'stock.adjusted': 'Stok disesuaikan',
    'stock.reversed': 'Stok dibatalkan',
};

const eventClasses = {
    created: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    updated: 'bg-blue-50 text-blue-700 ring-blue-200',
    deleted: 'bg-red-50 text-red-700 ring-red-200',
    adjusted: 'bg-amber-50 text-amber-700 ring-amber-200',
    reversed: 'bg-red-50 text-red-700 ring-red-200',
};

function applyFilters() {
    const params = {};

    if (event.value !== '') {
        params.event = event.value;
    }

    if (type.value !== '') {
        params.type = type.value;
    }

    if (dateFrom.value !== '') {
        params.date_from = dateFrom.value;
    }

    if (dateTo.value !== '') {
        params.date_to = dateTo.value;
    }

    router.get('/reports/audit', params, { preserveState: true, replace: true });
}

function resetFilters() {
    event.value = '';
    type.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/reports/audit', {}, { preserveState: true, replace: true });
}

function eventLabel(value) {
    return eventLabels[value] ?? value;
}

function eventClass(value) {
    return eventClasses[value?.split('.').pop()] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
}

function typeLabel(value) {
    return props.types.find((item) => item.value === value)?.label ?? value;
}

function summary(row) {
    if (row.before === null && row.after !== null) {
        return 'Data baru dibuat';
    }

    if (row.before !== null && row.after === null) {
        return 'Data dihapus';
    }

    if (row.before === null) {
        return 'Tanpa detail';
    }

    const changed = Object.keys(row.after ?? {}).filter(
        (key) => JSON.stringify(row.before?.[key]) !== JSON.stringify(row.after?.[key]),
    );

    return changed.length > 0 ? `${changed.length} atribut berubah` : 'Tidak ada perubahan';
}

function toggle(row) {
    expanded[row.id] = !expanded[row.id];
}

function pretty(value) {
    return value === null ? '-' : JSON.stringify(value, null, 2);
}

watch(
    () => props.filters,
    (filters) => {
        event.value = filters.event ?? '';
        type.value = filters.type ?? '';
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
    },
    { deep: true },
);
</script>

<template>
    <AppLayout title="Riwayat Audit">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Laporan</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Riwayat Audit</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Periksa jejak perubahan data barang, supplier, pelanggan, dan dokumen stok.
                </p>
            </div>
        </div>

        <ReportTabs class="mt-4" />

        <form
            class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <div>
                <label for="event-filter" class="mb-1 block text-xs font-medium text-slate-500">
                    Event
                </label>
                <select
                    id="event-filter"
                    v-model="event"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Semua event</option>
                    <option v-for="value in events" :key="value" :value="value">
                        {{ eventLabel(value) }}
                    </option>
                </select>
            </div>

            <div>
                <label for="type-filter" class="mb-1 block text-xs font-medium text-slate-500">
                    Tipe data
                </label>
                <select
                    id="type-filter"
                    v-model="type"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="">Semua tipe</option>
                    <option v-for="item in types" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div>
                <label for="audit-date-from" class="mb-1 block text-xs font-medium text-slate-500">
                    Dari tanggal
                </label>
                <input
                    id="audit-date-from"
                    v-model="dateFrom"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label for="audit-date-to" class="mb-1 block text-xs font-medium text-slate-500">
                    Sampai tanggal
                </label>
                <input
                    id="audit-date-to"
                    v-model="dateTo"
                    type="date"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <button
                type="submit"
                class="self-end rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
            >
                Terapkan
            </button>
            <button
                type="button"
                class="self-end rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
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
                                Waktu
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Event
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Tipe
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                ID
                            </th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:table-cell">
                                Ringkasan
                            </th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Detail
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-if="logs.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada jejak audit yang sesuai filter.
                            </td>
                        </tr>
                        <template v-for="row in logs.data" :key="row.id">
                            <tr class="align-top transition hover:bg-slate-50/70">
                                <td class="whitespace-nowrap px-4 py-4 text-slate-700">
                                    {{ formatDateTime(row.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
                                        :class="eventClass(row.event)"
                                    >
                                        {{ eventLabel(row.event) }}
                                    </span>
                                    <p class="mt-1 font-mono text-[11px] text-slate-400">{{ row.event }}</p>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-4 text-slate-700 sm:table-cell">
                                    {{ typeLabel(row.auditable_type) }}
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-4 font-mono text-xs text-slate-500 sm:table-cell">
                                    #{{ row.auditable_id }}
                                </td>
                                <td class="hidden px-4 py-4 text-slate-600 sm:table-cell">{{ summary(row) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50"
                                        :aria-expanded="expanded[row.id] ? 'true' : 'false'"
                                        @click="toggle(row)"
                                    >
                                        {{ expanded[row.id] ? 'Tutup' : 'Lihat' }}
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="expanded[row.id]" class="bg-slate-50/60">
                                <td colspan="6" class="px-4 py-4">
                                    <div class="grid gap-3 lg:grid-cols-2">
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Sebelum
                                            </p>
                                            <pre class="max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700">{{ pretty(row.before) }}</pre>
                                        </div>
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                Sesudah
                                            </p>
                                            <pre class="max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white p-3 text-xs text-slate-700">{{ pretty(row.after) }}</pre>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        Menampilkan {{ logs.from ?? 0 }}–{{ logs.to ?? 0 }} dari
                        {{ logs.total ?? 0 }} jejak audit
                    </p>

                    <div v-if="logs.links.length > 3" class="flex flex-wrap gap-1">
                        <template v-for="(link, index) in logs.links" :key="index">
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
            </div>
        </div>
    </AppLayout>
</template>
