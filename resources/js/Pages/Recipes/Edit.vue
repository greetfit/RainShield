<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    variant: Object,
    materialOptions: Array,
    materials: Array,
    partOptions: Array,
    parts: Array,
});

const showMaterial = ref(false);
const editingMaterialId = ref(null);
const materialForm = useForm({ raw_material_variant_id: '', quantity: 0 });
const materialDelete = ref(null);
const materialDeleteForm = useForm({});
const showPart = ref(false);
const editingPartId = ref(null);
const partForm = useForm({ part_id: '', quantity_per_garment: 1 });
const partDelete = ref(null);
const partDeleteForm = useForm({});

const usedMaterialIds = computed(() => props.materials.map((m) => m.raw_material_variant_id));
const availableMaterials = computed(() =>
    props.materialOptions.filter((o) => !usedMaterialIds.value.includes(o.id) || o.id === materialForm.raw_material_variant_id),
);
const availableMaterialSearchOptions = computed(() => availableMaterials.value.map((option) => ({
    value: option.id,
    label: `${option.label} (${option.unit})`,
})));
const usedPartIds = computed(() => props.parts.map((p) => p.part_id));
const availableParts = computed(() =>
    props.partOptions.filter((o) => !usedPartIds.value.includes(o.id) || o.id === partForm.part_id),
);
const availablePartSearchOptions = computed(() => availableParts.value.map((option) => ({
    value: option.id,
    label: option.name,
})));

function resetMaterialForm() {
    materialForm.raw_material_variant_id = '';
    materialForm.quantity = 0;
}

function openAddMaterial() {
    editingMaterialId.value = null;
    materialForm.clearErrors();
    resetMaterialForm();
    showMaterial.value = true;
}

function openEditMaterial(material) {
    editingMaterialId.value = material.id;
    materialForm.clearErrors();
    materialForm.raw_material_variant_id = material.raw_material_variant_id;
    materialForm.quantity = material.quantity;
    showMaterial.value = true;
}

function submitMaterial() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetMaterialForm();
            showMaterial.value = false;
        },
    };

    if (editingMaterialId.value) {
        materialForm.put(route('recipes.materials.update', editingMaterialId.value), options);
        return;
    }

    materialForm.post(route('recipes.materials.store', props.variant.id), options);
}

function confirmMaterialDelete() {
    materialDeleteForm.delete(route('recipes.materials.destroy', materialDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => (materialDelete.value = null),
    });
}

function resetPartForm() {
    partForm.part_id = '';
    partForm.quantity_per_garment = 1;
}

function openAddPart() {
    editingPartId.value = null;
    partForm.clearErrors();
    resetPartForm();
    showPart.value = true;
}

function openEditPart(part) {
    editingPartId.value = part.id;
    partForm.clearErrors();
    partForm.part_id = part.part_id;
    partForm.quantity_per_garment = part.quantity_per_garment;
    showPart.value = true;
}

function submitPart() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetPartForm();
            showPart.value = false;
        },
    };

    if (editingPartId.value) {
        partForm.put(route('recipes.parts.update', editingPartId.value), options);
        return;
    }

    partForm.post(route('recipes.parts.store', props.variant.id), options);
}

function confirmPartDelete() {
    partDeleteForm.delete(route('recipes.parts.destroy', partDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => (partDelete.value = null),
    });
}
</script>

