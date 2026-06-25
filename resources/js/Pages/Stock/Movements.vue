<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ movements: Array });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qty = (n) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });
</script>

<template>
    <Head title="Stock Movements" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('stock.index')" class="text-sm text-indigo-600 hover:underline">Stock</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Movement Ledger</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Material — Variant</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Dir</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="m in movements" :key="m.id">
                                <td class="px-6 py-3 text-sm text-gray-500">{{ m.at }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ m.label }}</td>
                                <td class="px-6 py-3 text-center">
                                    <span class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="m.direction === 'in' ? 'bg-emerald-100 text-emerald-800' : m.direction === 'out' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">
                                        {{ m.direction }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right text-sm text-gray-700">{{ qty(m.quantity) }}</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-700">{{ money(m.unit_cost) }}</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-700">{{ qty(m.balance_quantity) }}</td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ m.note || '—' }}</td>
                            </tr>
                            <tr v-if="movements.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No movements yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

