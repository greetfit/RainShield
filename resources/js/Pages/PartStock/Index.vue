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

const props = defineProps({ rows: Array, variantOptions: Array, partOptions: Array });
const table = useTableControls(() => props.rows, ['label', 'part', 'stock_type_label', 'quantity', 'alert_quantity']);
const qty = (n) => Number(n).toLocaleString();
const money = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));
const partSearchOptions = computed(() => props.partOptions.map((part) => ({
    value: part.id,
    label: part.name,
})));

const adjustmentTarget = ref(null);
const adjustmentForm = useForm({
    product_variant_id: '',
    part_id: '',
    stock_type: 'good',
    counted_quantity: '',
    alert_quantity: '',
    note: '',
});
const openingTarget = ref(false);
const openingForm = useForm({
    product_variant_id: '',
    part_id: '',
    stock_type: 'good',
    quantity: '',
    unit_cost: '',
    alert_quantity: '',
    note: '',
});

function openAdjustment(row) {
    adjustmentTarget.value = row;
    adjustmentForm.clearErrors();
    adjustmentForm.product_variant_id = row.product_variant_id;
    adjustmentForm.part_id = row.part_id;
    adjustmentForm.stock_type = row.stock_type;
    adjustmentForm.counted_quantity = row.quantity;
    adjustmentForm.alert_quantity = row.alert_quantity ?? 0;
    adjustmentForm.note = '';
}

function submitAdjustment() {
    adjustmentForm.post(route('part-stock.adjust'), {
        preserveScroll: true,
        onSuccess: () => (adjustmentTarget.value = null),
    });
}

function openOpeningStock() {
    openingTarget.value = true;
    openingForm.clearErrors();
    openingForm.reset();
    openingForm.stock_type = 'good';
}

function submitOpeningStock() {
    openingForm.post(route('part-stock.opening'), {
        preserveScroll: true,
        onSuccess: () => (openingTarget.value = false),
    });
}
</script>

<template>
    <Head title="Part Stock" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Part Stock</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap justify-end gap-3">
                    <Link :href="route('part-stock.movements')">
                        <SecondaryButton type="button">
                            <span class="inline-flex items-center gap-2">
                                <AppIcon name="clipboard" />
                                View movement ledger
                            </span>
                        </SecondaryButton>
                    </Link>
                    <PrimaryButton type="button" @click="openOpeningStock">
                        <span class="inline-flex items-center gap-2">
                            <AppIcon name="warehouse" />
                            Opening Stock
                        </span>
                    </PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search part stock...">
                    <div class="table-scroll">
                        <table class="min-w-[1060px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product Variant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Part</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Stock Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Avg Cost</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Value</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Alert At</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="row in table.rows.value" :key="row.key">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ row.label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ row.part }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="row.stock_type === 'recoverable' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                        >
                                            {{ row.stock_type_label }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ qty(row.quantity) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">{{ money(row.average_cost) }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(row.value) }}</td>
                                    <td class="px-6 py-4 text-right text-sm" :class="Number(row.alert_quantity || 0) > 0 && Number(row.quantity) <= Number(row.alert_quantity) ? 'font-semibold text-amber-700' : 'text-gray-600'">
                                        {{ qty(row.alert_quantity || 0) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openAdjustment(row)">Adjust</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No part stock yet. Create a cutting batch to produce good or recoverable parts.
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
                <h2 class="text-lg font-medium text-gray-900">Adjust Part Stock</h2>
                <p class="mt-1 text-sm text-gray-600">{{ adjustmentTarget?.label }} - {{ adjustmentTarget?.part }}</p>

                <div class="mt-5">
                    <InputLabel for="counted_quantity" value="Counted quantity" />
                    <TextInput
                        id="counted_quantity"
                        v-model="adjustmentForm.counted_quantity"
                        type="number"
                        min="0"
                        step="1"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="adjustmentForm.errors.counted_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="alert_quantity" value="Alert quantity" />
                    <TextInput
                        id="alert_quantity"
                        v-model="adjustmentForm.alert_quantity"
                        type="number"
                        min="0"
                        step="1"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="adjustmentForm.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="note" value="Reason / note" />
                    <TextInput id="note" v-model="adjustmentForm.note" class="mt-1 block w-full" />
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

        <Modal :show="openingTarget" @close="openingTarget = false" max-width="lg">
            <form @submit.prevent="submitOpeningStock" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Set Part Opening Stock</h2>
                <p class="mt-1 text-sm text-gray-600">Use this for already cut parts before the system starts tracking production.</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="part_opening_product_variant_id" value="Product variant" />
                        <SearchableSelect id="part_opening_product_variant_id" v-model="openingForm.product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                        <InputError :message="openingForm.errors.product_variant_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="part_opening_part_id" value="Part" />
                        <SearchableSelect id="part_opening_part_id" v-model="openingForm.part_id" :options="partSearchOptions" placeholder="Search part..." class="mt-1" />
                        <InputError :message="openingForm.errors.part_id" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="part_opening_stock_type" value="Stock type" />
                        <select
                            id="part_opening_stock_type"
                            v-model="openingForm.stock_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="good">Good</option>
                            <option value="recoverable">Recoverable</option>
                        </select>
                        <InputError :message="openingForm.errors.stock_type" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="part_opening_quantity" value="Opening quantity" />
                        <TextInput id="part_opening_quantity" v-model="openingForm.quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.quantity" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel for="part_opening_unit_cost" value="Unit cost" />
                        <TextInput id="part_opening_unit_cost" v-model="openingForm.unit_cost" type="number" min="0" step="0.0001" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.unit_cost" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="part_opening_alert_quantity" value="Alert quantity" />
                    <TextInput id="part_opening_alert_quantity" v-model="openingForm.alert_quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="part_opening_note" value="Note" />
                    <TextInput id="part_opening_note" v-model="openingForm.note" class="mt-1 block w-full" />
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

