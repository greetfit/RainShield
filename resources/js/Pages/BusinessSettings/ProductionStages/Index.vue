<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ stages: Array });
const table = useTableControls(() => props.stages, ['name', 'slug', 'priority_level', 'description']);

const showForm = ref(false);
const editing = ref(null);
const deleteTarget = ref(null);
const form = useForm({ name: '', slug: '', priority_level: 1, description: '', is_active: true });
const deleteForm = useForm({});

function resetForm() {
    form.name = '';
    form.slug = '';
    form.priority_level = 1;
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    editing.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(stage) {
    editing.value = stage;
    form.clearErrors();
    form.name = stage.name;
    form.slug = stage.slug;
    form.priority_level = stage.priority_level;
    form.description = stage.description ?? '';
    form.is_active = Boolean(stage.is_active);
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
            editing.value = null;
        },
    };

    if (editing.value) {
        form.put(route('business-settings.production-stages.update', editing.value.id), options);
        return;
    }

    form.post(route('business-settings.production-stages.store'), options);
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.production-stages.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Production Stages" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Production Stages</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-100">
                    Production flow is controlled by priority: the first active stage converts raw material into recipe parts, middle stages move good quantity forward, and the last active stage adds finished goods.
                </div>
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Stage</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search stages...">
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Priority</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Stage</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="stage in table.rows.value" :key="stage.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ stage.priority_level }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ stage.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ stage.slug }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ stage.description || '-' }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                            :class="stage.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ stage.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(stage)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = stage">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="stages.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No stages yet. Add the first production stage.
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
                <h2 class="text-lg font-medium text-gray-900">{{ editing ? 'Edit Stage' : 'Add Stage' }}</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Stage name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="slug" value="Code" />
                        <TextInput id="slug" v-model="form.slug" class="mt-1 block w-full" placeholder="auto_from_name" />
                        <InputError :message="form.errors.slug" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="priority" value="Priority level" />
                        <TextInput id="priority" v-model="form.priority_level" type="number" min="1" step="1" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.priority_level" class="mt-1" />
                    </div>
                    <label class="mt-7 flex items-center gap-2 text-sm font-medium text-gray-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        Active
                    </label>
                    <div class="sm:col-span-2">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError :message="form.errors.description" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">
                        {{ editing ? 'Save' : 'Add' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteTarget"
            title="Delete stage?"
            message="Used stages cannot be deleted. Deactivate the stage if old records already use it."
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

