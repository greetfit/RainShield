<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ returns: Array });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const table = useTableControls(() => props.returns, ['returned_on', 'return_no', 'purchase_reference', 'supplier_name', 'total_amount']);
</script>

<template>
    <Head title="Purchase Returns" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Purchase Returns</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <Link :href="route('purchases.index')" class="text-sm font-medium text-indigo-600 hover:underline">Back to purchases</Link>
                </div>

                <TableControls :table="table" placeholder="Search returns...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Return No</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Purchase</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Items</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in table.rows.value" :key="item.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ item.returned_on }}</td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ item.return_no || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ item.purchase_reference || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ item.supplier_name || '-' }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ item.items_count }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(item.total_amount) }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="eye" :href="route('purchase-returns.show', item.id)">View</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No purchase returns yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
