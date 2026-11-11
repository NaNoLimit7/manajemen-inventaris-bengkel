<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ItemSearchSelect from '../../Components/ItemSearchSelect.vue';
import MoneyInput from '../../Components/MoneyInput.vue';
import { formatCurrency, formatQuantity } from '../../utils/formatters';

const props = defineProps({
    suppliers: {
        type: Array,
        default: () => [],
    },
    items: {
        type: Array,
        default: () => [],
    },
    suggestions: {
        type: Array,
        default: () => [],
    },
    purchaseOrder: {
        type: Object,
        default: null,
    },
});

const isEditing = computed(() => props.purchaseOrder !== null);

function initialLines() {
    if (isEditing.value) {
        return props.purchaseOrder.items.map((line) => ({
            item_id: line.item_id,
            quantity_ordered: Number(line.quantity_ordered),
            unit_price: Number(line.unit_price),
        }));
    }

    if (props.suggestions.length > 0) {
        return props.suggestions.map((suggestion) => ({
            item_id: suggestion.id,
            quantity_ordered: suggestion.suggested_quantity,
            unit_price: suggestion.purchase_price,
        }));
    }

    return [createLine()];
}

const form = useForm({
    supplier_id: props.purchaseOrder?.supplier_id ?? '',
    notes: props.purchaseOrder?.notes ?? '',
    lines: initialLines(),
});

function createLine() {
    return {
        item_id: '',
        quantity_ordered: 1,
        unit_price: 0,
    };
}

const findItem = (itemId) =>
    props.items.find((item) => String(item.id) === String(itemId)) ?? null;

const lineItem = (line) => findItem(line.item_id);

const lineError = (index, field) => form.errors[`lines.${index}.${field}`] ?? '';

function selectItem(line, itemId) {
    line.item_id = itemId;

    const item = findItem(itemId);

    if (item) {
        line.unit_price = item.purchase_price ?? 0;
    }
}

function addLine() {
    form.lines.push(createLine());
}

function removeLine(index) {
    if (form.lines.length > 1) {
        form.lines.splice(index, 1);
    }
}

const selectedItemIds = computed(() => form.lines.map((line) => Number(line.item_id)));

const remainingSuggestions = computed(() =>
    props.suggestions.filter((suggestion) => !selectedItemIds.value.includes(suggestion.id)),
);

function addSuggestion(suggestion) {
    const existing = form.lines.find(
        (line) => String(line.item_id) === String(suggestion.id),
    );

    if (existing) {
        return;
    }

    form.lines.push({
        item_id: suggestion.id,
        quantity_ordered: suggestion.suggested_quantity,
        unit_price: suggestion.purchase_price,
    });
}

const total = computed(() =>
    form.lines.reduce(
        (sum, line) =>
            sum + (Number(line.quantity_ordered) || 0) * (Number(line.unit_price) || 0),
        0,
    ),
);

function submit() {
    if (isEditing.value) {
        form.put(`/purchase-orders/${props.purchaseOrder.id}`);

        return;
    }

    form.post('/purchase-orders');
}
</script>

