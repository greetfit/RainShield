<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ purchase: Object, today: String });

const form = useForm({
    return_no: '',
    returned_on: props.today,
    reason: '',
    items: props.purchase.items.map((item) => ({ purchase_item_id: item.id, quantity: '' })),
});

const itemFor = (id) => props.purchase.items.find((item) => item.id === id);
const lineTotal = (row) => (Number(row.quantity) || 0) * Number(itemFor(row.purchase_item_id)?.landed_unit_cost ?? 0);
const total = computed(() => form.items.reduce((sum, row) => sum + lineTotal(row), 0));
const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.filter((item) => Number(item.quantity) > 0),
    })).post(route('purchase-returns.store', props.purchase.id));
}
</script>

<template>
    <Head title="New Purchase Return" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('purchases.index')" class="text-sm text-indigo-600 hover:underline">Purchases</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Purchase Return</h2>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
                    <div>
                        <InputLabel value="Purchase" />
                        <div class="mt-2 text-sm font-medium text-gray-900">{{ purchase.reference || '#' + purchase.id }}</div>
                    </div>
                    <div>
                        <InputLabel value="Supplier" />
                        <div class="mt-2 text-sm font-medium text-gray-900">{{ purchase.supplier_name || '-' }}</div>
                    </div>
                    <div>
                        <InputLabel for="returned_on" value="Return date" />
                        <DatePicker id="returned_on" v-model="form.returned_on" class="mt-1" />
                        <InputError :message="form.errors.returned_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="return_no" value="Return no" />
                        <TextInput id="return_no" v-model="form.return_no" class="mt-1 block w-full" />
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Item</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Returnable</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Return qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit cost</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="row in form.items" :key="row.purchase_item_id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ itemFor(row.purchase_item_id).label }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ itemFor(row.purchase_item_id).returnable_quantity }}</td>
                                <td class="px-4 py-3 text-right">
                                    <input v-model="row.quantity" type="number" min="0" :max="itemFor(row.purchase_item_id).returnable_quantity" :step="itemFor(row.purchase_item_id).item_type === 'finished_good' ? 1 : 0.001" class="w-28 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                </td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(itemFor(row.purchase_item_id).landed_unit_cost) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(lineTotal(row)) }}</td>
                            </tr>
                            <tr v-if="form.items.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">No returnable items remain for this purchase.</td>
                            </tr>
                        </tbody>
                    </table>
                    <InputError :message="form.errors.items" class="px-6 py-2" />
                </div>

                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <InputLabel for="reason" value="Reason" />
                    <textarea id="reason" v-model="form.reason" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <div class="mt-4 text-right text-sm font-semibold text-gray-900">Return total: {{ money(total) }}</div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('purchases.index')"><SecondaryButton type="button">Cancel</SecondaryButton></Link>
                    <PrimaryButton :disabled="form.processing || form.items.length === 0" :class="{ 'opacity-50': form.processing || form.items.length === 0 }">Save return</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
