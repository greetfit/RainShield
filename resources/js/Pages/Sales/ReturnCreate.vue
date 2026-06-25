<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ sale: Object });
const form = useForm({
    returned_on: new Date().toISOString().slice(0, 10),
    notes: '',
    items: props.sale.items.map((item) => ({ sale_item_id: item.id, quantity: 0 })),
});
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const total = computed(() => form.items.reduce((sum, row, index) => sum + Number(row.quantity || 0) * Number(props.sale.items[index].unit_price || 0), 0));

function submit() {
    form.post(route('sale-returns.store', props.sale.id));
}
</script>

<template>
    <Head :title="`Return ${sale.invoice_no}`" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">Sales Return</h2></template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ sale.invoice_no }}</h1>
                        <p class="text-sm text-gray-500">{{ sale.customer }} / {{ sale.sold_at }}</p>
                    </div>
                    <Link :href="route('sales.index')"><SecondaryButton>Back</SecondaryButton></Link>
                </div>

                <form class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Return date</label>
                            <DatePicker v-model="form.returned_on" class="mt-1" />
                            <InputError :message="form.errors.returned_on" class="mt-1" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Notes</label>
                            <TextInput v-model="form.notes" class="mt-1 block w-full" />
                        </div>
                    </div>

                    <div class="mt-5 table-scroll">
                        <table class="min-w-[820px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Sold</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Already returned</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Return now</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="(item, index) in sale.items" :key="item.id">
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ item.label }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ item.sold_quantity }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ item.returned_quantity }}</td>
                                    <td class="px-4 py-3">
                                        <TextInput v-model="form.items[index].quantity" type="number" min="0" :max="item.available_to_return" step="1" class="ml-auto block w-28 text-right" />
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ money(item.unit_price) }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(Number(form.items[index].quantity || 0) * Number(item.unit_price || 0)) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError :message="form.errors.items" class="mt-3" />

                    <div class="mt-6 flex items-center justify-between">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100">Return total {{ money(total) }}</div>
                        <div class="flex gap-3">
                            <Link :href="route('sales.index')"><SecondaryButton type="button">Cancel</SecondaryButton></Link>
                            <PrimaryButton :disabled="form.processing || total <= 0" :class="{ 'opacity-50': form.processing || total <= 0 }">Save Return</PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
