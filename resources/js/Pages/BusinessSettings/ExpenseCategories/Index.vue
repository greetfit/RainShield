<script setup>
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import Checkbox from '@/Components/Checkbox.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TableControls from '@/Components/TableControls.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ categories: Array });
const table = useTableControls(() => props.categories, ['name', 'description']);
const showForm = ref(false);
const editingId = ref(null);
const deleteTarget = ref(null);
const form = useForm({ name: '', description: '', is_active: true });
const deleteForm = useForm({});

function resetForm() {
    form.name = '';
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(category) {
    editingId.value = category.id;
    form.clearErrors();
    form.name = category.name;
    form.description = category.description ?? '';
    form.is_active = category.is_active;
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
        form.put(route('business-settings.expense-categories.update', editingId.value), options);
    } else {
        form.post(route('business-settings.expense-categories.store'), options);
    }
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.expense-categories.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Expense Categories" />

    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Expense Categories</h2></template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Category</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search expense categories...">
                    <div class="table-scroll">
                        <table class="min-w-[760px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="category in table.rows.value" :key="category.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ category.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ category.description || '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="category.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                            {{ category.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(category)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = category">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No expense categories yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="md">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ editingId ? 'Edit Expense Category' : 'Add Expense Category' }}</h2>
                <div class="mt-4">
                    <InputLabel for="expense_category_name" value="Name" />
                    <TextInput id="expense_category_name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div class="mt-4">
                    <InputLabel for="expense_category_description" value="Description" />
                    <TextInput id="expense_category_description" v-model="form.description" class="mt-1 block w-full" />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>
                <label class="mt-4 flex items-center">
                    <Checkbox v-model:checked="form.is_active" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">Active</span>
                </label>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">{{ editingId ? 'Save' : 'Add' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteTarget"
            title="Delete expense category?"
            :message="`${deleteTarget?.name} will be removed if it has no expenses.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
