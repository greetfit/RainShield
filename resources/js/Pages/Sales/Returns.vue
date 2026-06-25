<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ returns: Array });
const table = useTableControls(() => props.returns, ['return_no', 'invoice_no', 'customer', 'returned_on']);
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Sales Returns" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">Sales Returns</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <Link :href="route('sales.index')"><SecondaryButton>Sales Invoices</SecondaryButton></Link>
                </div>
                <TableControls :table="table" placeholder="Search sales returns...">
                    <div class="table-scroll">
                        <table class="min-w-[900px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Return</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Items</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="row in table.rows.value" :key="row.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ row.return_no }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ row.invoice_no }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ row.returned_on }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ row.customer }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-500">{{ row.items_count }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(row.total_amount) }}</td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No sales returns yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
