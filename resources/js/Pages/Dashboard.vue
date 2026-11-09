<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();

const lowStock = computed(() => page.props.badges?.low_stock ?? 0);
const expiring = computed(() => page.props.badges?.expiring ?? 0);
const expired = computed(() => page.props.badges?.expired ?? 0);
</script>

<template>
    <AppLayout title="Dashboard">
        <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                Ringkasan Bengkel
            </p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                Dashboard
            </h1>
            <p class="mt-3 max-w-2xl text-slate-600">
                Pantau kondisi stok dan mulai catat barang masuk atau keluar dari menu di atas.
            </p>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-sm font-medium text-amber-700">Stok menipis</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-900">{{ lowStock }}</p>
                </div>
                <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
                    <p class="text-sm font-medium text-orange-700">Mendekati kedaluwarsa</p>
                    <p class="mt-2 text-2xl font-semibold text-orange-900">{{ expiring }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <p class="text-sm font-medium text-red-700">Sudah kedaluwarsa</p>
                    <p class="mt-2 text-2xl font-semibold text-red-900">{{ expired }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <p class="text-sm font-medium text-slate-500">Penjualan bulan ini</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">Rp0</p>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Mulai dari sini</h2>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                Data barang contoh sudah tersedia di environment local. Gunakan menu Barang untuk
                melihat daftar stok, lalu catat transaksi harian dari menu Barang Masuk dan Barang
                Keluar.
            </p>
        </div>
    </AppLayout>
</template>
