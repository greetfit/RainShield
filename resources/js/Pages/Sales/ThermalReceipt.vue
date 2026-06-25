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
    <Head :title="`Receipt ${sale.invoice_no}`" />

    <div class="min-h-screen bg-gray-100 p-4 print:bg-white print:p-0">
        <div class="mx-auto mb-4 flex max-w-sm justify-end gap-2 print:hidden">
            <SecondaryButton type="button" @click="printPage">Print Receipt</SecondaryButton>
            <Link :href="route('sales.index')"><SecondaryButton>Back</SecondaryButton></Link>
        </div>

        <main class="receipt mx-auto bg-white p-4 text-black shadow print:shadow-none">
            <header class="text-center">
                <img
                    :src="appSettings.company_logo_url || '/images/logo-small.png'"
                    :alt="appSettings.company_name || 'RainShield'"
                    class="mx-auto mb-1 h-12 w-12 object-contain"
                />
                <h1 class="text-base font-bold">{{ appSettings.company_name || 'RainShield' }}</h1>
                <p v-if="appSettings.company_phone" class="text-[11px]">Phone: {{ appSettings.company_phone }}</p>
                <p v-if="appSettings.company_email" class="text-[11px]">{{ appSettings.company_email }}</p>
                <p class="text-[11px]">Sales Receipt</p>
                <p class="mt-2 text-[11px]">{{ sale.invoice_no }}</p>
                <p class="text-[11px]">{{ sale.sold_at }}</p>
                <p class="text-[11px]">Customer: {{ sale.customer }}</p>
            </header>

            <div class="my-3 border-t border-dashed border-black"></div>

            <section class="space-y-2 text-[11px]">
                <div v-for="item in sale.items" :key="item.product">
                    <div class="font-semibold">{{ item.product }}</div>
                    <div class="flex justify-between">
                        <span>{{ item.quantity }} x {{ money(item.unit_price) }}</span>
                        <span>{{ money(item.line_total) }}</span>
                    </div>
                </div>
            </section>

            <div class="my-3 border-t border-dashed border-black"></div>

            <section class="space-y-1 text-[11px]">
                <div class="flex justify-between"><span>Subtotal</span><strong>{{ money(sale.subtotal) }}</strong></div>
                <div v-if="Number(sale.discount) > 0" class="flex justify-between"><span>Discount</span><strong>{{ money(sale.discount) }}</strong></div>
                <div v-if="Number(sale.tax) > 0" class="flex justify-between"><span>Tax</span><strong>{{ money(sale.tax) }}</strong></div>
                <div v-if="Number(sale.shipping) > 0" class="flex justify-between"><span>Shipping</span><strong>{{ money(sale.shipping) }}</strong></div>
                <div class="flex justify-between border-t border-black pt-1 text-sm"><span>Total</span><strong>{{ money(sale.total) }}</strong></div>
                <div class="flex justify-between"><span>Paid</span><strong>{{ money(sale.paid) }}</strong></div>
                <div class="flex justify-between"><span>Due</span><strong>{{ money(sale.due) }}</strong></div>
            </section>

            <div class="my-3 border-t border-dashed border-black"></div>

            <footer class="text-center text-[11px]">
                <p>Thank you.</p>
            </footer>
        </main>
    </div>
</template>

<style scoped>
.receipt {
    width: 80mm;
    min-height: 120mm;
}

@page {
    size: 80mm auto;
    margin: 4mm;
}

@media print {
    .receipt {
        width: 72mm;
        min-height: auto;
        padding: 0;
    }
}
</style>
