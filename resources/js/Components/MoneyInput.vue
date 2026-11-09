<script setup>
defineOptions({
    inheritAttrs: false,
});

defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    label: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <label v-if="label" class="mb-1 block text-sm font-medium text-slate-700">
            {{ label }}
        </label>

        <div class="relative">
            <span
                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500"
            >
                Rp
            </span>

            <input
                v-bind="$attrs"
                type="number"
                min="0"
                step="0.01"
                :value="modelValue"
                class="block w-full rounded-xl border bg-white py-2 pl-10 pr-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                :class="
                    error
                        ? 'border-red-300 focus:border-red-500 focus:ring-red-100'
                        : 'border-slate-300'
                "
                @input="$emit('update:modelValue', $event.target.value)"
            />
        </div>

        <p v-if="error" class="mt-1 text-sm text-red-600">
            {{ error }}
        </p>
    </div>
</template>
