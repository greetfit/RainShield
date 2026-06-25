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

const props = defineProps({ categories: Array });
const table = useTableControls(() => props.categories, ['name', 'description', 'products_count']);

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', description: '', is_active: true });

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
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
        },
    };

    if (editingId.value) {
        form.put(route('masters.product-categories.update', editingId.value), opts);
    } else {
        form.post(route('masters.product-categories.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('masters.product-categories.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Product Categories" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Product Categories</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Category</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search categories...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="category in table.rows.value" :key="category.id">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ category.name }}</div>
                                    <div class="mt-1 text-sm text-gray-500">{{ category.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ category.products_count }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="category.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                    >
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
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No product categories yet. Add Rainwear, Winterwear, Jersey, etc.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    {{ editingId ? 'Edit Category' : 'Add Category' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Description" />
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
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
            title="Delete product category?"
            :message="`Products assigned to ${deleteTarget?.name} will keep working, but their category will be cleared.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

