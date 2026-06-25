<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ users: Array, roles: Array });
const table = useTableControls(() => props.users, ['name', 'email', 'role', 'roles']);

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({
    name: '',
    email: '',
    role: 'viewer',
    password: '',
    password_confirmation: '',
});

function resetForm() {
    form.name = '';
    form.email = '';
    form.role = props.roles.includes('viewer') ? 'viewer' : props.roles[0] ?? '';
    form.password = '';
    form.password_confirmation = '';
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(user) {
    editingId.value = user.id;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role ?? 'viewer';
    form.password = '';
    form.password_confirmation = '';
    showForm.value = true;
}

function roleLabel(role) {
    return String(role || '')
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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
        form.put(route('business-settings.system-users.update', editingId.value), opts);
    } else {
        form.post(route('business-settings.system-users.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('business-settings.system-users.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="System Users" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">System Users</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add User</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search system users...">
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Verified</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="user in table.rows.value" :key="user.id" class="dark:bg-gray-900">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                                            {{ roleLabel(user.role) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="user.verified ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                            {{ user.verified ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ user.created_at || '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(user)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = user">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No system users yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false">
            <form @submit.prevent="submit" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ editingId ? 'Edit User' : 'Add User' }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Name" />
                        <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                        <InputError :message="form.errors.name" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="role" value="Role" />
                        <select id="role" v-model="form.role" class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option v-for="role in roles" :key="role" :value="role">{{ roleLabel(role) }}</option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <InputLabel value="Password rule" />
                        <p class="mt-2 rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            {{ editingId ? 'Leave password blank to keep the current password.' : 'Password is required for new users.' }}
                        </p>
                    </div>
                    <div>
                        <InputLabel for="password" value="Password" />
                        <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Confirm password" />
                        <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    </div>
                </div>

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
            title="Delete system user?"
            :message="`${deleteTarget?.name} will no longer be able to log in.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
