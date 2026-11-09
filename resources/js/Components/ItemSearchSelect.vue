<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { formatQuantity } from '../utils/formatters';

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: null,
    },
    items: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: 'Barang',
    },
    placeholder: {
        type: String,
        default: 'Cari nama atau kode barang',
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const query = ref('');
const searchInput = ref(null);

const selectedItem = computed(
    () => props.items.find((item) => String(item.id) === String(props.modelValue)) ?? null,
);

const filteredItems = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (term === '') {
        return props.items.slice(0, 10);
    }

    return props.items
        .filter(
            (item) =>
                item.name.toLowerCase().includes(term) ||
                item.code.toLowerCase().includes(term),
        )
        .slice(0, 10);
});

function openList() {
    open.value = true;
    query.value = '';

    nextTick(() => searchInput.value?.focus());
}

function selectItem(item) {
    emit('update:modelValue', item.id);
    open.value = false;
    query.value = '';
}

function closeList() {
    window.setTimeout(() => {
        open.value = false;
    }, 150);
}

watch(
    () => props.modelValue,
    () => {
        open.value = false;
    },
);
</script>

<template>
    <div class="relative">
        <label v-if="label" class="mb-1 block text-sm font-medium text-slate-700">
            {{ label }}
        </label>

        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-xl border bg-white px-3 py-2 text-left text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            :class="error ? 'border-red-300' : 'border-slate-300'"
            @click="open ? (open = false) : openList()"
        >
            <span v-if="selectedItem" class="truncate text-slate-900">
                {{ selectedItem.code }} — {{ selectedItem.name }}
            </span>
            <span v-else class="truncate text-slate-400">{{ placeholder }}</span>

            <svg
                class="h-4 w-4 shrink-0 text-slate-400"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                    clip-rule="evenodd"
                />
            </svg>
        </button>

        <div
            v-if="open"
            class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 shadow-lg"
        >
            <input
                ref="searchInput"
                v-model="query"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                placeholder="Ketik nama atau kode..."
                @blur="closeList"
            />

            <div class="mt-2 max-h-60 overflow-y-auto">
                <button
                    v-for="item in filteredItems"
                    :key="item.id"
                    type="button"
                    class="flex w-full flex-col rounded-lg px-3 py-2 text-left transition hover:bg-slate-50"
                    @mousedown.prevent="selectItem(item)"
                >
                    <span class="text-sm font-medium text-slate-800">{{ item.name }}</span>
                    <span class="text-xs text-slate-500">
                        {{ item.code }} · stok {{ formatQuantity(item.current_stock) }}
                        {{ item.unit }}
                    </span>
                </button>

                <p v-if="filteredItems.length === 0" class="px-3 py-4 text-center text-sm text-slate-500">
                    Barang tidak ditemukan.
                </p>
            </div>
        </div>

        <p v-if="error" class="mt-1 text-sm text-red-600">
            {{ error }}
        </p>
    </div>
</template>
