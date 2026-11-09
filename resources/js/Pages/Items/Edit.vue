<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItemForm from '../../Components/ItemForm.vue';

const props = defineProps({
    item: {
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
});

const form = useForm({
    code: props.item.code ?? '',
    name: props.item.name ?? '',
    category_id: props.item.category_id ?? '',
    brand: props.item.brand ?? '',
    unit: props.item.unit ?? 'pcs',
    purchase_price: props.item.purchase_price ?? 0,
    sale_price: props.item.sale_price ?? 0,
    min_stock: props.item.min_stock ?? 0,
    shelf_location: props.item.shelf_location ?? '',
    has_expiry: Boolean(props.item.has_expiry),
    is_active: Boolean(props.item.is_active),
});

function submit() {
    form.put(`/items/${props.item.id}`);
}
</script>

<template>
    <AppLayout :title="`Ubah ${item.name}`">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Master Barang</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Ubah Barang</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Perbarui data {{ item.name }} ({{ item.code }}).
                    </p>
                </div>

                <Link
                    :href="`/items/${item.id}`"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <ItemForm
                    :form="form"
                    :categories="categories"
                    :has-stock-history="hasStockHistory"
                    submit-label="Simpan Perubahan"
                    :cancel-href="`/items/${item.id}`"
                    @submit="submit"
                />
            </div>
        </div>
    </AppLayout>
</template>
