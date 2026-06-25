<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ wageBalances: Array });
const table = useTableControls(() => props.wageBalances, ['reference', 'staff', 'stage', 'wage', 'paid', 'balance']);
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="Wage Balances" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Wage Balances</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <TableControls :table="table" placeholder="Search wage balances...">
                        <div class="table-scroll">
                            <table class="min-w-[860px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Work</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Staff</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Wage</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Paid</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Balance</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="row in table.rows.value" :key="row.id">
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.reference }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.staff }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ row.stage }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ money(row.wage) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-emerald-600 dark:text-emerald-300">{{ money(row.paid) }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-indigo-600 dark:text-indigo-300">{{ money(row.balance) }}</td>
                                    </tr>
                                    <tr v-if="!table.rows.value.length">
                                        <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No wage balances.</td>
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
