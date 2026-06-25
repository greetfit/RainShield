<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({ rows: Array, stages: Array });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const markup = (row) => (row.profit_markup_type === 'flat'
    ? money(row.profit_markup_amount || 0)
    : `${Number(row.profit_margin_percent || 0).toFixed(2)}%`);
</script>

<template>
    <Head title="Costing" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Product Costing</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600">
                    Estimated unit cost = recipe materials at their <strong>current weighted-average landed cost</strong>
                    (incl. transport) + piece-rate labour for each stage.
                </p>

                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product · Variant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Material</th>
                                <th v-for="s in stages" :key="s.value" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ s.label }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Labour</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Profit markup</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Selling price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in rows" :key="r.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ r.label }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ money(r.material_cost) }}</td>
                                <td v-for="s in stages" :key="s.value" class="px-4 py-4 text-right text-sm text-gray-500">{{ money(r.labor[s.value]) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ money(r.labor_cost) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(r.unit_cost) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500">{{ markup(r) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-emerald-700">{{ money(r.selling_price) }}</td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td :colspan="stages.length + 6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No costed variants yet. Define required parts and stock pre-cut parts.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

