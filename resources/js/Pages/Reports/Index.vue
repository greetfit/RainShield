<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    summary: Object,
    production: Object,
    reportLinks: Array,
});

const money = (value) => Number(value ?? 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const number = (value) => Number(value ?? 0).toLocaleString();

const cards = [
    { label: 'Sales total', value: money(props.summary.sales_total) },
    { label: 'Sales due', value: money(props.summary.sales_due) },
    { label: 'Purchase total', value: money(props.summary.purchase_total) },
    { label: 'Raw stock value', value: money(props.summary.raw_stock_value) },
    { label: 'Part stock value', value: money(props.summary.part_stock_value) },
    { label: 'Finished units', value: number(props.summary.finished_units) },
    { label: 'Open work orders', value: number(props.summary.open_work_orders) },
    { label: 'Wage balance', value: money(props.summary.wage_balance) },
];
</script>

<template>
    <Head title="Reports" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Reports</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="card in cards" :key="card.label" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ card.label }}</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ card.value }}</div>
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="report in reportLinks"
                        :key="report.route"
                        :href="route(report.route)"
                        class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-700"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ report.label }}</h3>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ report.description }}</p>
                            </div>
                            <span class="rounded-lg bg-indigo-50 p-2 text-indigo-700 transition group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-950 dark:text-indigo-200">
                                <AppIcon name="file-chart" />
                            </span>
                        </div>
                    </Link>
                </section>

                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">Cutting batches</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(production.cutting_batches) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">Open job cards</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(production.open_job_cards) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">Completed job cards</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(production.completed_job_cards) }}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">Completed work orders</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number(production.completed_work_orders) }}</div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
