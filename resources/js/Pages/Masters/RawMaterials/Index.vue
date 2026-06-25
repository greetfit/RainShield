<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ materials: Array, units: Array });
const table = useTableControls(() => props.materials, ['name', 'unit', 'alert_quantity', 'variants_count']);
const unitSearchOptions = computed(() => props.units.map((unit) => ({ value: unit, label: unit })));

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', unit: 'piece', alert_quantity: 0, description: '', is_active: true });

function resetForm() {
    form.name = '';
    form.unit = 'piece';
    form.alert_quantity = 0;
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(material) {
    editingId.value = material.id;
    form.clearErrors();
    form.name = material.name;
    form.unit = material.unit;
    form.alert_quantity = material.alert_quantity ?? 0;
    form.description = material.description ?? '';
    form.is_active = material.is_active;
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
        form.put(route('masters.raw-materials.update', editingId.value), opts);
    } else {
        form.post(route('masters.raw-materials.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('masters.raw-materials.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Raw Materials" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Raw Materials</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Material</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search raw materials...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Material</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Alert at</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Variants</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="material in table.rows.value" :key="material.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ material.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ material.unit }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ Number(material.alert_quantity || 0).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <Link
                                        :href="route('masters.raw-materials.variants', material.id)"
                                        class="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-200 dark:hover:bg-indigo-900"
                                    >
                                        {{ material.variants_count }} variant(s) / manage
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="material.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ material.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="recipe" :href="route('masters.raw-materials.variants', material.id)">Manage variants</ActionMenuItem>
                                        <ActionMenuItem icon="edit" @click="openEdit(material)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = material">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No materials yet. Add cloth, zip, badge, rope, thread, elastic, buttons, packing…
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
                    {{ editingId ? 'Edit Material' : 'Add Material' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="unit" value="Unit of measure" />
                    <SearchableSelect id="unit" v-model="form.unit" :options="unitSearchOptions" placeholder="Search unit..." class="mt-1" />
                    <InputError :message="form.errors.unit" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="alert_quantity" value="Alert quantity" />
                    <TextInput id="alert_quantity" v-model="form.alert_quantity" type="number" min="0" step="0.001" class="mt-1 block w-full" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Dashboard alert shows when total stock for this material reaches this quantity.</p>
                    <InputError :message="form.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="description" value="Description (optional)" />
                    <textarea id="description" v-model="form.description" rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
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
            title="Delete material?"
            :message="`This removes ${deleteTarget?.name} and all its variants.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
