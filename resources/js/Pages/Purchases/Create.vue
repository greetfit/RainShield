<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PurchaseForm from './Partials/PurchaseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    materialOptions: Array,
    finishedProductOptions: Array,
    supplierOptions: Array,
    statusOptions: Array,
    today: String,
});

const form = useForm({
    reference: '',
    supplier_name: '',
    purchased_on: props.today,
    status: 'received',
    transport_charge: 0,
    allocation_method: 'value',
    notes: '',
    items: [{ id: null, item_type: 'raw_material', raw_material_variant_id: '', product_variant_id: '', quantity: '', unit_price: '' }],
});

function submit() {
    form.transform((data) => ({
        ...data,
        items: data.items.filter((row) => {
            const selectedItem = (row.item_type ?? 'raw_material') === 'finished_good'
                ? row.product_variant_id
                : row.raw_material_variant_id;

            return selectedItem && row.quantity;
        }),
    })).post(route('purchases.store'));
}
</script>

<template>
    <Head title="New Purchase" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('purchases.index')" class="text-sm text-indigo-600 hover:underline">Purchases</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">New Purchase</h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-gray-900">
                    <PurchaseForm
                        :form="form"
                        :material-options="materialOptions"
                        :finished-product-options="finishedProductOptions"
                        :supplier-options="supplierOptions"
                        :status-options="statusOptions"
                        title="New Purchase"
                        submit-label="Save purchase"
                        @submit="submit"
                        @cancel="$inertia.visit(route('purchases.index'))"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
