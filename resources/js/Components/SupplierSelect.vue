<script setup>
defineProps({
    modelValue: {
        type: [Number, String, null],
        default: '',
    },
    suppliers: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: 'Supplier',
    },
    placeholder: {
        type: String,
        default: 'Tanpa supplier',
    },
    error: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="mb-1 block text-sm font-medium text-slate-700">
            {{ label }}
        </label>

        <select
            :value="modelValue ?? ''"
            class="w-full rounded-xl border bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            :class="error ? 'border-red-300' : 'border-slate-300'"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                {{ supplier.name }}
            </option>
        </select>

        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
