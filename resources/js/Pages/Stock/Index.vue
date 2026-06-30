<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ rows: Array, totalValue: Number });
const table = useTableControls(() => props.rows, ['label', 'unit', 'quantity', 'average_cost', 'value']);

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const qty = (n) => Number(n).toLocaleString(undefined, { maximumFractionDigits: 3 });

const adjustmentTarget = ref(null);
const openingTarget = ref(null);
const adjustmentForm = useForm({
    raw_material_variant_id: '',
    counted_quantity: '',
    note: '',
});
const openingForm = useForm({
    raw_material_variant_id: '',
    quantity: '',
    unit_cost: '',
    note: '',
});

function openOpening(row) {
    openingTarget.value = row;
    openingForm.clearErrors();
    openingForm.raw_material_variant_id = row.id;
    openingForm.quantity = row.quantity;
    openingForm.unit_cost = row.average_cost;
    openingForm.note = '';
}

function openAdjustment(row) {
    adjustmentTarget.value = row;
    adjustmentForm.clearErrors();
    adjustmentForm.raw_material_variant_id = row.id;
    adjustmentForm.counted_quantity = row.quantity;
    adjustmentForm.note = '';
}

function submitAdjustment() {
    adjustmentForm.post(route('stock.adjust'), {
        preserveScroll: true,
        onSuccess: () => (adjustmentTarget.value = null),
    });
}

function submitOpening() {
    openingForm.post(route('stock.opening'), {
        preserveScroll: true,
        onSuccess: () => (openingTarget.value = null),
    });
}
</script>

<template>
    <Head title="Stock" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Raw Material Stock</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between">
                    <div class="rounded-lg bg-white px-6 py-4 shadow-sm dark:bg-gray-900">
                        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total stock value</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ money(totalValue) }}</div>
                    </div>
                    <Link :href="route('stock.movements')">
                        <SecondaryButton type="button">
                            <span class="inline-flex items-center gap-2">
                                <AppIcon name="clipboard" />
                                View movement ledger
                            </span>
                        </SecondaryButton>
                    </Link>
                </div>

                <TableControls :table="table" placeholder="Search stock...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Material - Variant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">On hand</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg cost</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Value</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <tr v-for="r in table.rows.value" :key="r.id" :class="r.quantity <= 0 ? 'bg-amber-50 dark:bg-amber-950/20' : 'bg-white dark:bg-gray-900'">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ r.label }}</td>
                                <td class="px-6 py-4 text-right text-sm" :class="r.quantity <= 0 ? 'font-medium text-amber-700 dark:text-amber-300' : 'text-gray-700 dark:text-gray-300'">
                                    {{ qty(r.quantity) }} {{ r.unit }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ money(r.average_cost) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(r.value) }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="warehouse" @click="openOpening(r)">Opening Stock</ActionMenuItem>
                                        <ActionMenuItem icon="edit" @click="openAdjustment(r)">Adjust</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No material variants yet. Add them under Masters - Raw Materials.
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
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Adjust Raw Stock</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ adjustmentTarget?.label }}</p>

                <div class="mt-5">
                    <InputLabel for="counted_quantity" value="Counted quantity" />
                    <TextInput
                        id="counted_quantity"
                        type="number"
                        min="0"
                        step="0.001"
                        v-model="adjustmentForm.counted_quantity"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="adjustmentForm.errors.counted_quantity" class="mt-1" />
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

        <Modal :show="!!openingTarget" @close="openingTarget = null" max-width="md">
            <form @submit.prevent="submitOpening" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Set Opening Stock</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ openingTarget?.label }}</p>

                <div class="mt-5">
                    <InputLabel for="opening_quantity" value="Opening quantity" />
                    <TextInput
                        id="opening_quantity"
                        type="number"
                        min="0"
                        step="0.001"
                        v-model="openingForm.quantity"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="openingForm.errors.quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="opening_unit_cost" value="Unit cost" />
                    <TextInput
                        id="opening_unit_cost"
                        type="number"
                        min="0"
                        step="0.0001"
                        v-model="openingForm.unit_cost"
                        class="mt-1 block w-full"
                    />
                    <InputError :message="openingForm.errors.unit_cost" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="opening_note" value="Note" />
                    <TextInput id="opening_note" v-model="openingForm.note" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.note" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="openingTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': openingForm.processing }" :disabled="openingForm.processing">
                        Save Opening Stock
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

