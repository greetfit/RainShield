<script setup>
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({ sale: Object });
const page = usePage();
const appSettings = computed(() => page.props.appSettings ?? {});
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function printPage() {
    window.print();
}

onMounted(() => {
    setTimeout(printPage, 250);
});
</script>

<template>
    <Head :title="`Print ${sale.invoice_no}`" />

    <div class="min-h-screen bg-gray-100 p-4 text-gray-950 print:bg-white print:p-0">
        <div class="mx-auto mb-4 flex max-w-4xl justify-end gap-2 print:hidden">
            <SecondaryButton type="button" @click="printPage">Print / Save PDF</SecondaryButton>
            <Link :href="route('sales.show', sale.id)"><SecondaryButton>Back</SecondaryButton></Link>
        </div>

        <main class="mx-auto max-w-4xl bg-white p-10 shadow print:max-w-none print:shadow-none">
            <header class="flex items-start justify-between border-b border-gray-300 pb-6">
                <div class="flex items-center gap-4">
                    <img
                        :src="appSettings.company_logo_url || '/images/logo-small.png'"
                        :alt="appSettings.company_name || 'RainShield'"
                        class="h-16 w-16 object-contain"
                    />
                    <div>
                        <h1 class="text-2xl font-bold">{{ appSettings.company_name || 'RainShield' }}</h1>
                        <p v-if="appSettings.company_phone" class="mt-1 text-sm text-gray-600">Phone: {{ appSettings.company_phone }}</p>
                        <p v-if="appSettings.company_email" class="text-sm text-gray-600">Email: {{ appSettings.company_email }}</p>
                        <p class="mt-1 text-sm text-gray-600">Sales Invoice</p>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-xl font-semibold">{{ sale.invoice_no }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ sale.sold_at }}</p>
                </div>
            </header>

            <section class="mt-6 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <div class="text-xs uppercase text-gray-500">Bill To</div>
                    <div class="mt-1 font-semibold">{{ sale.customer }}</div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase text-gray-500">Payment Status</div>
                    <div class="mt-1 font-semibold capitalize">{{ sale.payment_status }}</div>
                </div>
            </section>

            <table class="mt-8 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-300 text-left text-xs uppercase text-gray-500">
                        <th class="py-3">Product</th>
                        <th class="py-3 text-right">Qty</th>
                        <th class="py-3 text-right">Price</th>
                        <th class="py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in sale.items" :key="item.product" class="border-b border-gray-100">
                        <td class="py-3 font-medium">{{ item.product }}</td>
                        <td class="py-3 text-right">{{ item.quantity }}</td>
                        <td class="py-3 text-right">{{ money(item.unit_price) }}</td>
                        <td class="py-3 text-right font-semibold">{{ money(item.line_total) }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="mt-8 flex justify-end">
                <div class="w-72 space-y-2 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><strong>{{ money(sale.subtotal) }}</strong></div>
                    <div class="flex justify-between"><span>Discount</span><strong>{{ money(sale.discount) }}</strong></div>
                    <div class="flex justify-between"><span>Tax</span><strong>{{ money(sale.tax) }}</strong></div>
                    <div class="flex justify-between"><span>Shipping</span><strong>{{ money(sale.shipping) }}</strong></div>
                    <div class="flex justify-between border-t border-gray-300 pt-3 text-lg"><span>Total</span><strong>{{ money(sale.total) }}</strong></div>
                    <div class="flex justify-between"><span>Paid</span><strong>{{ money(sale.paid) }}</strong></div>
                    <div class="flex justify-between"><span>Due</span><strong>{{ money(sale.due) }}</strong></div>
                </div>
            </section>

            <footer class="mt-12 border-t border-gray-200 pt-4 text-center text-xs text-gray-500">
                Thank you for your business.
            </footer>
        </main>
    </div>
</template>
