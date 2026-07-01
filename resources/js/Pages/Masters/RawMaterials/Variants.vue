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
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ material: Object, variants: Array });
const table = useTableControls(() => props.variants, ['name', 'code']);

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', code: '', is_active: true });

function resetForm() {
    form.name = '';
    form.code = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(variant) {
    editingId.value = variant.id;
    form.clearErrors();
    form.name = variant.name;
    form.code = variant.code ?? '';
    form.is_active = variant.is_active;
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
        form.put(route('masters.raw-material-variants.update', editingId.value), opts);
    } else {
        form.post(route('masters.raw-material-variants.store', props.material.id), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
const duplicateForm = useForm({});

function duplicateVariant(variant) {
    duplicateForm.post(route('masters.raw-material-variants.duplicate', variant.id), {
        preserveScroll: true,
    });
}

function confirmDelete() {
    deleteForm.delete(route('masters.raw-material-variants.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head :title="`${material.name} — Variants`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('masters.raw-materials.index')" class="text-sm text-indigo-600 hover:underline">Raw Materials</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ material.name }} — Variants
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600">
                    Variants are the "types" of this material (measured in <strong>{{ material.unit }}</strong>).
                </p>
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Variant</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search variants...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Variant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="variant in table.rows.value" :key="variant.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ variant.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ variant.code || '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="variant.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ variant.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="copy" @click="duplicateVariant(variant)">Duplicate</ActionMenuItem>
                                        <ActionMenuItem icon="edit" @click="openEdit(variant)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = variant">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No variants yet. Add types like single layer, double layer, A grade…
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
                    {{ editingId ? 'Edit Variant' : 'Add Variant' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Variant name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="code" value="Code (optional)" />
                    <TextInput id="code" v-model="form.code" class="mt-1 block w-full" />
                    <InputError :message="form.errors.code" class="mt-1" />
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
            title="Delete variant?"
            :message="`This will permanently remove ${deleteTarget?.name}.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

