<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ purchase: Object });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="`Purchase ${purchase.reference || '#' + purchase.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('purchases.index')" class="text-sm text-indigo-600 hover:underline">Purchases</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ purchase.reference || 'Purchase #' + purchase.id }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-white p-6 text-sm shadow-sm sm:grid-cols-4">
                    <div><div class="text-gray-500">Date</div><div class="font-medium">{{ purchase.purchased_on }}</div></div>
                    <div><div class="text-gray-500">Supplier</div><div class="font-medium">{{ purchase.supplier_name || '—' }}</div></div>
                    <div><div class="text-gray-500">Transport split by</div><div class="font-medium capitalize">{{ purchase.allocation_method }}</div></div>
                    <div><div class="text-gray-500">Recorded by</div><div class="font-medium">{{ purchase.created_by || '—' }}</div></div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Material — Variant</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit price</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Transport</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Landed/unit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(i, idx) in purchase.items" :key="idx">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ i.label }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ i.quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(i.unit_price) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(i.line_total) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">{{ money(i.allocated_transport) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(i.landed_unit_cost) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-50">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right text-sm text-gray-500">Items total</td>
                                <td class="px-4 py-3 text-right text-sm">{{ money(purchase.items_total) }}</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right text-sm text-gray-500">Transport</td>
                                <td class="px-4 py-3 text-right text-sm">{{ money(purchase.transport_charge) }}</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr class="font-semibold">
                                <td colspan="3" class="px-4 py-3 text-right text-sm text-gray-900">Grand total</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ money(purchase.grand_total) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p v-if="purchase.notes" class="rounded-md bg-white p-4 text-sm text-gray-600 shadow-sm">{{ purchase.notes }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
