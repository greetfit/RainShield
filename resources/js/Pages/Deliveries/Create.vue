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

const form = useForm({
    customer_name: '',
    product_variant_id: '',
    quantity: 1,
    dispatched_on: props.today,
    notes: '',
});

const available = computed(
    () => props.variantOptions.find((o) => o.id === Number(form.product_variant_id))?.available ?? null,
);
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: `${variant.label} (${variant.available} in stock)`,
})));

function submit() {
    form.post(route('deliveries.store'));
}
</script>

<template>
    <Head title="New Delivery" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('deliveries.index')" class="text-sm text-indigo-600 hover:underline">Deliveries</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">New Delivery</h2>
            </div>
        </template>

        <div class="py-8">
            <form @submit.prevent="submit" class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <InputLabel for="customer" value="Customer" />
                        <TextInput id="customer" v-model="form.customer_name" class="mt-1 block w-full" />
                        <InputError :message="form.errors.customer_name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="variant" value="Product variant (in stock)" />
                        <SearchableSelect id="variant" v-model="form.product_variant_id" :options="variantSearchOptions" placeholder="Search finished good..." class="mt-1" />
                        <InputError :message="form.errors.product_variant_id" class="mt-1" />
                        <p v-if="variantOptions.length === 0" class="mt-1 text-xs text-amber-700">No finished goods in stock yet.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="quantity" value="Quantity" />
                            <TextInput id="quantity" type="number" min="1" :max="available ?? undefined" step="1" v-model="form.quantity" class="mt-1 block w-full" />
                            <InputError :message="form.errors.quantity" class="mt-1" />
                            <p v-if="available !== null" class="mt-1 text-xs text-gray-500">{{ available }} available</p>
                        </div>
                        <div>
                            <InputLabel for="dispatched_on" value="Dispatch date" />
                            <DatePicker id="dispatched_on" v-model="form.dispatched_on" class="mt-1" />
                            <InputError :message="form.errors.dispatched_on" class="mt-1" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('deliveries.index')"><SecondaryButton type="button">Cancel</SecondaryButton></Link>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Dispatch</PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
