<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItemForm from '../../Components/ItemForm.vue';

defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    code: '',
    name: '',
    category_id: '',
    brand: '',
    unit: 'pcs',
    purchase_price: 0,
    sale_price: 0,
    min_stock: 0,
    shelf_location: '',
    has_expiry: false,
    is_active: true,
});

function submit() {
    form.post('/items');
}
</script>

<template>
    <AppLayout title="Tambah Barang">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Barang</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Tambah Barang</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Isi data barang baru. Kode dapat dikosongkan untuk dibuat otomatis.
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
                <ItemForm
                    :form="form"
                    :categories="categories"
                    submit-label="Simpan Barang"
                    cancel-href="/items"
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
