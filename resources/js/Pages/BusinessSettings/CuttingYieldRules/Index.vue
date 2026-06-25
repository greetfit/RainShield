<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    rules: Array,
    rawMaterialOptions: Array,
    variantOptions: Array,
    partOptions: Array,
    selectedVariant: Object,
});

const table = useTableControls(() => props.rules, ['material_label', 'product_label', 'part', 'yield_per_material_unit']);
const rawMaterialSearchOptions = computed(() => props.rawMaterialOptions.map((material) => ({
    value: material.id,
    label: `${material.label} (${material.unit})`,
})));
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));
const showForm = ref(false);
const editing = ref(null);
const deleteTarget = ref(null);
const form = useForm({
    raw_material_variant_id: '',
    product_variant_id: '',
    part_id: '',
    yield_per_material_unit: '',
    is_active: true,
});
const deleteForm = useForm({});

const selectedMaterial = computed(() => props.rawMaterialOptions.find((item) => Number(item.id) === Number(form.raw_material_variant_id)));
const pageTitle = computed(() => (props.selectedVariant ? `${props.selectedVariant.label} Yield Rules` : 'Cutting Yield Rules'));
const availablePartOptions = computed(() => {
    const materialId = Number(form.raw_material_variant_id);
    const variantId = Number(form.product_variant_id);

    if (!variantId) {
        return props.partOptions;
    }

    const usedPartIds = new Set(
        props.rules
            .filter((rule) => Number(rule.id) !== Number(editing.value?.id))
            .filter((rule) => Number(rule.product_variant_id) === variantId)
            .filter((rule) => !materialId || Number(rule.raw_material_variant_id) === materialId)
            .map((rule) => Number(rule.part_id)),
    );

    return props.partOptions.filter((part) => !usedPartIds.has(Number(part.id)));
});
const availablePartSearchOptions = computed(() => availablePartOptions.value.map((part) => ({
    value: part.id,
    label: part.name,
})));
const exampleText = computed(() => {
    const yieldValue = Number(form.yield_per_material_unit || 0);
    if (yieldValue <= 0) return 'Enter how many pieces one material unit can produce.';

    return `1 ${selectedMaterial.value?.unit ?? 'unit'} can produce ${yieldValue.toLocaleString(undefined, { maximumFractionDigits: 3 })} piece(s).`;
});

function resetForm() {
    form.raw_material_variant_id = '';
    form.product_variant_id = props.selectedVariant?.id ?? '';
    form.part_id = '';
    form.yield_per_material_unit = '';
    form.is_active = true;
}

function openCreate() {
    editing.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(rule) {
    editing.value = rule;
    form.clearErrors();
    form.raw_material_variant_id = rule.raw_material_variant_id;
    form.product_variant_id = rule.product_variant_id;
    form.part_id = rule.part_id;
    form.yield_per_material_unit = rule.yield_per_material_unit;
    form.is_active = Boolean(rule.is_active);
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
        form.put(route('business-settings.cutting-yield-rules.update', editing.value.id), options);
        return;
    }

    form.post(route('business-settings.cutting-yield-rules.store'), options);
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.cutting-yield-rules.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}

watch(availablePartOptions, (parts) => {
    if (form.part_id && !parts.some((part) => Number(part.id) === Number(form.part_id))) {
        form.part_id = '';
    }
});
</script>

<template>
    <Head :title="pageTitle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ pageTitle }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-100">
                    <span v-if="selectedVariant">
                        Define material yield for {{ selectedVariant.label }}. Example: 1 roll of Normal Tapata can cut 40 arms for this variant.
                    </span>
                    <span v-else>
                        Define how many pieces a material unit can produce. Example: 1 roll of Normal Tapata can cut 40 Medium Arms.
                    </span>
                </div>

                <div class="mb-4 flex justify-end">
                    <PrimaryButton type="button" @click="openCreate">+ Add Rule</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search cutting yield rules...">
                    <div class="table-scroll">
                        <table class="min-w-[920px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Material</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product Variant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Part</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Yield / Unit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="rule in table.rows.value" :key="rule.id">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ rule.material_label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ rule.product_label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ rule.part }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                        {{ Number(rule.yield_per_material_unit).toLocaleString(undefined, { maximumFractionDigits: 3 }) }} / {{ rule.unit }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium" :class="rule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                                            {{ rule.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(rule)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = rule">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No yield rules yet. Add the normal output for each material, product variant, and part.
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
                <h2 class="text-lg font-medium text-gray-900">{{ editing ? 'Edit Yield Rule' : 'Add Yield Rule' }}</h2>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="raw_material_variant_id" value="Material / Variant" />
                        <SearchableSelect id="raw_material_variant_id" v-model="form.raw_material_variant_id" :options="rawMaterialSearchOptions" placeholder="Search material..." class="mt-1" />
                        <InputError :message="form.errors.raw_material_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="product_variant_id" value="Product variant" />
                        <div v-if="selectedVariant" class="mt-1 rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            {{ selectedVariant.label }}
                        </div>
                        <SearchableSelect v-else id="product_variant_id" v-model="form.product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                        <InputError :message="form.errors.product_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="part_id" value="Part" />
                        <SearchableSelect id="part_id" v-model="form.part_id" :options="availablePartSearchOptions" placeholder="Search part..." class="mt-1" />
                        <p v-if="form.raw_material_variant_id && form.product_variant_id && availablePartOptions.length === 0" class="mt-1 text-xs text-amber-600 dark:text-amber-300">
                            All parts already have yield rules for this material and product variant.
                        </p>
                        <InputError :message="form.errors.part_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="yield_per_material_unit" value="Pieces per material unit" />
                        <TextInput id="yield_per_material_unit" v-model="form.yield_per_material_unit" type="number" min="0.001" step="0.001" required class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ exampleText }}</p>
                        <InputError :message="form.errors.yield_per_material_unit" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                        Active
                    </label>
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
            title="Delete yield rule?"
            message="Cutting batches will stop auto-filling this yield."
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

