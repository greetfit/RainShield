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

const props = defineProps({ units: Array });
const table = useTableControls(() => props.units, ['name', 'description', 'materials_count']);

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

function openEdit(unit) {
    editingId.value = unit.id;
    form.clearErrors();
    form.name = unit.name;
    form.description = unit.description ?? '';
    form.is_active = unit.is_active;
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
        form.put(route('business-settings.unit-of-measures.update', editingId.value), options);
    } else {
        form.post(route('business-settings.unit-of-measures.store'), options);
    }
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.unit-of-measures.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Units of Measure" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Units of Measure</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Unit</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search units...">
                    <div class="table-scroll">
                        <table class="min-w-[900px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Materials</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="unit in table.rows.value" :key="unit.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ unit.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ unit.description || '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-700">{{ unit.materials_count }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="unit.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                            {{ unit.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(unit)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = unit">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No units yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="md">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">{{ editingId ? 'Edit Unit' : 'Add Unit' }}</h2>

                <div class="mt-4">
                    <InputLabel for="unit_name" value="Unit name" />
                    <TextInput id="unit_name" v-model="form.name" class="mt-1 block w-full" placeholder="roll" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="unit_description" value="Description" />
                    <TextInput id="unit_description" v-model="form.description" class="mt-1 block w-full" placeholder="Optional" />
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
            title="Delete unit?"
            :message="`${deleteTarget?.name} will be removed only if it is not used by raw materials.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
