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
import { computed, ref } from 'vue';

const props = defineProps({
    title: String,
    items: Array,
    routeBase: String,
    singular: String,
});

const table = useTableControls(() => props.items, ['name', 'description', 'variants_count']);
const showForm = ref(false);
const editingId = ref(null);
const deleteTarget = ref(null);
const form = useForm({ name: '', description: '', is_active: true });
const deleteForm = useForm({});

const addLabel = computed(() => `+ Add ${props.singular}`);

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

function openEdit(item) {
    editingId.value = item.id;
    form.clearErrors();
    form.name = item.name;
    form.description = item.description ?? '';
    form.is_active = item.is_active;
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
        form.put(route(`${props.routeBase}.update`, editingId.value), opts);
    } else {
        form.post(route(`${props.routeBase}.store`), opts);
    }
}

function confirmDelete() {
    deleteForm.delete(route(`${props.routeBase}.destroy`, deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">{{ addLabel }}</PrimaryButton>
                </div>

                <TableControls :table="table" :placeholder="`Search ${title.toLowerCase()}...`">
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Variants</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="item in table.rows.value" :key="item.id">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ item.name }}</div>
                                        <div class="mt-1 text-sm text-gray-500">{{ item.description || '-' }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ item.variants_count }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="item.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                            {{ item.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(item)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = item">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No records yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900">{{ editingId ? `Edit ${singular}` : `Add ${singular}` }}</h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Description" />
                    <textarea id="description" v-model="form.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <label class="mt-4 flex items-center">
                    <Checkbox v-model:checked="form.is_active" />
                    <span class="ms-2 text-sm text-gray-600">Active</span>
                </label>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">{{ editingId ? 'Save' : 'Add' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteTarget"
            :title="`Delete ${singular}?`"
            :message="`${deleteTarget?.name} will be removed from the master list. Existing variants will keep working but this value will be cleared.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
