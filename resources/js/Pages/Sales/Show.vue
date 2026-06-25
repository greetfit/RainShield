<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({ sale: Object });
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <Head :title="`Invoice ${sale.invoice_no}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('sales.index')" class="text-sm text-indigo-600 hover:underline">Sales</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ sale.invoice_no }}</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap justify-end gap-2">
                    <Link :href="route('sales.print', sale.id)"><SecondaryButton>Print Invoice</SecondaryButton></Link>
                    <Link :href="route('sales.receipt', sale.id)"><SecondaryButton>Thermal Receipt</SecondaryButton></Link>
                    <Link :href="route('sales.index')"><SecondaryButton>Back</SecondaryButton></Link>
                </div>

                <section class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                    <div class="grid gap-4 text-sm sm:grid-cols-4">
                        <div><div class="text-gray-500">Invoice</div><div class="font-semibold">{{ sale.invoice_no }}</div></div>
                        <div><div class="text-gray-500">Date</div><div class="font-semibold">{{ sale.sold_at }}</div></div>
                        <div><div class="text-gray-500">Customer</div><div class="font-semibold">{{ sale.customer }}</div></div>
                        <div><div class="text-gray-500">Status</div><div class="font-semibold capitalize">{{ sale.status }} / {{ sale.payment_status }}</div></div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="font-semibold">Items</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Price</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="item in sale.items" :key="item.product">
                                    <td class="px-6 py-4 text-sm font-medium">{{ item.product }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ item.quantity }}</td>
                                    <td class="px-6 py-4 text-right text-sm">{{ money(item.unit_price) }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold">{{ money(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="grid gap-6 md:grid-cols-2">
                    <section class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                        <h3 class="font-semibold">Payments</h3>
                        <div class="mt-4 space-y-3 text-sm">
                            <div v-for="payment in sale.payments" :key="`${payment.paid_on}-${payment.amount}`" class="flex justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <span>{{ payment.paid_on }} · {{ payment.method || 'Payment' }}</span>
                                <strong>{{ money(payment.amount) }}</strong>
                            </div>
                            <div v-if="sale.payments.length === 0" class="text-gray-500">No payments recorded.</div>
                        </div>
                    </section>

                    <section class="rounded-lg bg-white p-6 shadow-sm dark:bg-gray-900">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span>Subtotal</span><strong>{{ money(sale.subtotal) }}</strong></div>
                            <div class="flex justify-between"><span>Discount</span><strong>{{ money(sale.discount) }}</strong></div>
                            <div class="flex justify-between"><span>Tax</span><strong>{{ money(sale.tax) }}</strong></div>
                            <div class="flex justify-between"><span>Shipping</span><strong>{{ money(sale.shipping) }}</strong></div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 text-lg dark:border-gray-700"><span>Total</span><strong>{{ money(sale.total) }}</strong></div>
                            <div class="flex justify-between text-emerald-600"><span>Paid</span><strong>{{ money(sale.paid) }}</strong></div>
                            <div class="flex justify-between text-red-600"><span>Due</span><strong>{{ money(sale.due) }}</strong></div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
