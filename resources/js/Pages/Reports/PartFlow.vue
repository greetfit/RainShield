<script setup>
import DatePicker from '@/Components/DatePicker.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ filters: Object, partFlow: Object });
const from = ref(props.filters.from);
const to = ref(props.filters.to);
const number = (value) => Number(value ?? 0).toLocaleString();

function applyFilters() {
    router.get(route('reports.part-flow'), { from: from.value, to: to.value }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <Head title="Part In / Out Report" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Part In / Out</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="w-44 text-xs font-semibold uppercase text-gray-500">From<DatePicker v-model="from" class="mt-1" /></label>
                        <label class="w-44 text-xs font-semibold uppercase text-gray-500">To<DatePicker v-model="to" class="mt-1" /></label>
                        <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="applyFilters">Apply</button>
                    </div>
                </section>

                <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="row in partFlow.summary" :key="`${row.stock_type}-${row.direction}`" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">{{ row.stock_type }} / {{ row.direction }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(row.quantity) }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ number(row.movement_count) }} movement(s)</div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="table-scroll">
                        <table class="min-w-[980px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">At</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Product</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Part</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Direction</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Qty</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="row in partFlow.movements" :key="`${row.at}-${row.product}-${row.part}-${row.balance}`">
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.at }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.product }}</td>
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.part }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.stock_type }}</td>
                                    <td class="px-5 py-3 text-sm capitalize" :class="row.direction === 'in' ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">{{ row.direction }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number(row.quantity) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number(row.balance) }}</td>
                                </tr>
                                <tr v-if="!partFlow.movements.length">
                                    <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No part movements in this range.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
