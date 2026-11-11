<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const props = defineProps({
    expiryWarningDays: {
        type: Number,
        required: true,
    },
});

const form = useForm({
    expiry_warning_days: props.expiryWarningDays,
});

function submit() {
    form.put('/settings', {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="Pengaturan">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Konfigurasi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Pengaturan</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Atur batas hari peringatan kedaluwarsa yang tampil di dashboard dan lencana.
                    </p>
                </div>

                <Link
                    href="/"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <form
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                @submit.prevent="submit"
            >
                <div>
                    <label for="expiry-warning-days" class="mb-1 block text-sm font-medium text-slate-700">
                        Batas Hari Peringatan Kedaluwarsa
                    </label>
                    <div class="flex">
                        <input
                            id="expiry-warning-days"
                            v-model="form.expiry_warning_days"
                            type="number"
                            min="1"
                            max="365"
                            step="1"
                            required
                            class="w-full rounded-l-xl border border-r-0 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            :class="
                                form.errors.expiry_warning_days
                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                    : 'border-slate-300'
                            "
                        />
                        <span
                            class="flex items-center rounded-r-xl border border-slate-300 bg-slate-50 px-3 text-xs text-slate-500"
                        >
                            hari
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Batch dengan tanggal kedaluwarsa dalam rentang ini akan ditandai mendekati kedaluwarsa.
                    </p>
                    <p v-if="form.errors.expiry_warning_days" class="mt-1 text-sm text-red-600">
                        {{ form.errors.expiry_warning_days }}
                    </p>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
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
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
