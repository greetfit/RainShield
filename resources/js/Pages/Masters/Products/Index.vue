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

const props = defineProps({ products: Array, categoryOptions: Array, sourceOptions: Array });
const table = useTableControls(() => props.products, ['name', 'category', 'source_label', 'variants_count']);
const categorySearchOptions = computed(() => [
    { value: '', label: 'No category' },
    ...props.categoryOptions.map((category) => ({ value: category.id, label: category.name })),
]);

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', product_category_id: '', source_type: 'in_house', description: '', is_active: true });

function resetForm() {
    form.name = '';
    form.product_category_id = '';
    form.source_type = 'in_house';
    form.description = '';
    form.is_active = true;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(product) {
    editingId.value = product.id;
    form.clearErrors();
    form.name = product.name;
    form.product_category_id = product.product_category_id ?? '';
    form.source_type = product.source_type ?? 'in_house';
    form.description = product.description ?? '';
    form.is_active = product.is_active;
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
        form.put(route('masters.products.update', editingId.value), opts);
    } else {
        form.post(route('masters.products.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('masters.products.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Products" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Products</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Product</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search products...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Variants</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="product in table.rows.value" :key="product.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ product.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ product.category || '-' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ product.source_label }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <Link
                                        :href="route('masters.products.variants', product.id)"
                                        class="inline-flex items-center whitespace-nowrap rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950 dark:text-indigo-200 dark:hover:bg-indigo-900"
                                    >
                                        {{ product.variants_count }} variant(s) / manage
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="product.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ product.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="recipe" :href="route('masters.products.variants', product.id)">Manage variants</ActionMenuItem>
                                        <ActionMenuItem icon="edit" @click="openEdit(product)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = product">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No products yet. Add raincoat, bike jacket, sweater, jersey…
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
                    {{ editingId ? 'Edit Product' : 'Add Product' }}
                </h2>

                <div class="mt-4">
                    <InputLabel for="name" value="Name" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="product_category_id" value="Category" />
                    <SearchableSelect id="product_category_id" v-model="form.product_category_id" :options="categorySearchOptions" placeholder="Search category..." class="mt-1" />
                    <InputError :message="form.errors.product_category_id" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="source_type" value="Product source" />
                    <select
                        id="source_type"
                        v-model="form.source_type"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option v-for="source in sourceOptions" :key="source.value" :value="source.value">
                            {{ source.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.source_type" class="mt-1" />
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
            title="Delete product?"
            :message="`This removes ${deleteTarget?.name} and all its variants.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
