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

const props = defineProps({ rates: Array, stages: Array, staffOptions: Array, variantOptions: Array });
const table = useTableControls(() => props.rates, ['stage', 'staff', 'scope', 'rate']);
const firstStage = computed(() => props.stages[0]?.value ?? '');
const stageLabel = (value) => props.stages.find((stage) => stage.value === value)?.label ?? value;
const stageSearchOptions = computed(() => props.stages.map((stage) => ({ value: stage.value, label: stage.label })));
const staffSearchOptions = computed(() => [
    { value: '', label: 'Default staff' },
    ...props.staffOptions.map((staff) => ({ value: staff.id, label: staff.label })),
]);
const variantSearchOptions = computed(() => [
    { value: '', label: 'Default (all variants)' },
    ...props.variantOptions.map((variant) => ({ value: variant.id, label: variant.label })),
]);

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const showForm = ref(false);
const editingId = ref(null);
const form = useForm({ stage: firstStage.value, staff_id: '', product_variant_id: '', rate: 0 });

function resetForm() {
    form.stage = firstStage.value;
    form.staff_id = '';
    form.product_variant_id = '';
    form.rate = 0;
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}
function openEdit(r) {
    editingId.value = r.id;
    form.clearErrors();
    form.stage = r.stage;
    form.staff_id = r.staff_id ?? '';
    form.product_variant_id = r.product_variant_id ?? '';
    form.rate = r.rate;
    // scope editing keeps the same variant; leave product_variant_id as-is is tricky, so allow stage+rate edit
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
    const payload = {
        ...form,
        staff_id: form.staff_id || null,
        product_variant_id: form.product_variant_id || null,
    };
    if (editingId.value) {
        form.transform(() => payload).put(route('piece-rates.update', editingId.value), opts);
    } else {
        form.transform(() => payload).post(route('piece-rates.store'), opts);
    }
}

const deleteTarget = ref(null);
const deleteForm = useForm({});
function confirmDelete() {
    deleteForm.delete(route('piece-rates.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Piece Rates" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Piece Rates</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-gray-600">
                    Set default, staff-specific, and optional per-variant rates. Job cards and cutting batches prefill from these.
                </p>
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ Add Rate</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search piece rates...">
                <div class="table-scroll">
                    <table class="min-w-[960px] divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Stage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Staff</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Scope</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Rate</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="r in table.rows.value" :key="r.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ stageLabel(r.stage) }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ r.staff }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ r.scope }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-900">{{ money(r.rate) }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <ActionMenu>
                                        <ActionMenuItem icon="edit" @click="openEdit(r)">Edit</ActionMenuItem>
                                        <ActionMenuItem icon="trash" danger @click="deleteTarget = r">Delete</ActionMenuItem>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="rates.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No rates yet. Add at least a default rate for each stage.
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
                <h2 class="text-lg font-medium text-gray-900">{{ editingId ? 'Edit Rate' : 'Add Rate' }}</h2>
                <div class="mt-4">
                    <InputLabel for="stage" value="Stage" />
                    <SearchableSelect id="stage" v-model="form.stage" :options="stageSearchOptions" placeholder="Search stage..." class="mt-1" />
                    <InputError :message="form.errors.stage" class="mt-1" />
                </div>
                <div class="mt-4" v-if="!editingId">
                    <InputLabel for="staff" value="Staff" />
                    <SearchableSelect id="staff" v-model="form.staff_id" :options="staffSearchOptions" placeholder="Search staff..." class="mt-1" />
                    <InputError :message="form.errors.staff_id" class="mt-1" />
                </div>
                <div class="mt-4" v-if="!editingId">
                    <InputLabel for="variant" value="Scope" />
                    <SearchableSelect id="variant" v-model="form.product_variant_id" :options="variantSearchOptions" placeholder="Search variant..." class="mt-1" />
                    <InputError :message="form.errors.product_variant_id" class="mt-1" />
                </div>
                <div class="mt-4">
                    <InputLabel for="rate" value="Rate per piece" />
                    <TextInput id="rate" type="number" min="0" step="0.01" v-model="form.rate" class="mt-1 block w-full" />
                    <InputError :message="form.errors.rate" class="mt-1" />
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
            title="Delete rate?"
            message="This removes the piece rate."
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>

