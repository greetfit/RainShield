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
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ customers: Array });
const table = useTableControls(() => props.customers, ['name', 'phone', 'email', 'address', 'notes']);

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', phone: '', email: '', address: '', notes: '', is_active: true });

function resetForm() {
    form.name = '';
    form.phone = '';
    form.email = '';
    form.address = '';
    form.notes = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(customer) {
    editingId.value = customer.id;
    form.clearErrors();
    form.name = customer.name;
    form.phone = customer.phone ?? '';
    form.email = customer.email ?? '';
    form.address = customer.address ?? '';
    form.notes = customer.notes ?? '';
    form.is_active = customer.is_active;
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
        form.put(route('masters.customers.update', editingId.value), opts);
    } else {
        form.post(route('masters.customers.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('masters.customers.destroy', deleteTarget.value.id), {
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
</script>

<template>
    <Head title="Customers" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Customers</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Customer</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search customers...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="customer in table.rows.value" :key="customer.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ customer.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <span class="inline-flex items-center gap-2">
                                        <span>{{ formatPhone(customer.phone) }}</span>
                                        <CopyPhoneButton :phone="customer.phone" />
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ customer.email || '-' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="customer.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ customer.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openEdit(customer)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = customer">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No customers yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingId ? 'Edit Customer' : 'Add Customer' }}</h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="phone" value="Phone" />
                        <PhoneInput id="phone" v-model="form.phone" class="mt-1 block w-full" />
                        <InputError :message="form.errors.phone" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" type="email" v-model="form.email" class="mt-1 block w-full" />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="address" value="Address" />
                    <textarea id="address" v-model="form.address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <InputError :message="form.errors.address" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="notes" value="Notes" />
                    <textarea id="notes" v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <InputError :message="form.errors.notes" class="mt-1" />
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
            title="Delete customer?"
            :message="`This will permanently remove ${deleteTarget?.name}.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
