<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    lowRawStock: Array,
    lowPartStock: Array,
});

const number = (value) => Number(value ?? 0).toLocaleString();
</script>

<template>
    <Head title="Stock Alerts Report" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Stock Alerts</h2></template>
        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:px-8 xl:grid-cols-2">
                <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div class="border-b border-amber-200 px-5 py-4 dark:border-amber-900/60">
                        <h3 class="font-semibold text-amber-950 dark:text-amber-100">Raw Material Alerts</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-[600px] divide-y divide-amber-200 dark:divide-amber-900/60">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Material</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Current</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Alert</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900/50">
                                <tr v-for="row in lowRawStock" :key="row.item">
                                    <td class="px-5 py-3 text-sm font-medium text-amber-950 dark:text-amber-50">{{ row.item }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ row.current }} {{ row.unit }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ row.alert }} {{ row.unit }}</td>
                                </tr>
                                <tr v-if="!lowRawStock.length">
                                    <td colspan="3" class="px-5 py-8 text-center text-sm text-amber-900 dark:text-amber-100">No raw material alerts.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div class="border-b border-amber-200 px-5 py-4 dark:border-amber-900/60">
                        <h3 class="font-semibold text-amber-950 dark:text-amber-100">Part Stock Alerts</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-[680px] divide-y divide-amber-200 dark:divide-amber-900/60">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Product</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Part</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Current</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-amber-900 dark:text-amber-100">Alert</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900/50">
                                <tr v-for="row in lowPartStock" :key="`${row.item}-${row.part}`">
                                    <td class="px-5 py-3 text-sm font-medium text-amber-950 dark:text-amber-50">{{ row.item }}</td>
                                    <td class="px-5 py-3 text-sm text-amber-900 dark:text-amber-100">{{ row.part }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ number(row.current) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ number(row.alert) }}</td>
                                </tr>
                                <tr v-if="!lowPartStock.length">
                                    <td colspan="4" class="px-5 py-8 text-center text-sm text-amber-900 dark:text-amber-100">No part stock alerts.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
