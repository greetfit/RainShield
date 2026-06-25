<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ rows: Array, variantOptions: Array });
const table = useTableControls(() => props.rows, ['label', 'quantity', 'average_cost', 'alert_quantity']);
const money = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));

const adjustmentTarget = ref(null);
const adjustmentForm = useForm({
    product_variant_id: '',
    counted_quantity: '',
    alert_quantity: '',
    note: '',
});
const openingTarget = ref(false);
const openingForm = useForm({
    product_variant_id: '',
    quantity: '',
    unit_cost: '',
    alert_quantity: '',
    note: '',
});

const selectedVariantLabel = computed(() => {
    const option = props.variantOptions.find((v) => v.id === Number(adjustmentForm.product_variant_id));
    return option?.label ?? adjustmentTarget.value?.label ?? '';
});

function openAdjustment(row = null) {
    adjustmentTarget.value = row ?? { label: 'New finished-goods count' };
    adjustmentForm.clearErrors();
    adjustmentForm.product_variant_id = row?.product_variant_id ?? '';
    adjustmentForm.counted_quantity = row?.quantity ?? 0;
    adjustmentForm.alert_quantity = row?.alert_quantity ?? 0;
    adjustmentForm.note = '';
}

function submitAdjustment() {
    adjustmentForm.post(route('finished-goods.adjust'), {
        preserveScroll: true,
        onSuccess: () => (adjustmentTarget.value = null),
    });
}

function openOpeningStock() {
    openingTarget.value = true;
    openingForm.clearErrors();
    openingForm.reset();
}

function submitOpeningStock() {
    openingForm.post(route('finished-goods.opening'), {
        preserveScroll: true,
        onSuccess: () => (openingTarget.value = false),
    });
}
</script>

<template>
    <Head title="Finished Goods" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Finished Goods</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-center justify-end gap-4">
                    <Link :href="route('finished-goods.movements')">
                        <SecondaryButton type="button">
                            <span class="inline-flex items-center gap-2">
                                <AppIcon name="clipboard" />
                                View movement ledger
                            </span>
                        </SecondaryButton>
                    </Link>
                    <Link :href="route('deliveries.create')">
                        <PrimaryButton type="button">
                            <span class="inline-flex items-center gap-2">
                                <AppIcon name="truck" />
                                Dispatch a delivery
                            </span>
                        </PrimaryButton>
                    </Link>
                    <SecondaryButton type="button" @click="openOpeningStock">
                        <span class="inline-flex items-center gap-2">
                            <AppIcon name="warehouse" />
                            Opening Stock
                        </span>
                    </SecondaryButton>
                    <PrimaryButton @click="openAdjustment()">
                        <span class="inline-flex items-center gap-2">
                            <AppIcon name="edit" />
                            Adjust Stock
                        </span>
                    </PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search finished goods...">
                <div class="table-scroll">
                    <table class="min-w-[980px] divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product - Variant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">On hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Avg Cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Value</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Alert At</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in table.rows.value" :key="r.id" :class="{ 'bg-amber-50 dark:bg-amber-950/20': Number(r.alert_quantity || 0) > 0 && Number(r.quantity) <= Number(r.alert_quantity) }">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ r.label }}</td>
                                <td class="px-6 py-4 text-right text-sm" :class="Number(r.alert_quantity || 0) > 0 && Number(r.quantity) <= Number(r.alert_quantity) ? 'font-semibold text-amber-700' : 'text-gray-700'">
                                    {{ r.quantity }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-600">{{ money(r.average_cost) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(r.value) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-600">{{ r.alert_quantity || 0 }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openAdjustment(r)">Adjust</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No finished goods yet. Complete a work order or add a physical count.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="!!adjustmentTarget" @close="adjustmentTarget = null" max-width="md">
            <form @submit.prevent="submitAdjustment" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Adjust Finished Goods</h2>
                <p class="mt-1 text-sm text-gray-600">{{ selectedVariantLabel }}</p>

                <div class="mt-5">
                    <InputLabel for="product_variant_id" value="Product variant" />
                    <SearchableSelect id="product_variant_id" v-model="adjustmentForm.product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                    <InputError :message="adjustmentForm.errors.product_variant_id" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="fg_counted_quantity" value="Counted quantity" />
                    <TextInput
                        id="fg_counted_quantity"
                        type="number"
                        min="0"
                        step="1"
                        v-model="adjustmentForm.counted_quantity"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="adjustmentForm.errors.counted_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="fg_alert_quantity" value="Alert quantity" />
                    <TextInput
                        id="fg_alert_quantity"
                        type="number"
                        min="0"
                        step="1"
                        v-model="adjustmentForm.alert_quantity"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="adjustmentForm.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="fg_note" value="Reason / note" />
                    <TextInput id="fg_note" v-model="adjustmentForm.note" class="mt-1 block w-full" />
                    <InputError :message="adjustmentForm.errors.note" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="adjustmentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': adjustmentForm.processing }" :disabled="adjustmentForm.processing">
                        Save Adjustment
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="openingTarget" @close="openingTarget = false" max-width="md">
            <form @submit.prevent="submitOpeningStock" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Set Finished Goods Opening Stock</h2>
                <p class="mt-1 text-sm text-gray-600">Use this for stock already available before using this system.</p>

                <div class="mt-5">
                    <InputLabel for="opening_product_variant_id" value="Product variant" />
                    <SearchableSelect id="opening_product_variant_id" v-model="openingForm.product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                    <InputError :message="openingForm.errors.product_variant_id" class="mt-1" />
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="fg_opening_quantity" value="Opening quantity" />
                        <TextInput id="fg_opening_quantity" v-model="openingForm.quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.quantity" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="fg_opening_unit_cost" value="Unit cost" />
                        <TextInput id="fg_opening_unit_cost" v-model="openingForm.unit_cost" type="number" min="0" step="0.0001" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.unit_cost" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="fg_opening_alert_quantity" value="Alert quantity" />
                    <TextInput id="fg_opening_alert_quantity" v-model="openingForm.alert_quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="fg_opening_note" value="Note" />
                    <TextInput id="fg_opening_note" v-model="openingForm.note" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.note" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="openingTarget = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': openingForm.processing }" :disabled="openingForm.processing">
                        Save Opening Stock
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

