<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    lowStockMaterials: Array,
    lowPartStock: Array,
    productStockAlerts: Array,
    partStockSummary: Array,
});

const roles = computed(() => usePage().props.auth.roles ?? []);
const hasAny = (...r) => r.some((x) => roles.value.includes(x));

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Cards shown by role relevance.
const cards = computed(() => {
    const c = [];
    if (hasAny('admin', 'stock_manager')) {
        c.push({ label: 'Stock value', value: money(props.stats.stock_value), to: 'stock.index', tone: 'indigo' });
        if (props.stats.low_stock > 0) c.push({ label: 'Stock alerts', value: props.stats.low_stock, to: 'stock.index', tone: 'amber' });
    }
    if (hasAny('admin', 'production_manager')) {
        c.push({ label: 'In production', value: props.stats.wo_in_production, to: 'work-orders.index', tone: 'blue' });
        c.push({ label: 'Draft orders', value: props.stats.wo_draft, to: 'work-orders.index', tone: 'gray' });
        if (props.stats.part_stock_alerts > 0) c.push({ label: 'Part stock alerts', value: props.stats.part_stock_alerts, to: 'part-stock.index', tone: 'amber' });
        if (props.stats.product_stock_alerts > 0) c.push({ label: 'Product alerts', value: props.stats.product_stock_alerts, to: 'finished-goods.index', tone: 'amber' });
        c.push({ label: 'Completed orders', value: props.stats.wo_completed, to: 'work-orders.index', tone: 'emerald' });
        c.push({ label: 'Finished goods (units)', value: props.stats.finished_units, to: 'finished-goods.index', tone: 'emerald' });
        c.push({ label: 'Pending deliveries', value: props.stats.pending_deliveries, to: 'deliveries.index', tone: 'blue' });
        c.push({ label: 'Wages this month', value: money(props.stats.wages_month), to: 'wages.index', tone: 'indigo' });
    }
    return c;
});

const toneClass = (t) => ({
    indigo: 'border-indigo-200',
    amber: 'border-amber-300 bg-amber-50',
    blue: 'border-blue-200',
    emerald: 'border-emerald-200',
    gray: 'border-gray-200',
}[t] ?? 'border-gray-200');

const quantity = (n) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="cards.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <Link v-for="(card, i) in cards" :key="i" :href="route(card.to)"
                        class="rounded-lg border bg-white p-5 shadow-sm transition hover:shadow-md" :class="toneClass(card.tone)">
                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ card.label }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ card.value }}</div>
                    </Link>
                </div>
                <section v-if="lowStockMaterials?.length" class="mt-6 overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div class="flex items-center gap-2 border-b border-amber-200 px-5 py-4 text-amber-900 dark:border-amber-900/60 dark:text-amber-100">
                        <AppIcon name="alert" />
                        <h3 class="font-semibold">Raw material stock alerts</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-amber-200 dark:divide-amber-900/60">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Material</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Current</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Alert at</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Short by</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900/50">
                                <tr v-for="material in lowStockMaterials" :key="material.id">
                                    <td class="px-5 py-3 text-sm font-medium text-amber-950 dark:text-amber-50">{{ material.name }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(material.current_quantity) }} {{ material.unit }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(material.alert_quantity) }} {{ material.unit }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-red-700 dark:text-red-300">{{ quantity(material.short_by) }} {{ material.unit }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section v-if="lowPartStock?.length" class="mt-6 overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div class="flex items-center gap-2 border-b border-amber-200 px-5 py-4 text-amber-900 dark:border-amber-900/60 dark:text-amber-100">
                        <AppIcon name="alert" />
                        <h3 class="font-semibold">Pre-cut part stock alerts</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-amber-200 dark:divide-amber-900/60">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Product</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Part</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Needed</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Available</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Short by</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Source</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-amber-100 dark:divide-amber-900/50">
                                <tr v-for="row in lowPartStock" :key="`${row.product_variant_id}-${row.part_id}`">
                                    <td class="px-5 py-3 text-sm font-medium text-amber-950 dark:text-amber-50">{{ row.label }}</td>
                                    <td class="px-5 py-3 text-sm text-amber-900 dark:text-amber-100">{{ row.part }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(row.needed) }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(row.available) }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-red-700 dark:text-red-300">{{ quantity(row.short_by) }}</td>
                                    <td class="px-5 py-3 text-sm text-amber-900 dark:text-amber-100">{{ row.source }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Part stock snapshot</h3>
                            <Link :href="route('part-stock.index')" class="text-sm font-medium text-indigo-600 dark:text-indigo-300">View all</Link>
                        </div>
                        <div class="table-scroll">
                            <table class="min-w-[620px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Product</th>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Part</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Stock</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Alert</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="row in partStockSummary" :key="`${row.label}-${row.part}`">
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.label }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ row.part }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold" :class="row.is_alert ? 'text-amber-700 dark:text-amber-300' : 'text-gray-900 dark:text-gray-100'">{{ quantity(row.quantity) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ quantity(row.alert_quantity) }}</td>
                                    </tr>
                                    <tr v-if="!partStockSummary?.length">
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No part stock yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-amber-200 bg-amber-50 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
                        <div class="flex items-center justify-between border-b border-amber-200 px-5 py-4 dark:border-amber-900/60">
                            <div class="flex items-center gap-2 text-amber-900 dark:text-amber-100">
                                <AppIcon name="alert" />
                                <h3 class="font-semibold">Product stock alerts</h3>
                            </div>
                            <Link :href="route('finished-goods.index')" class="text-sm font-medium text-amber-900 dark:text-amber-100">Manage</Link>
                        </div>
                        <div class="table-scroll">
                            <table class="min-w-[560px] divide-y divide-amber-200 dark:divide-amber-900/60">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Product</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Current</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Alert</th>
                                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-amber-900 dark:text-amber-100">Short</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-amber-100 dark:divide-amber-900/50">
                                    <tr v-for="row in productStockAlerts" :key="row.id">
                                        <td class="px-5 py-3 text-sm font-medium text-amber-950 dark:text-amber-50">{{ row.label }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(row.current) }}</td>
                                        <td class="px-5 py-3 text-right text-sm text-amber-900 dark:text-amber-100">{{ quantity(row.alert) }}</td>
                                        <td class="px-5 py-3 text-right text-sm font-semibold text-red-700 dark:text-red-300">{{ quantity(row.short_by) }}</td>
                                    </tr>
                                    <tr v-if="!productStockAlerts?.length">
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-amber-900 dark:text-amber-100">No product stock alerts.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div v-if="!cards.length" class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">
                    Welcome to Rain Shield. Your role has no summary widgets — use the menu to get started.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
