<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ movements: Array });
const table = useTableControls(() => props.movements, ['date', 'item', 'stock_type', 'direction', 'quantity', 'unit_cost', 'note']);
const money = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 });
</script>

<template>
    <Head title="Part Stock Movements" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Part Stock Movements</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4">
                    <Link :href="route('part-stock.index')">
                        <SecondaryButton type="button">Back to part stock</SecondaryButton>
                    </Link>
                </div>

                <TableControls :table="table" placeholder="Search movements...">
                    <div class="table-scroll">
                        <table class="min-w-[1060px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Part</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Direction</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Cost</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Balance</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Balance Avg</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="movement in table.rows.value" :key="movement.id">
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ movement.date }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ movement.item }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ movement.stock_type }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ movement.direction }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ movement.quantity }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ money(movement.unit_cost) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ movement.balance_quantity }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ money(movement.balance_average_cost) }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ movement.note || '-' }}</td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No part movements yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

