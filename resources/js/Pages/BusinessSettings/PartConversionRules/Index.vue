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
import { computed, ref } from 'vue';

const props = defineProps({ rules: Array, variantOptions: Array, partOptions: Array });
const table = useTableControls(() => props.rules, ['from_label', 'to_label', 'output_per_input']);
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));
const partSearchOptions = computed(() => props.partOptions.map((part) => ({
    value: part.id,
    label: part.name,
})));

const showForm = ref(false);
const editing = ref(null);
const deleteTarget = ref(null);
const form = useForm({
    from_product_variant_id: '',
    from_part_id: '',
    to_product_variant_id: '',
    to_part_id: '',
    output_per_input: 1,
    is_active: true,
});
const deleteForm = useForm({});

const exampleText = computed(() => {
    const ratio = Number(form.output_per_input || 0);
    if (ratio <= 0) return 'Enter an output ratio.';

    const output = Number.isInteger(ratio) ? ratio : ratio.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
    return `1 recoverable source piece can become ${output} target piece(s).`;
});

function resetForm() {
    form.from_product_variant_id = '';
    form.from_part_id = '';
    form.to_product_variant_id = '';
    form.to_part_id = '';
    form.output_per_input = 1;
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
    form.from_product_variant_id = rule.from_product_variant_id;
    form.from_part_id = rule.from_part_id;
    form.to_product_variant_id = rule.to_product_variant_id;
    form.to_part_id = rule.to_part_id;
    form.output_per_input = rule.output_per_input;
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
        form.put(route('business-settings.part-conversion-rules.update', editing.value.id), options);
        return;
    }

    form.post(route('business-settings.part-conversion-rules.store'), options);
}

function confirmDelete() {
    deleteForm.delete(route('business-settings.part-conversion-rules.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Part Conversion Rules" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Part Conversion Rules</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-100">
                    These rules suggest recovery output when a larger miscut/recoverable part is later cut into a smaller usable part. Users can still override the actual output.
                </div>

                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Rule</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search conversion rules...">
                    <div class="table-scroll">
                        <table class="min-w-[900px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">From Recoverable</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">To Good Part</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Output / 1 Input</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="rule in table.rows.value" :key="rule.id">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ rule.from_label }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ rule.to_label }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ Number(rule.output_per_input).toLocaleString(undefined, { maximumFractionDigits: 3 }) }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                            :class="rule.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                                        >
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
                                    <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No conversion rules yet. Add a rule for recoverable large parts that can become smaller parts.
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
                <h2 class="text-lg font-medium text-gray-900">{{ editing ? 'Edit Conversion Rule' : 'Add Conversion Rule' }}</h2>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="from_product_variant_id" value="From product variant" />
                        <SearchableSelect id="from_product_variant_id" v-model="form.from_product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                        <InputError :message="form.errors.from_product_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="from_part_id" value="From part" />
                        <SearchableSelect id="from_part_id" v-model="form.from_part_id" :options="partSearchOptions" placeholder="Search part..." class="mt-1" />
                        <InputError :message="form.errors.from_part_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="to_product_variant_id" value="To product variant" />
                        <SearchableSelect id="to_product_variant_id" v-model="form.to_product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                        <InputError :message="form.errors.to_product_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="to_part_id" value="To part" />
                        <SearchableSelect id="to_part_id" v-model="form.to_part_id" :options="partSearchOptions" placeholder="Search part..." class="mt-1" />
                        <InputError :message="form.errors.to_part_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="output_per_input" value="Output per 1 recoverable piece" />
                        <TextInput
                            id="output_per_input"
                            v-model="form.output_per_input"
                            type="number"
                            min="0.001"
                            step="0.001"
                            required
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ exampleText }}</p>
                        <InputError :message="form.errors.output_per_input" class="mt-1" />
                    </div>
                    <label class="mt-7 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
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
            title="Delete conversion rule?"
            message="Recovery cutting will stop using this rule for automatic suggestions."
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

