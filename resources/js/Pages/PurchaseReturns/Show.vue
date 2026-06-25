<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ returnRecord: Object });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="`Purchase Return ${returnRecord.return_no || '#' + returnRecord.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('purchase-returns.index')" class="text-sm text-indigo-600 hover:underline">Purchase Returns</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ returnRecord.return_no || 'Return #' + returnRecord.id }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-white p-6 text-sm shadow-sm sm:grid-cols-4">
                    <div><div class="text-gray-500">Date</div><div class="font-medium">{{ returnRecord.returned_on }}</div></div>
                    <div><div class="text-gray-500">Supplier</div><div class="font-medium">{{ returnRecord.supplier_name || '-' }}</div></div>
                    <div><div class="text-gray-500">Purchase</div><div class="font-medium">{{ returnRecord.purchase_reference || '-' }}</div></div>
                    <div><div class="text-gray-500">Total</div><div class="font-medium">{{ money(returnRecord.total_amount) }}</div></div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Material</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit cost</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in returnRecord.items" :key="item.label">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.label }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ item.quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(item.unit_cost) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="returnRecord.reason" class="rounded-md bg-white p-4 text-sm text-gray-600 shadow-sm">{{ returnRecord.reason }}</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
