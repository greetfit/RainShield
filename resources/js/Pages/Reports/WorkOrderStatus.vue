<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    production: Object,
    workOrdersByStatus: Array,
});

const number = (value) => Number(value ?? 0).toLocaleString();

const cards = [
    { label: 'Cutting batches', value: props.production.cutting_batches },
    { label: 'Open job cards', value: props.production.open_job_cards },
    { label: 'Completed job cards', value: props.production.completed_job_cards },
    { label: 'Completed work orders', value: props.production.completed_work_orders },
];
</script>

<template>
    <Head title="Work Order Status Report" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Work Order Status</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">{{ card.label }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(card.value) }}</div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="table-scroll">
                        <table class="min-w-[520px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Count</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="row in workOrdersByStatus" :key="row.status">
                                    <td class="px-5 py-3 text-sm capitalize text-gray-700 dark:text-gray-300">{{ row.status }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number(row.count) }}</td>
                                </tr>
                                <tr v-if="!workOrdersByStatus.length">
                                    <td colspan="2" class="px-5 py-8 text-center text-sm text-gray-500">No work orders yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