<template>
    <AppLayout :title="isEditing ? 'Ubah Pesanan' : 'Buat Pesanan'">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Pembelian</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        {{ isEditing ? `Ubah Pesanan ${purchaseOrder.number}` : 'Buat Pesanan Pembelian' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Pilih supplier dan barang. Saran jumlah diambil dari barang yang menipis dan dapat diubah.
                    </p>
                </div>

                <Link
                    :href="isEditing ? `/purchase-orders/${purchaseOrder.id}` : '/purchase-orders'"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div
                    v-if="Object.keys(form.errors).length > 0"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                >
                    <p>Periksa kembali data pesanan yang diisi.</p>
                    <p v-if="form.errors.lines" class="mt-1 font-medium">{{ form.errors.lines }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="po-supplier" class="mb-1 block text-sm font-medium text-slate-700">
                                Supplier
                            </label>
                            <select
                                id="po-supplier"
                                v-model="form.supplier_id"
                                required
                                class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.supplier_id
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                            >
                                <option value="" disabled>Pilih supplier</option>
                                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.supplier_id }}
                            </p>
                        </div>

                        <div>
                            <label for="po-notes" class="mb-1 block text-sm font-medium text-slate-700">
                                Catatan
                            </label>
                            <input
                                id="po-notes"
                                v-model="form.notes"
                                type="text"
                                maxlength="1000"
                                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                :class="
                                    form.errors.notes
                                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                        : 'border-slate-300'
                                "
                                placeholder="Opsional"
                            />
                            <p v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                {{ form.errors.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!isEditing && suggestions.length > 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-5"
                >
                    <h2 class="text-sm font-semibold text-amber-900">Barang menipis</h2>
                    <p class="mt-1 text-sm text-amber-800">
                        Saran jumlah berdasarkan stok saat ini terhadap batas minimum.
                    </p>

                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="suggestion in suggestions"
                            :key="suggestion.id"
                            class="flex flex-col gap-2 rounded-xl bg-white/70 px-3 py-2 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-slate-800">
                                    {{ suggestion.name }}
                                </span>
                                <span class="text-xs text-slate-500">
                                    {{ suggestion.code }} · stok {{ formatQuantity(suggestion.current_stock) }}
                                    {{ suggestion.unit }} · minimum {{ formatQuantity(suggestion.min_stock) }}
                                </span>
                            </span>

                            <span class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-amber-800">
                                    saran {{ formatQuantity(suggestion.suggested_quantity) }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-xs font-medium text-amber-800 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="selectedItemIds.includes(suggestion.id)"
                                    @click="addSuggestion(suggestion)"
                                >
                                    {{ selectedItemIds.includes(suggestion.id) ? 'Sudah ada' : 'Tambah' }}
                                </button>
                            </span>
                        </li>
                    </ul>

                    <p v-if="remainingSuggestions.length === 0" class="mt-2 text-xs text-amber-700">
                        Semua saran sudah masuk ke pesanan.
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">Daftar Barang</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Ubah jumlah dan harga satuan sesuai kebutuhan.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addLine"
                        >
                            Tambah Baris
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(line, index) in form.lines"
                            :key="index"
                            class="rounded-2xl border border-slate-200 p-4"
                        >
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-900">
                                    Barang {{ index + 1 }}
                                </h3>
                                <button
                                    v-if="form.lines.length > 1"
                                    type="button"
                                    class="text-sm font-medium text-red-600 transition hover:text-red-700"
                                    @click="removeLine(index)"
                                >
                                    Hapus
                                </button>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2">
                                    <ItemSearchSelect
                                        :model-value="line.item_id"
                                        :items="items"
                                        :error="lineError(index, 'item_id')"
                                        @update:model-value="selectItem(line, $event)"
                                    />
                                </div>

                                <div>
                                    <label
                                        :for="`po-quantity-${index}`"
                                        class="mb-1 block text-sm font-medium text-slate-700"
                                    >
                                        Jumlah Pesanan
                                    </label>
                                    <div class="flex">
                                        <input
                                            :id="`po-quantity-${index}`"
                                            v-model="line.quantity_ordered"
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            required
                                            class="w-full rounded-l-xl border border-r-0 px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                            :class="
                                                lineError(index, 'quantity_ordered')
                                                    ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                                                    : 'border-slate-300'
                                            "
                                        />
                                        <span
                                            class="flex items-center rounded-r-xl border border-slate-300 bg-slate-50 px-3 text-xs text-slate-500"
                                        >
                                            {{ lineItem(line)?.unit ?? 'satuan' }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="lineError(index, 'quantity_ordered')"
                                        class="mt-1 text-sm text-red-600"
                                    >
                                        {{ lineError(index, 'quantity_ordered') }}
                                    </p>
                                </div>

                                <MoneyInput
                                    :id="`po-price-${index}`"
                                    v-model="line.unit_price"
                                    label="Harga Satuan"
                                    :error="lineError(index, 'unit_price')"
                                />

                                <div
                                    v-if="lineItem(line)"
                                    class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600 sm:col-span-2 lg:col-span-4"
                                >
                                    Stok saat ini:
                                    <span class="font-medium text-slate-800">
                                        {{ formatQuantity(lineItem(line).current_stock) }}
                                        {{ lineItem(line).unit }}
                                    </span>
                                    <span class="mx-2 text-slate-300">·</span>
                                    Minimum:
                                    <span class="font-medium text-slate-800">
                                        {{ formatQuantity(lineItem(line).min_stock) }}
                                    </span>
                                    <span class="mx-2 text-slate-300">·</span>
                                    Subtotal:
                                    <span class="font-medium text-slate-800">
                                        {{ formatCurrency((Number(line.quantity_ordered) || 0) * (Number(line.unit_price) || 0)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-slate-500">Total pesanan</p>
                        <p class="text-lg font-semibold text-slate-900">{{ formatCurrency(total) }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Link
                            :href="isEditing ? `/purchase-orders/${purchaseOrder.id}` : '/purchase-orders'"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : isEditing
                                      ? 'Simpan Perubahan'
                                      : 'Simpan Pesanan'
                            }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
