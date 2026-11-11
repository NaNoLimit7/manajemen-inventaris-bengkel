<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    customerName: {
        type: String,
        default: '',
    },
    customers: {
        type: Array,
        default: () => [],
    },
    error: {
        type: String,
        default: '',
    },
    nameError: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue', 'update:customerName']);

const creatingNew = ref(props.customerName !== '');

watch(
    () => props.customerName,
    (value) => {
        if (value !== '') {
            creatingNew.value = true;
        }
    },
);

function onChange(event) {
    const value = event.target.value;

    if (value === '__new__') {
        creatingNew.value = true;
        emit('update:modelValue', '');
        emit('update:customerName', props.customerName ?? '');

        return;
    }

    creatingNew.value = false;
    emit('update:modelValue', value);
    emit('update:customerName', '');
}
</script>

<template>
    <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Pelanggan</label>

        <select
            :value="creatingNew ? '__new__' : (modelValue ?? '')"
            class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            :class="error ? 'border-red-300' : 'border-slate-300'"
            @change="onChange"
        >
            <option value="">Tanpa pelanggan</option>
            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                {{ customer.name }}{{ customer.phone ? ` — ${customer.phone}` : '' }}
            </option>
            <option value="__new__">+ Pelanggan baru</option>
        </select>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>

        <div v-if="creatingNew" class="mt-2">
            <label class="mb-1 block text-sm font-medium text-slate-700">
                Nama Pelanggan Baru
            </label>
            <input
                :value="customerName"
                type="text"
                maxlength="120"
                class="w-full rounded-xl border px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                :class="nameError ? 'border-red-300' : 'border-slate-300'"
                placeholder="Ketik nama pelanggan"
                @input="emit('update:customerName', $event.target.value)"
            />
            <p v-if="nameError" class="mt-1 text-sm text-red-600">{{ nameError }}</p>
        </div>
    </div>
</template>
