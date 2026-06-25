<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TableControls from '@/Components/TableControls.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({ sessions: Array });
const table = useTableControls(() => props.sessions, ['session_no', 'opened_by', 'closed_by', 'status']);
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head title="POS Sessions" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">POS Sessions</h2></template>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end gap-2">
                    <Link :href="route('sales.pos')"><SecondaryButton>POS</SecondaryButton></Link>
                    <Link :href="route('sales.index')"><SecondaryButton>Sales Invoices</SecondaryButton></Link>
                </div>
                <TableControls :table="table" placeholder="Search POS sessions...">
                    <div class="table-scroll">
                        <table class="min-w-[1120px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Session</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Opened</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Closed</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Opening</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Expected</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Closing</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Difference</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Sales</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="session in table.rows.value" :key="session.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ session.session_no }}
                                        <div class="text-xs font-normal text-gray-500">{{ session.opened_by }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ session.opened_at }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ session.closed_at || '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ money(session.opening_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ money(session.expected_closing_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ money(session.closing_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm" :class="Number(session.difference_amount || 0) === 0 ? 'text-gray-500' : 'text-amber-600'">{{ money(session.difference_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ session.sales_count }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="session.status === 'open' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'">{{ session.status }}</span>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No POS sessions yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
