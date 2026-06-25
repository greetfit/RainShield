<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    summary: Object,
    cashByMethod: Object,
    counts: Object,
});

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const cards = [
    { label: 'Customer receivable', value: props.summary.receivables, tone: 'text-red-600 dark:text-red-300' },
    { label: 'Supplier payable', value: props.summary.payables, tone: 'text-amber-600 dark:text-amber-300' },
    { label: 'Wage balance', value: props.summary.wage_balance, tone: 'text-indigo-600 dark:text-indigo-300' },
    { label: 'Expenses', value: props.summary.expense_total, tone: 'text-red-600 dark:text-red-300' },
    { label: 'Net cash recorded', value: props.summary.net_cash, tone: 'text-emerald-600 dark:text-emerald-300' },
];

const sections = [
    { title: 'Daily Profit & Loss', route: 'accounting.daily-profit-loss', description: 'Daily sales, estimated costs, gross profit, and invoice list.', count: null },
    { title: 'Customer Due Invoices', route: 'accounting.customer-due-invoices', description: 'Invoices with pending customer balances.', count: props.counts.receivables },
    { title: 'Supplier Payables', route: 'accounting.supplier-payables', description: 'Purchases with unpaid supplier balances.', count: props.counts.payables },
    { title: 'Wage Balances', route: 'accounting.wage-balances', description: 'Job cards where wage is still pending or overpaid.', count: props.counts.wage_balances },
    { title: 'Expenses', route: 'expenses.index', description: 'Record business expenses like rent, electricity, meals, repairs, and transport.', count: props.counts.expenses },
    { title: 'Money Movement', route: 'accounting.money-movement', description: 'Sale receipts, supplier payments, wage payments, and expenses.', count: props.counts.transactions },
];

const paymentSections = [
    { label: 'Sale receipts', rows: props.cashByMethod.in ?? [] },
    { label: 'Supplier payments', rows: props.cashByMethod.out ?? [] },
    { label: 'Wage payments', rows: props.cashByMethod.wages ?? [] },
    { label: 'Expenses', rows: props.cashByMethod.expenses ?? [] },
];
</script>

<template>
    <Head title="Accounting" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Accounting Overview</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <section v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ card.label }}</div>
                        <div class="mt-2 text-2xl font-semibold" :class="card.tone">{{ money(card.value) }}</div>
                    </section>
                </div>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="section in sections"
                        :key="section.route"
                        :href="route(section.route)"
                        class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-700"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ section.title }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ section.description }}</p>
                            </div>
                            <span v-if="section.count !== null" class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">{{ section.count }}</span>
                        </div>
                    </Link>
                </section>

                <div class="grid gap-4 lg:grid-cols-4">
                    <section v-for="section in paymentSections" :key="section.label" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ section.label }}</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="row in section.rows" :key="`${section.label}-${row.method}`" class="flex items-center justify-between text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ row.method }}</span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ money(row.total) }}</span>
                            </div>
                            <div v-if="!section.rows.length" class="text-sm text-gray-500 dark:text-gray-400">No payments recorded.</div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
