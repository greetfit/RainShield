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
import Checkbox from '@/Components/Checkbox.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ product: Object, variants: Array, sizeOptions: Array, layerOptions: Array, gradeOptions: Array });
const table = useTableControls(() => props.variants, ['name', 'size', 'layer', 'grade', 'sku', 'stock_quantity']);
const money = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const sizeSearchOptions = computed(() => props.sizeOptions.map((size) => ({ value: size.id, label: size.name })));
const layerSearchOptions = computed(() => [
    { value: '', label: 'No layer' },
    ...props.layerOptions.map((layer) => ({ value: layer.id, label: layer.name })),
]);
const gradeSearchOptions = computed(() => props.gradeOptions.map((grade) => ({ value: grade.id, label: grade.name })));

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({
    name: '',
    product_size_id: '',
    product_layer_id: '',
    product_grade_id: '',
    sku: '',
    selling_price: 0,
    profit_markup_type: 'percent',
    profit_margin_percent: 0,
    profit_markup_amount: 0,
    is_active: true,
});

function resetForm() {
    form.name = '';
    form.product_size_id = '';
    form.product_layer_id = '';
    form.product_grade_id = '';
    form.sku = '';
    form.selling_price = 0;
    form.profit_markup_type = 'percent';
    form.profit_margin_percent = 0;
    form.profit_markup_amount = 0;
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
    form.product_size_id = variant.product_size_id ?? '';
    form.product_layer_id = variant.product_layer_id ?? '';
    form.product_grade_id = variant.product_grade_id ?? '';
    form.sku = variant.sku ?? '';
    form.selling_price = variant.selling_price ?? 0;
    form.profit_markup_type = variant.profit_markup_type ?? 'percent';
    form.profit_margin_percent = variant.profit_margin_percent ?? 0;
    form.profit_markup_amount = variant.profit_markup_amount ?? 0;
    form.is_active = variant.is_active;
    showForm.value = true;
}

