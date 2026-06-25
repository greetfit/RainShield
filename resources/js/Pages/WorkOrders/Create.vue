<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ variantOptions: Array, today: String });
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));

const form = useForm({
    product_variant_id: '',
    quantity: 1,
    target_delivery_date: '',
    notes: '',
});

function submit() {
    form.post(route('work-orders.store'));
}
</script>

<template>
    <Head title="New Work Order" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('work-orders.index')" class="text-sm text-indigo-600 hover:underline">Work Orders</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">New Work Order</h2>
            </div>
        </template>

        <div class="py-8">
            <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <InputLabel for="variant" value="Product variant" />
                        <SearchableSelect id="variant" v-model="form.product_variant_id" :options="variantSearchOptions" placeholder="Search variant with required parts..." class="mt-1" />
                        <InputError :message="form.errors.product_variant_id" class="mt-1" />
                        <p v-if="variantOptions.length === 0" class="mt-1 text-xs text-amber-700">
                            No variants have required parts yet. Define pre-cut parts first from Masters > Products > Variants > Recipe.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="quantity" value="Quantity (garments)" />
                            <TextInput id="quantity" type="number" min="1" step="1" v-model="form.quantity" class="mt-1 block w-full" />
                            <InputError :message="form.errors.quantity" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="target" value="Target delivery date" />
                            <DatePicker id="target" v-model="form.target_delivery_date" class="mt-1" />
                            <InputError :message="form.errors.target_delivery_date" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="notes" value="Notes (optional)" />
                        <textarea id="notes" v-model="form.notes" rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('work-orders.index')"><SecondaryButton type="button">Cancel</SecondaryButton></Link>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Create</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
