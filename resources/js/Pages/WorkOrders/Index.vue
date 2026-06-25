<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import InputError from '@/Components/InputError.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ workOrders: Array, variantOptions: Array, today: String, openCreate: Boolean });
const table = useTableControls(() => props.workOrders, ['code', 'product', 'status', 'target_delivery_date']);
const showForm = ref(props.openCreate ?? false);
const editingWorkOrder = ref(null);
const deleteWorkOrder = ref(null);
const form = useForm({
    product_variant_id: '',
    quantity: 1,
    target_delivery_date: '',
    notes: '',
});
const deleteForm = useForm({});

const statusClass = (s) => ({
    draft: 'bg-gray-100 text-gray-700',
    in_production: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
}[s] ?? 'bg-gray-100 text-gray-700');

function openCreate() {
    editingWorkOrder.value = null;
    form.clearErrors();
    form.product_variant_id = '';
    form.quantity = 1;
    form.target_delivery_date = '';
    form.notes = '';
    showForm.value = true;
}

function openEdit(workOrder) {
    editingWorkOrder.value = workOrder;
    form.clearErrors();
    form.product_variant_id = workOrder.product_variant_id;
    form.quantity = workOrder.quantity;
    form.target_delivery_date = workOrder.target_delivery_date || '';
    form.notes = workOrder.notes || '';
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            editingWorkOrder.value = null;
            form.reset();
        },
    };

    if (editingWorkOrder.value) {
        form.put(route('work-orders.update', editingWorkOrder.value.id), options);
        return;
    }

    form.post(route('work-orders.store'), options);
}

function confirmDelete() {
    deleteForm.delete(route('work-orders.destroy', deleteWorkOrder.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteWorkOrder.value = null),
    });
}
</script>

<template>
    <Head title="Work Orders" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Work Orders</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ New Work Order</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search work orders...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Target delivery</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="w in table.rows.value" :key="w.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ w.code }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ w.product }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ w.quantity }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ w.target_delivery_date || '-' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(w.status)">
                                        {{ w.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="eye" :href="route('work-orders.show', w.id)">View</ActionMenuItem>
                                        <ActionMenuItem v-if="w.can_edit" icon="edit" @click="openEdit(w)">Edit</ActionMenuItem>
                                        <ActionMenuItem v-if="w.can_delete" icon="trash" danger @click="deleteWorkOrder = w">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No work orders yet. Create one to start production.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">{{ editingWorkOrder ? 'Edit Work Order' : 'New Work Order' }}</h2>
                <p v-if="editingWorkOrder?.status === 'in_production'" class="mt-1 text-sm text-amber-700">
                    Changing product or quantity will reverse the current material allocation and issue the new allocation from stock.
                </p>
                <p v-if="editingWorkOrder?.allocation_locked" class="mt-1 text-sm text-amber-700">
                    Product and quantity are locked because job cards already exist. You can still update the target date and notes.
                </p>
                <div v-if="form.errors.release || form.errors.work_order" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {{ form.errors.release || form.errors.work_order }}
                </div>

                <div class="mt-5">
                    <InputLabel for="variant" value="Product variant" />
                    <SearchableSelect
                        id="variant"
                        v-model="form.product_variant_id"
                        :options="variantOptions"
                        placeholder="Search product variant..."
                        no-results-text="No variants with recipes found"
                        :disabled="editingWorkOrder?.allocation_locked"
                        class="mt-1"
                    />
                    <InputError :message="form.errors.product_variant_id" class="mt-1" />
                    <p v-if="variantOptions.length === 0" class="mt-1 text-xs text-amber-700">
                        No variants have recipes yet. Define a recipe first.
                    </p>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="quantity" value="Quantity (garments)" />
                        <TextInput
                            id="quantity"
                            v-model="form.quantity"
                            type="number"
                            min="1"
                            step="1"
                            :disabled="editingWorkOrder?.allocation_locked"
                            class="mt-1 block w-full disabled:cursor-not-allowed disabled:opacity-70"
                        />
                        <InputError :message="form.errors.quantity" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="target" value="Target delivery date" />
                        <DatePicker id="target" v-model="form.target_delivery_date" class="mt-1" />
                        <InputError :message="form.errors.target_delivery_date" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="notes" value="Notes (optional)" />
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    />
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                        {{ editingWorkOrder ? 'Save' : 'Create' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteWorkOrder"
            title="Delete work order?"
            message="Draft orders are removed directly. In-production orders will return allocated materials to stock before deletion."
            confirmText="Delete"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteWorkOrder = null"
        />
    </AuthenticatedLayout>
</template>