function markupLabel(variant) {
    if ((variant.profit_markup_type ?? 'percent') === 'flat') {
        return `Flat ${Number(variant.profit_markup_amount || 0).toFixed(2)}`;
    }

    return `${Number(variant.profit_margin_percent || 0).toFixed(2)}%`;
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
        form.put(route('masters.product-variants.update', editingId.value), opts);
    } else {
        form.post(route('masters.product-variants.store', props.product.id), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
const duplicateForm = useForm({});

function duplicateVariant(variant) {
    duplicateForm.post(route('masters.product-variants.duplicate', variant.id), {
        preserveScroll: true,
    });
}

const openingTarget = ref(null);
const openingForm = useForm({
    quantity: '',
    unit_cost: '',
    alert_quantity: '',
    note: '',
});

function openOpeningStock(variant) {
    openingTarget.value = variant;
    openingForm.clearErrors();
    openingForm.quantity = variant.stock_quantity ?? 0;
    openingForm.unit_cost = variant.stock_average_cost ?? 0;
    openingForm.alert_quantity = variant.stock_alert_quantity ?? 0;
    openingForm.note = '';
}

function submitOpeningStock() {
    openingForm.post(route('masters.product-variants.opening-stock', openingTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (openingTarget.value = null),
    });
}

function confirmDelete() {
    deleteForm.delete(route('masters.product-variants.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head :title="`${product.name} — Variants`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('masters.products.index')" class="text-sm text-indigo-600 hover:underline">Products</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ product.name }} — Variants
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600">
                    A variant is a sellable combination of <strong>size · layer · grade</strong>.
                    Each variant later gets its own recipe (materials + parts).
                </p>
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Variant</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search variants...">
                <div class="table-scroll">
                    <table class="min-w-[1180px] divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Variant</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Layer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Grade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Profit Markup</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Selling Price</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Opening / Stock</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Avg Cost</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="variant in table.rows.value" :key="variant.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ variant.name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ variant.size || '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500 capitalize">{{ variant.layer || '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ variant.grade || '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ variant.sku || '—' }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500">{{ markupLabel(variant) }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500">{{ money(variant.selling_price) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ Number(variant.stock_quantity || 0).toLocaleString() }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-500">{{ money(variant.stock_average_cost) }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="variant.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                        {{ variant.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="recipe" :href="route('recipes.edit', variant.id)">Recipe</ActionMenuItem>
                                        <ActionMenuItem icon="recipe" :href="route('business-settings.cutting-yield-rules.index', { product_variant_id: variant.id })">Yield Rules</ActionMenuItem>
                                        <ActionMenuItem icon="warehouse" @click="openOpeningStock(variant)">Opening Stock</ActionMenuItem>
                                        <ActionMenuItem icon="copy" @click="duplicateVariant(variant)">Duplicate</ActionMenuItem>
                                        <ActionMenuItem icon="edit" @click="openEdit(variant)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = variant">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="11" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No variants yet. e.g. "Medium / Double / A grade".
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
                    <InputLabel for="name" value="Variant label" />
                    <TextInput id="name" v-model="form.name" class="mt-1 block w-full" placeholder="Medium / Double / A" autofocus />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="product_size_id" value="Size" />
                        <SearchableSelect id="product_size_id" v-model="form.product_size_id" :options="sizeSearchOptions" placeholder="Search size..." class="mt-1" />
                        <InputError :message="form.errors.product_size_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="product_layer_id" value="Layer" />
                        <SearchableSelect id="product_layer_id" v-model="form.product_layer_id" :options="layerSearchOptions" placeholder="Search layer..." class="mt-1" />
                        <InputError :message="form.errors.product_layer_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="product_grade_id" value="Grade" />
                        <SearchableSelect id="product_grade_id" v-model="form.product_grade_id" :options="gradeSearchOptions" placeholder="Search grade..." class="mt-1" />
                        <InputError :message="form.errors.product_grade_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="sku" value="SKU (optional)" />
                        <TextInput id="sku" v-model="form.sku" class="mt-1 block w-full" />
                        <InputError :message="form.errors.sku" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="profit_markup_type" value="Profit markup type" />
                        <select id="profit_markup_type" v-model="form.profit_markup_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="percent">Percentage (%)</option>
                            <option value="flat">Flat amount</option>
                        </select>
                        <InputError :message="form.errors.profit_markup_type" class="mt-1" />
                    </div>
                    <div v-if="form.profit_markup_type === 'percent'">
                        <InputLabel for="profit_margin_percent" value="Profit markup %" />
                        <TextInput id="profit_margin_percent" v-model="form.profit_margin_percent" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="form.errors.profit_margin_percent" class="mt-1" />
                    </div>
                    <div v-else>
                        <InputLabel for="profit_markup_amount" value="Profit flat amount" />
                        <TextInput id="profit_markup_amount" v-model="form.profit_markup_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="form.errors.profit_markup_amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="selling_price" value="Manual selling price fallback" />
                        <TextInput id="selling_price" v-model="form.selling_price" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="form.errors.selling_price" class="mt-1" />
                    </div>
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

        <Modal :show="!!openingTarget" @close="openingTarget = null" max-width="md">
            <form @submit.prevent="submitOpeningStock" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Set Opening Stock</h2>
                <p class="mt-1 text-sm text-gray-600">{{ product.name }} - {{ openingTarget?.name }}</p>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="variant_opening_quantity" value="Opening quantity" />
                        <TextInput id="variant_opening_quantity" v-model="openingForm.quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.quantity" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="variant_opening_unit_cost" value="Unit cost" />
                        <TextInput id="variant_opening_unit_cost" v-model="openingForm.unit_cost" type="number" min="0" step="0.0001" class="mt-1 block w-full" />
                        <InputError :message="openingForm.errors.unit_cost" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="variant_opening_alert_quantity" value="Alert quantity" />
                    <TextInput id="variant_opening_alert_quantity" v-model="openingForm.alert_quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.alert_quantity" class="mt-1" />
                </div>

                <div class="mt-4">
                    <InputLabel for="variant_opening_note" value="Note" />
                    <TextInput id="variant_opening_note" v-model="openingForm.note" class="mt-1 block w-full" />
                    <InputError :message="openingForm.errors.note" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="openingTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': openingForm.processing }" :disabled="openingForm.processing">
                        Save Opening Stock
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

