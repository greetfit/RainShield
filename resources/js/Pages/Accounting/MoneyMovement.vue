<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ transactions: Array });
const table = useTableControls(() => props.transactions, ['date', 'type', 'reference', 'party', 'method', 'amount']);
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Money Movement" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Money Movement</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <TableControls :table="table" placeholder="Search money movement...">
                        <div class="table-scroll">
                            <table class="min-w-[920px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Type</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Party</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Method</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="(row, index) in table.rows.value" :key="index">
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.date }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.type }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.reference }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.party }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.method }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold" :class="row.direction === 'in' ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                            {{ row.direction === 'in' ? '+' : '-' }}{{ money(row.amount) }}
                                        </td>
                                    </tr>
                                    <tr v-if="!table.rows.value.length">
                                        <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No payments recorded yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </TableControls>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
