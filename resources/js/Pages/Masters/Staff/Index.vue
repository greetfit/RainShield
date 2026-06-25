<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PhoneInput from '@/Components/PhoneInput.vue';
import CopyPhoneButton from '@/Components/CopyPhoneButton.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ staff: Array, designationOptions: Array });
const table = useTableControls(() => props.staff, ['name', 'phone', 'designation', 'designation_priority_level', 'salary_type_label']);
const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const designationSearchOptions = computed(() => props.designationOptions.map((designation) => ({
    value: designation.id,
    label: designationLabel(designation),
})));

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', phone: '', designation_id: '', salary_type: 'piece_rate', monthly_salary: '', is_active: true });

function resetForm() {
    form.name = '';
    form.phone = '';
    form.designation_id = '';
    form.salary_type = 'piece_rate';
    form.monthly_salary = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(member) {
    editingId.value = member.id;
    form.clearErrors();
    form.name = member.name;
    form.phone = member.phone ?? '';
    form.designation_id = member.designation_id ?? '';
    form.salary_type = member.salary_type ?? 'piece_rate';
    form.monthly_salary = member.monthly_salary ?? '';
    form.is_active = member.is_active;
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
        form.put(route('masters.staff.update', editingId.value), opts);
    } else {
        form.post(route('masters.staff.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('masters.staff.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}

function formatPhone(value) {
    const digits = String(value ?? '').replace(/\D/g, '');
    if (digits.length !== 10) {
        return value || '-';
    }

    return `${digits.slice(0, 3)} ${digits.slice(3, 6)} ${digits.slice(6)}`;
}

function designationLabel(designation) {
    return designation.priority_level
        ? `${designation.name} - Priority ${designation.priority_level}`
        : designation.name;
}
</script>

<template>
    <Head title="Staff" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Staff</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Staff</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search staff...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Designation</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Salary</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="member in table.rows.value" :key="member.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ member.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span class="inline-flex items-center gap-2">
                                        <span>{{ formatPhone(member.phone) }}</span>
                                        <CopyPhoneButton :phone="member.phone" />
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div>{{ member.designation || '-' }}</div>
                                    <div v-if="member.designation_priority_level" class="mt-1 text-xs text-gray-400">
                                        Priority {{ member.designation_priority_level }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div>{{ member.salary_type_label }}</div>
                                    <div v-if="member.salary_type === 'monthly'" class="text-xs text-gray-400">{{ money(member.monthly_salary || 0) }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="member.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ member.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openEdit(member)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = member">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No staff yet. Add the people who cut, stitch and pack.
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
                    {{ editingId ? 'Edit Staff' : 'Add Staff' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="phone" value="Phone (optional)" />
                    <PhoneInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
                    <InputError :message="form.errors.phone" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="designation_id" value="Designation" />
                    <SearchableSelect id="designation_id" v-model="form.designation_id" :options="designationSearchOptions" placeholder="Search designation..." class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Designation priority links this staff member to the matching production stage.
                    </p>
                    <InputError :message="form.errors.designation_id" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="salary_type" value="Salary type" />
                    <select
                        id="salary_type"
                        v-model="form.salary_type"
                        class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    >
                        <option value="piece_rate">Piece rate</option>
                        <option value="monthly">Monthly salary</option>
                    </select>
                    <InputError :message="form.errors.salary_type" class="mt-1" />
                </div>

                <div v-if="form.salary_type === 'monthly'" class="mt-4">
                    <InputLabel for="monthly_salary" value="Monthly salary" />
                    <TextInput id="monthly_salary" v-model="form.monthly_salary" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                    <InputError :message="form.errors.monthly_salary" class="mt-1" />
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
            title="Delete staff member?"
            :message="`This will permanently remove ${deleteTarget?.name}.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
