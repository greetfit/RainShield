<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ methods: Array });
const table = useTableControls(() => props.methods, ['name', 'label', 'description']);

const showForm = ref(false);
const editingId = ref(null);
const deleteTarget = ref(null);
const form = useForm({ name: '', label: '', description: '', is_active: true });
const deleteForm = useForm({});

function resetForm() {
    form.name = '';
    form.label = '';
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(method) {
    editingId.value = method.id;
    form.clearErrors();
    form.name = method.name;
    form.label = method.label;
    form.description = method.description ?? '';
    form.is_active = method.is_active;
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
        },
    };

    if (editingId.value) {
        form.put(route('business-settings.payment-methods.update', editingId.value), options);
    } else {
        form.post(route('business-settings.payment-methods.store'), options);
    }
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.payment-methods.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Payment Methods" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Payment Methods</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Method</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search payment methods...">
                    <div class="table-scroll">
                        <table class="min-w-[900px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="method in table.rows.value" :key="method.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ method.label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ method.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ method.description || '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="method.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                            {{ method.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(method)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = method">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No payment methods yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="md">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">{{ editingId ? 'Edit Payment Method' : 'Add Payment Method' }}</h2>

                <div class="mt-4">
                    <InputLabel for="method_label" value="Label" />
                    <TextInput id="method_label" v-model="form.label" class="mt-1 block w-full" placeholder="Cash" autofocus />
                    <InputError :message="form.errors.label" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="method_name" value="Code" />
                    <TextInput id="method_name" v-model="form.name" class="mt-1 block w-full" placeholder="cash" />
                    <p class="mt-1 text-xs text-gray-500">Use lowercase letters, numbers, hyphen or underscore.</p>
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="method_description" value="Description" />
                    <TextInput id="method_description" v-model="form.description" class="mt-1 block w-full" placeholder="Optional" />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <label class="mt-4 flex items-center">
                    <Checkbox v-model:checked="form.is_active" />
                    <span class="ms-2 text-sm text-gray-600">Active</span>
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                        {{ editingId ? 'Save' : 'Add' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteTarget"
            title="Delete payment method?"
            :message="`${deleteTarget?.label} will be removed from future dropdowns. Existing payments keep their saved method text.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
