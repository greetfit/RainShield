<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ salesByProduct: Array });
const number = (value) => Number(value ?? 0).toLocaleString();
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Sales By Product Report" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Sales By Product</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="table-scroll">
                        <table class="min-w-[720px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Product</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Qty</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="row in salesByProduct" :key="row.product">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.product }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ number(row.quantity) }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(row.total) }}</td>
                                </tr>
                                <tr v-if="!salesByProduct.length">
                                    <td colspan="3" class="px-5 py-8 text-center text-sm text-gray-500">No finalized sales yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
