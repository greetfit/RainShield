<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ receivables: Array });
const table = useTableControls(() => props.receivables, ['date', 'reference', 'party', 'total', 'paid', 'due']);
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Customer Due Invoices" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Customer Due Invoices</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <TableControls :table="table" placeholder="Search customer dues...">
                        <div class="table-scroll">
                            <table class="min-w-[880px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Customer</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Paid</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Due</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="row in table.rows.value" :key="row.id">
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.date }}</td>
                                        <td class="px-5 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-300">
                                            <Link :href="route('sales.show', row.id)">{{ row.reference }}</Link>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.party }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ money(row.total) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-emerald-600 dark:text-emerald-300">{{ money(row.paid) }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-300">{{ money(row.due) }}</td>
                                    </tr>
                                    <tr v-if="!table.rows.value.length">
                                        <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No customer dues.</td>
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