<template>
    <Head :title="`Recipe - ${variant.product} ${variant.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('masters.products.index')" class="text-sm text-indigo-600 hover:underline">Products</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Recipe - {{ variant.product }} / {{ variant.name }}
                </h2>
            </div>
        </template>

        <div class="space-y-8 py-8">
            <div class="mx-auto max-w-6xl space-y-8 px-4 sm:px-6 lg:px-8">
                <p class="rounded-md bg-indigo-50 p-4 text-sm text-indigo-900 dark:bg-indigo-950/30 dark:text-indigo-100">
                    Recipe keeps both optional raw-material references and required pre-cut parts. Raw materials can include
                    Tapata, Zip, Thread, Elastic, Button, Packing, or any master material. Required parts are issued from Part Stock
                    when a work order is released.
                    <span v-if="variant.layer">
                        This is a <strong class="capitalize">{{ variant.layer }}-layer</strong> variant, so enter part counts accordingly.
                    </span>
                </p>

                <section class="table-scroll">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Raw materials reference</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional BOM reference for materials used with this product variant.</p>
                        </div>
                        <PrimaryButton type="button" @click="openAddMaterial">+ Add Material</PrimaryButton>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Material / Variant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty / garment</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <tr v-for="material in materials" :key="material.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ material.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">{{ Number(material.quantity || 0).toFixed(3) }} {{ material.unit }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openEditMaterial(material)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="materialDelete = material">Remove</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="materials.length === 0">
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No raw materials added yet. Add Tapata, Zip, Thread, Elastic, Button, Packing, or any material needed.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section class="table-scroll">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Required pre-cut parts</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">These are issued from Part Stock when a work order is released.</p>
                        </div>
                        <PrimaryButton type="button" @click="openAddPart">+ Add Part</PrimaryButton>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Part</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Qty / garment</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            <tr v-for="part in parts" :key="part.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ part.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300">x {{ part.quantity_per_garment }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openEditPart(part)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="partDelete = part">Remove</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="parts.length === 0">
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No parts yet. Add head, arms, body, or any required pre-cut part.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>

        <Modal :show="showMaterial" @close="showMaterial = false">
            <form @submit.prevent="submitMaterial" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Recipe Material</h2>

                <div class="mt-4">
                    <InputLabel for="material" value="Raw material / variant" />
                    <SearchableSelect
                        id="material"
                        v-model="materialForm.raw_material_variant_id"
                        :options="availableMaterialSearchOptions"
                        placeholder="Search material..."
                        :disabled="!!editingMaterialId"
                        class="mt-1"
                    />
                    <InputError :message="materialForm.errors.raw_material_variant_id" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="mqty" value="Quantity per garment" />
                    <TextInput id="mqty" v-model="materialForm.quantity" type="number" step="0.001" min="0.001" class="mt-1 block w-full" />
                    <InputError :message="materialForm.errors.quantity" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showMaterial = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': materialForm.processing }" :disabled="materialForm.processing">
                        {{ editingMaterialId ? 'Save' : 'Add' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showPart" @close="showPart = false">
            <form @submit.prevent="submitPart" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Required Part</h2>

                <div class="mt-4">
                    <InputLabel for="part" value="Part" />
                    <SearchableSelect
                        id="part"
                        v-model="partForm.part_id"
                        :options="availablePartSearchOptions"
                        placeholder="Search part..."
                        :disabled="!!editingPartId"
                        class="mt-1"
                    />
                    <InputError :message="partForm.errors.part_id" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="pqty" value="Quantity per garment" />
                    <TextInput id="pqty" v-model="partForm.quantity_per_garment" type="number" step="1" min="1" class="mt-1 block w-full" />
                    <InputError :message="partForm.errors.quantity_per_garment" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showPart = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': partForm.processing }" :disabled="partForm.processing">
                        {{ editingPartId ? 'Save' : 'Add' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
        <ConfirmModal
            :show="!!partDelete"
            title="Remove part?"
            :message="`Remove ${partDelete?.name} from this recipe.`"
            confirmText="Remove"
            :processing="partDeleteForm.processing"
            @confirm="confirmPartDelete"
            @cancel="partDelete = null"
        />
        <ConfirmModal
            :show="!!materialDelete"
            title="Remove material?"
            :message="`Remove ${materialDelete?.name} from this recipe.`"
            confirmText="Remove"
            :processing="materialDeleteForm.processing"
            @confirm="confirmMaterialDelete"
            @cancel="materialDelete = null"
        />
    </AuthenticatedLayout>
</template>
