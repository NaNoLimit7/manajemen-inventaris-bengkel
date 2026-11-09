<script setup>
defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    emptyMessage: {
        type: String,
        default: 'Tidak ada data.',
    },
});
</script>

<template>
    <div class="w-full max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="w-full overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500"
                            :class="column.class"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length" class="px-4 py-10 text-center text-sm text-slate-500">
                            {{ emptyMessage }}
                        </td>
                    </tr>
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.id ?? index"
                        class="align-top transition hover:bg-slate-50/70"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-4"
                            :class="column.class"
                        >
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ row[column.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="$slots.pagination" class="border-t border-slate-200 px-4 py-3">
            <slot name="pagination" />
        </div>
    </div>
</template>
