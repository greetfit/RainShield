<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ dailyProfitLoss: Object });
const profitDate = ref(props.dailyProfitLoss.date);
const table = useTableControls(() => props.dailyProfitLoss.sales, ['time', 'invoice_no', 'customer', 'items_count', 'total', 'paid', 'due']);

const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function applyProfitDate() {
    router.get(route('accounting.daily-profit-loss'), { profit_date: profitDate.value }, {
        preserveScroll: true,
        preserveState: false,
    });
}
</script>

<template>
    <Head title="Daily Profit & Loss" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Daily Profit & Loss</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="relative z-20 overflow-visible rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Selected Day</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sales and estimated gross profit for the selected day.</p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="w-48">
                                <label class="mb-1 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Date</label>
                                <DatePicker v-model="profitDate" align="right" />
                            </div>
                            <button type="button" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700" @click="applyProfitDate">
                                Apply
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Sales</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ money(dailyProfitLoss.summary.sales_total) }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Estimated cost</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ money(dailyProfitLoss.summary.estimated_cost) }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Gross profit</div>
                            <div class="mt-1 text-xl font-semibold" :class="dailyProfitLoss.summary.gross_profit >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                {{ money(dailyProfitLoss.summary.gross_profit) }}
                            </div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Margin</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ Number(dailyProfitLoss.summary.gross_margin_percent).toFixed(2) }}%</div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Expenses</div>
                            <div class="mt-1 text-xl font-semibold text-red-600 dark:text-red-300">{{ money(dailyProfitLoss.summary.expenses) }}</div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Net profit</div>
                            <div class="mt-1 text-xl font-semibold" :class="dailyProfitLoss.summary.net_profit >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300'">
                                {{ money(dailyProfitLoss.summary.net_profit) }}
                            </div>
                        </div>
                        <div class="rounded-md border border-gray-200 p-4 dark:border-gray-800">
                            <div class="text-xs uppercase text-gray-500">Net margin</div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ Number(dailyProfitLoss.summary.net_margin_percent).toFixed(2) }}%</div>
                        </div>
                    </div>

                    <div class="grid gap-4 px-5 pb-5 sm:grid-cols-3 xl:grid-cols-6">
                        <div v-for="item in [
                            ['Invoices', dailyProfitLoss.summary.invoice_count],
                            ['Items', dailyProfitLoss.summary.items_count],
                            ['Discount', money(dailyProfitLoss.summary.discount)],
                            ['Tax', money(dailyProfitLoss.summary.tax)],
                            ['Collected', money(dailyProfitLoss.summary.collected)],
                            ['Due', money(dailyProfitLoss.summary.due)],
                        ]" :key="item[0]" class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <div class="text-xs uppercase text-gray-500">{{ item[0] }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ item[1] }}</div>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 p-5 dark:border-gray-800">
                        <h3 class="mb-3 font-semibold text-gray-900 dark:text-gray-100">Expenses for the day</h3>
                        <div class="table-scroll">
                            <table class="min-w-[720px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Category</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Method</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="expense in dailyProfitLoss.expenses" :key="expense.id">
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ expense.category }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.method }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.reference }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-red-600 dark:text-red-300">{{ money(expense.amount) }}</td>
                                    </tr>
                                    <tr v-if="!dailyProfitLoss.expenses.length">
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No expenses for this day.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 p-5 dark:border-gray-800">
                        <TableControls :table="table" placeholder="Search invoices...">
                            <div class="table-scroll">
                                <table class="min-w-[820px] divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Time</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Invoice</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Customer</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Items</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Total</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Paid</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Due</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                        <tr v-for="sale in table.rows.value" :key="sale.id">
                                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ sale.time }}</td>
                                            <td class="px-5 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-300">
                                                <Link :href="route('sales.show', sale.id)">{{ sale.invoice_no }}</Link>
                                            </td>
                                            <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ sale.customer }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-gray-700 dark:text-gray-300">{{ sale.items_count }}</td>
                                            <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(sale.total) }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-emerald-600 dark:text-emerald-300">{{ money(sale.paid) }}</td>
                                            <td class="px-5 py-3 text-right text-sm text-red-600 dark:text-red-300">{{ money(sale.due) }}</td>
                                        </tr>
                                        <tr v-if="!table.rows.value.length">
                                            <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No sales for this day.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </TableControls>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
