<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.customer.name,
    phone: props.customer.phone ?? '',
    notes: props.customer.notes ?? '',
    is_active: props.customer.is_active,
});

function submit() {
    form.put(`/customers/${props.customer.id}`);
}
</script>

<template>
    <AppLayout title="Ubah Pelanggan">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Data</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Ubah Pelanggan</h1>
                </div>

                <Link
                    href="/customers"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <form
                class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <label for="customer-name" class="mb-1 block text-sm font-medium text-slate-700">
                        Nama Pelanggan
                    </label>
                    <input
                        id="customer-name"
                        v-model="form.name"
                        type="text"
                        maxlength="120"
                        required
                        class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        :class="
                            form.errors.name
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                : 'border-slate-300'
                        "
                    />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="customer-phone" class="mb-1 block text-sm font-medium text-slate-700">
                        Nomor Telepon
                    </label>
                    <input
                        id="customer-phone"
                        v-model="form.phone"
                        type="text"
                        maxlength="30"
                        class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        :class="
                            form.errors.phone
                                ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                : 'border-slate-300'
                        "
                        placeholder="Opsional"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                </div>

                <div>
                    <label for="customer-notes" class="mb-1 block text-sm font-medium text-slate-700">
                        Catatan
                    </label>
                    <textarea
                        id="customer-notes"
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
                    <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">{{ form.errors.notes }}</p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Pelanggan aktif
                </label>
                <p class="text-xs text-slate-500">
                    Pelanggan dengan riwayat penjualan tidak dapat dihapus. Nonaktifkan agar tidak muncul di pilihan baru.
                </p>

                <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                    <Link
                        href="/customers"
                        class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
