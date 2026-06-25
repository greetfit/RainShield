<script setup>
import DatePicker from '@/Components/DatePicker.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    filters: Object,
    productionFlow: Array,
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const number = (value) => Number(value ?? 0).toLocaleString();
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function applyFilters() {
    router.get(route('reports.production-flow'), { from: from.value, to: to.value }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <Head title="Production Flow Report" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Production Flow</h2></template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-end gap-3">
                        <label class="w-44 text-xs font-semibold uppercase text-gray-500">From<DatePicker v-model="from" class="mt-1" /></label>
                        <label class="w-44 text-xs font-semibold uppercase text-gray-500">To<DatePicker v-model="to" class="mt-1" /></label>
                        <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" @click="applyFilters">Apply</button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="table-scroll">
                        <table class="min-w-[880px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Cards</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Issued</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Good</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Damaged</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Pending</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Wage</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Paid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="row in productionFlow" :key="row.stage">
                                    <td class="px-5 py-3 text-sm font-medium capitalize text-gray-900 dark:text-gray-100">{{ row.stage }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number(row.cards) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number(row.issued) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-emerald-600 dark:text-emerald-300">{{ number(row.good) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-red-600 dark:text-red-300">{{ number(row.damaged) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-600 dark:text-amber-300">{{ number(row.pending) }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(row.wage) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ money(row.paid) }}</td>
                                </tr>
                                <tr v-if="!productionFlow.length">
                                    <td colspan="8" class="px-5 py-8 text-center text-sm text-gray-500">No production movement in this date range.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
