<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import TableControls from '@/Components/TableControls.vue';
import DatePicker from '@/Components/DatePicker.vue';
import AppIcon from '@/Components/AppIcon.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    batches: Array,
    recoveries: Array,
    rawMaterialOptions: Array,
    variantOptions: Array,
    partOptions: Array,
    staffOptions: Array,
    recoverableOptions: Array,
    conversionRules: Array,
    cuttingYieldRules: Array,
    pieceRateOptions: Array,
    today: String,
});

const table = useTableControls(() => props.batches, ['code', 'material', 'staff', 'cut_on']);
const recoveryTable = useTableControls(() => props.recoveries, ['code', 'from', 'to', 'staff', 'cut_on']);
const showCuttingForm = ref(false);
const showRecoveryForm = ref(false);
const editingBatch = ref(null);
const editingRecovery = ref(null);
const viewingBatch = ref(null);
const deleteBatchTarget = ref(null);
const deleteRecoveryTarget = ref(null);
const cuttingSubmitted = ref(false);
const number = (n) => Number(n || 0).toLocaleString();
const decimal = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const deleteBatchForm = useForm({});
const deleteRecoveryForm = useForm({});
const pad = (value) => String(value).padStart(2, '0');
const currentDateTimeValue = () => {
    const now = new Date();
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
};
const dateFromDateTime = (value) => (value ? String(value).replace(' ', 'T').split('T')[0] : props.today);

const cuttingForm = useForm({
    raw_material_variant_id: '',
    material_quantity: '',
    paid_labor: false,
    staff_id: '',
    cut_on: props.today,
    started_at: '',
    completed_at: '',
    piece_rate: '',
    wage_paid_amount: '',
    notes: '',
    outputs: [blankOutput()],
});

const recoveryForm = useForm({
    from_product_variant_id: '',
    from_part_id: '',
    input_quantity: '',
    to_product_variant_id: '',
    to_part_id: '',
    expected_quantity: '',
    good_quantity: '',
    scrap_quantity: '',
    paid_labor: false,
    staff_id: '',
    cut_on: props.today,
    started_at: '',
    completed_at: '',
    piece_rate: '',
    wage_paid_amount: '',
    notes: '',
});

const selectedRecoverable = computed(() =>
    props.recoverableOptions.find((item) =>
        Number(item.product_variant_id) === Number(recoveryForm.from_product_variant_id)
        && Number(item.part_id) === Number(recoveryForm.from_part_id),
    ),
);
const rawMaterialSearchOptions = computed(() => props.rawMaterialOptions.map((material) => ({
    value: material.id,
    label: `${material.label} (${material.available_quantity} available)`,
    description: material.unit ? `Unit: ${material.unit}` : '',
})));
const variantSearchOptions = computed(() => props.variantOptions.map((variant) => ({
    value: variant.id,
    label: variant.label,
})));
const partSearchOptions = computed(() => props.partOptions.map((part) => ({
    value: part.id,
    label: part.name,
})));
const staffSearchOptions = computed(() => props.staffOptions.map((staff) => ({
    value: staff.id,
    label: staff.name,
})));
const recoverableSearchOptions = computed(() => props.recoverableOptions.map((item) => ({
    value: recoverableKey(item),
    label: `${item.label} (${item.quantity} available)`,
})));
const partOptionsForOutput = (output, index) => availablePartsForOutput(output, index).map((part) => ({
    value: part.id,
    label: part.name,
}));
const selectedRawMaterial = computed(() =>
    props.rawMaterialOptions.find((material) => Number(material.id) === Number(cuttingForm.raw_material_variant_id)),
);
const cuttingOutputCapacity = (output) => {
    const materialQty = Number(cuttingForm.material_quantity || 0);
    const yieldPerUnit = Number(output.yield_per_material_unit || 0);
    return materialQty > 0 && yieldPerUnit > 0 ? Math.floor(materialQty * yieldPerUnit) : null;
};
const outputActualTotal = (output) =>
    Number(output.good_quantity || 0) + Number(output.recoverable_quantity || 0) + Number(output.scrap_quantity || 0);
const outputMaterialUsage = (output) => {
    const yieldPerUnit = Number(output.yield_per_material_unit || 0);
    return yieldPerUnit > 0 ? outputActualTotal(output) / yieldPerUnit : 0;
};
const totalOutputMaterialUsage = computed(() =>
    cuttingForm.outputs.reduce((total, output) => total + outputMaterialUsage(output), 0),
);
const outputKey = (output) => `${output.product_variant_id || ''}:${output.part_id || ''}`;
const duplicateOutputIndexes = computed(() => {
    const seen = new Map();
    const duplicates = new Set();

    cuttingForm.outputs.forEach((output, index) => {
        if (!output.product_variant_id || !output.part_id) return;

        const key = outputKey(output);
        if (seen.has(key)) {
            duplicates.add(index);
            duplicates.add(seen.get(key));
            return;
        }

        seen.set(key, index);
    });

    return duplicates;
});
const matchingYieldRule = (output) =>
    props.cuttingYieldRules.find((rule) =>
        Number(rule.raw_material_variant_id) === Number(cuttingForm.raw_material_variant_id)
        && Number(rule.product_variant_id) === Number(output.product_variant_id)
        && Number(rule.part_id) === Number(output.part_id),
    );
const firstOutputVariantId = computed(() => cuttingForm.outputs.find((output) => output.product_variant_id)?.product_variant_id || '');
const invalidClass = 'border-red-500 bg-red-50/80 text-red-900 focus:border-red-500 focus:ring-red-500 dark:border-red-500 dark:bg-red-950/30 dark:text-red-100';
const selectClass = (invalid = false) => [
    'mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100',
    invalid ? invalidClass : '',
];
const inlineSelectClass = (invalid = false) => [
    'block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100',
    invalid ? invalidClass : '',
];
const inputClass = (invalid = false, extra = 'mt-1 block w-full') => [extra, invalid ? invalidClass : ''];

function blankOutput() {
    return {
        product_variant_id: '',
        part_id: '',
        yield_per_material_unit: '',
        good_quantity: '',
        recoverable_quantity: '',
        scrap_quantity: '',
    };
}

function openCutting() {
    editingBatch.value = null;
    cuttingSubmitted.value = false;
    cuttingForm.clearErrors();
    cuttingForm.raw_material_variant_id = '';
    cuttingForm.material_quantity = '';
    cuttingForm.paid_labor = false;
    cuttingForm.staff_id = '';
    cuttingForm.cut_on = props.today;
    cuttingForm.started_at = currentDateTimeValue();
    cuttingForm.completed_at = '';
    cuttingForm.piece_rate = '';
    cuttingForm.wage_paid_amount = '';
    cuttingForm.notes = '';
    cuttingForm.outputs = [blankOutput()];
    showCuttingForm.value = true;
}

function openEditCutting(batch) {
    editingBatch.value = batch;
    cuttingSubmitted.value = false;
    cuttingForm.clearErrors();
    cuttingForm.raw_material_variant_id = batch.raw_material_variant_id;
    cuttingForm.material_quantity = batch.material_quantity;
    cuttingForm.paid_labor = !!batch.staff_id || Number(batch.piece_rate || 0) > 0 || Number(batch.wage_paid_amount || 0) > 0;
    cuttingForm.staff_id = batch.staff_id ?? '';
    cuttingForm.cut_on = batch.cut_on_input ?? props.today;
    cuttingForm.started_at = batch.started_at ?? '';
    cuttingForm.completed_at = batch.completed_at ?? '';
    cuttingForm.piece_rate = batch.piece_rate ?? '';
    cuttingForm.wage_paid_amount = batch.wage_paid_amount ?? '';
    cuttingForm.notes = batch.notes ?? '';
    cuttingForm.outputs = batch.outputs.map((output) => ({
        product_variant_id: output.product_variant_id,
        part_id: output.part_id,
        yield_per_material_unit: output.yield_per_material_unit ?? '',
        good_quantity: output.good_quantity,
        recoverable_quantity: output.recoverable_quantity,
        scrap_quantity: output.scrap_quantity,
    }));
    showCuttingForm.value = true;
}

function addOutput() {
    cuttingForm.outputs.push(blankOutput());
}

function removeOutput(index) {
    if (cuttingForm.outputs.length === 1) return;
    cuttingForm.outputs.splice(index, 1);
}

function availablePartsForOutput(output, index) {
    if (!output.product_variant_id) {
        return props.partOptions;
    }

    const usedPartIds = new Set(
        cuttingForm.outputs
            .filter((row, rowIndex) => rowIndex !== index)
            .filter((row) => Number(row.product_variant_id) === Number(output.product_variant_id))
            .filter((row) => row.part_id)
            .map((row) => Number(row.part_id)),
    );

    return props.partOptions.filter((part) => !usedPartIds.has(Number(part.id)));
}

function submitCutting() {
    cuttingSubmitted.value = true;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            cuttingSubmitted.value = false;
            showCuttingForm.value = false;
        },
    };

    if (!cuttingForm.paid_labor) {
        cuttingForm.staff_id = '';
        cuttingForm.piece_rate = '';
        cuttingForm.wage_paid_amount = '';
    }
    cuttingForm.cut_on = dateFromDateTime(cuttingForm.started_at);

    const missingMessage = validateCuttingForm();
    if (missingMessage) {
        notifyError(missingMessage);
        return;
    }

    if (editingBatch.value) {
        cuttingForm.put(route('cutting-batches.update', editingBatch.value.id), options);
        return;
    }

    cuttingForm.post(route('cutting-batches.store'), options);
}

function cuttingFieldInvalid(field) {
    if (cuttingForm.errors[field]) return true;
    if (!cuttingSubmitted.value) return false;

    if (field === 'raw_material_variant_id') return !cuttingForm.raw_material_variant_id;
    if (field === 'material_quantity') {
        return !Number(cuttingForm.material_quantity || 0)
            || (selectedRawMaterial.value && Number(cuttingForm.material_quantity) > Number(selectedRawMaterial.value.available_quantity));
    }
    if (field === 'staff_id') return cuttingForm.paid_labor && !cuttingForm.staff_id;
    if (field === 'started_at') return !cuttingForm.started_at;

    return false;
}

function outputFieldInvalid(index, output, field) {
    if (cuttingForm.errors[`outputs.${index}.${field}`]) return true;
    if (!cuttingSubmitted.value) return false;

    if (field === 'product_variant_id') return !output.product_variant_id;
    if (field === 'part_id') return !output.part_id || duplicateOutputIndexes.value.has(index);
    if (field === 'yield_per_material_unit') return !Number(output.yield_per_material_unit || 0);
    if (['good_quantity', 'recoverable_quantity', 'scrap_quantity'].includes(field)) {
        const capacity = cuttingOutputCapacity(output);
        return outputActualTotal(output) <= 0
            || (capacity !== null && outputActualTotal(output) > capacity)
            || totalOutputMaterialUsage.value > Number(cuttingForm.material_quantity || 0) + 0.000001;
    }

    return false;
}

function validateCuttingForm() {
    if (!cuttingForm.raw_material_variant_id) return 'Select a raw material / roll.';
    if (!Number(cuttingForm.material_quantity || 0)) return 'Enter material quantity used.';
    if (selectedRawMaterial.value && Number(cuttingForm.material_quantity) > Number(selectedRawMaterial.value.available_quantity)) {
        return `Only ${selectedRawMaterial.value.available_quantity} is available for this material.`;
    }
    if (!cuttingForm.started_at) return 'Select the cutting start date and time.';
    if (cuttingForm.paid_labor && !cuttingForm.staff_id) return 'Select cutting staff.';

    for (const [index, output] of cuttingForm.outputs.entries()) {
        const row = index + 1;
        if (!output.product_variant_id) return `Select product variant on output row ${row}.`;
        if (!output.part_id) return `Select part on output row ${row}.`;
        if (duplicateOutputIndexes.value.has(index)) return `Output row ${row} duplicates another product variant and part.`;
        if (!Number(output.yield_per_material_unit || 0)) return `Enter yield per material unit on output row ${row}.`;
        if (outputActualTotal(output) <= 0) return `Enter good, recoverable, or scrap quantity on output row ${row}.`;

        const capacity = cuttingOutputCapacity(output);
        if (capacity !== null && outputActualTotal(output) > capacity) {
            return `Output row ${row} can cut only ${capacity} pieces from the material quantity used.`;
        }
    }

    if (totalOutputMaterialUsage.value > Number(cuttingForm.material_quantity || 0) + 0.000001) {
        return `Output rows consume ${totalOutputMaterialUsage.value.toFixed(3)} material units, but only ${cuttingForm.material_quantity} was entered.`;
    }

    return '';
}

function notifyError(message) {
    window.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'error', message } }));
}

function openRecovery() {
    editingRecovery.value = null;
    recoveryForm.clearErrors();
    recoveryForm.from_product_variant_id = '';
    recoveryForm.from_part_id = '';
    recoveryForm.input_quantity = '';
    recoveryForm.to_product_variant_id = '';
    recoveryForm.to_part_id = '';
    recoveryForm.expected_quantity = '';
    recoveryForm.good_quantity = '';
    recoveryForm.scrap_quantity = '';
    recoveryForm.paid_labor = false;
    recoveryForm.staff_id = '';
    recoveryForm.cut_on = props.today;
    recoveryForm.started_at = currentDateTimeValue();
    recoveryForm.completed_at = '';
    recoveryForm.piece_rate = '';
    recoveryForm.wage_paid_amount = '';
    recoveryForm.notes = '';
    showRecoveryForm.value = true;
}

function openEditRecovery(recovery) {
    editingRecovery.value = recovery;
    recoveryForm.clearErrors();
    recoveryForm.from_product_variant_id = recovery.from_product_variant_id;
    recoveryForm.from_part_id = recovery.from_part_id;
    recoveryForm.input_quantity = recovery.input_quantity;
    recoveryForm.to_product_variant_id = recovery.to_product_variant_id;
    recoveryForm.to_part_id = recovery.to_part_id;
    recoveryForm.expected_quantity = recovery.expected_quantity;
    recoveryForm.good_quantity = recovery.good_quantity;
    recoveryForm.scrap_quantity = recovery.scrap_quantity;
    recoveryForm.paid_labor = !!recovery.staff_id || Number(recovery.piece_rate || 0) > 0 || Number(recovery.wage_paid_amount || 0) > 0;
    recoveryForm.staff_id = recovery.staff_id ?? '';
    recoveryForm.cut_on = recovery.cut_on_input ?? props.today;
    recoveryForm.started_at = recovery.started_at ?? '';
    recoveryForm.completed_at = recovery.completed_at ?? '';
    recoveryForm.piece_rate = recovery.piece_rate ?? '';
    recoveryForm.wage_paid_amount = recovery.wage_paid_amount ?? '';
    recoveryForm.notes = recovery.notes ?? '';
    showRecoveryForm.value = true;
}

function setRecoverableSource(value) {
    const [productVariantId, partId] = value.split(':');
    recoveryForm.from_product_variant_id = productVariantId;
    recoveryForm.from_part_id = partId;
    syncRecoveryExpected();
}

function recoverableKey(item) {
    return `${item.product_variant_id}:${item.part_id}`;
}

function syncRecoveryExpected() {
    const rule = props.conversionRules.find((item) =>
        Number(item.from_product_variant_id) === Number(recoveryForm.from_product_variant_id)
        && Number(item.from_part_id) === Number(recoveryForm.from_part_id)
        && Number(item.to_product_variant_id) === Number(recoveryForm.to_product_variant_id)
        && Number(item.to_part_id) === Number(recoveryForm.to_part_id),
    );
    const ratio = Number(rule?.output_per_input || 1);
    const expected = Math.floor(Number(recoveryForm.input_quantity || 0) * ratio);
    recoveryForm.expected_quantity = expected || '';
    if (!recoveryForm.good_quantity) {
        recoveryForm.good_quantity = expected || '';
    }
    syncRecoveryPieceRate();
}

function syncCuttingYield(output) {
    const rule = matchingYieldRule(output);
    if (rule && !Number(output.yield_per_material_unit || 0)) {
        output.yield_per_material_unit = rule.yield_per_material_unit;
    }

    syncCuttingPieceRate();
}

function syncCuttingVariant(output, index) {
    if (output.part_id && !availablePartsForOutput(output, index).some((part) => Number(part.id) === Number(output.part_id))) {
        output.part_id = '';
        output.yield_per_material_unit = '';
    }

    syncCuttingYield(output);
}

function resolvePieceRate(staffId, variantId) {
    const rates = props.pieceRateOptions || [];
    const staff = Number(staffId || 0);
    const variant = Number(variantId || 0);
    const match = (staffMatcher, variantMatcher) =>
        rates.find((rate) => staffMatcher(rate) && variantMatcher(rate));

    const staffVariant = match(
        (rate) => Number(rate.staff_id || 0) === staff && staff > 0,
        (rate) => Number(rate.product_variant_id || 0) === variant && variant > 0,
    );
    if (staffVariant) return staffVariant.rate;

    const staffDefault = match(
        (rate) => Number(rate.staff_id || 0) === staff && staff > 0,
        (rate) => !rate.product_variant_id,
    );
    if (staffDefault) return staffDefault.rate;

    const variantDefault = match(
        (rate) => !rate.staff_id,
        (rate) => Number(rate.product_variant_id || 0) === variant && variant > 0,
    );
    if (variantDefault) return variantDefault.rate;

    const globalDefault = match((rate) => !rate.staff_id, (rate) => !rate.product_variant_id);

    return globalDefault?.rate ?? '';
}

function syncCuttingPieceRate() {
    if (!cuttingForm.paid_labor) return;

    const rate = resolvePieceRate(cuttingForm.staff_id, firstOutputVariantId.value);
    if (rate !== '') {
        cuttingForm.piece_rate = rate;
    }
}

function syncRecoveryPieceRate() {
    if (!recoveryForm.paid_labor) return;

    const rate = resolvePieceRate(recoveryForm.staff_id, recoveryForm.to_product_variant_id);
    if (rate !== '') {
        recoveryForm.piece_rate = rate;
    }
}

function submitRecovery() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showRecoveryForm.value = false),
    };

    if (!recoveryForm.paid_labor) {
        recoveryForm.staff_id = '';
        recoveryForm.piece_rate = '';
        recoveryForm.wage_paid_amount = '';
    }
    recoveryForm.cut_on = dateFromDateTime(recoveryForm.started_at);

    if (editingRecovery.value) {
        recoveryForm.put(route('recovery-cuttings.update', editingRecovery.value.id), options);
        return;
    }

    recoveryForm.post(route('recovery-cuttings.store'), options);
}

watch(
    () => [cuttingForm.paid_labor, cuttingForm.staff_id, firstOutputVariantId.value],
    syncCuttingPieceRate,
);

watch(
    () => [recoveryForm.paid_labor, recoveryForm.staff_id, recoveryForm.to_product_variant_id],
    syncRecoveryPieceRate,
);

function confirmDeleteBatch() {
    deleteBatchForm.delete(route('cutting-batches.destroy', deleteBatchTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteBatchTarget.value = null),
    });
}

function confirmDeleteRecovery() {
    deleteRecoveryForm.delete(route('recovery-cuttings.destroy', deleteRecoveryTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteRecoveryTarget.value = null),
    });
}
</script>

<template>
    <Head title="Cutting Batches" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Cutting Batches</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap justify-end gap-3">
                    <SecondaryButton type="button" @click="openRecovery">+ Recovery Cutting</SecondaryButton>
                    <PrimaryButton type="button" @click="openCutting">+ New Cutting Batch</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search cutting batches...">
                    <div class="table-scroll">
                            <table class="min-w-[1120px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Batch</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Material</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Used</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Staff</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Good</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Recoverable</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Scrap</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Wage</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Paid</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr
                                    v-for="batch in table.rows.value"
                                    :key="batch.id"
                                    class="cursor-pointer transition hover:bg-gray-50 dark:hover:bg-gray-800/60"
                                    @click="viewingBatch = batch"
                                >
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                        {{ batch.code }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ batch.material }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ batch.material_quantity }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ batch.cut_on || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-600">{{ batch.staff || '-' }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-emerald-700">{{ number(batch.good_total) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-amber-700">{{ number(batch.recoverable_total) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-red-700">{{ number(batch.scrap_total) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-900">{{ number(batch.wage_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-600">{{ number(batch.wage_paid_amount) }}</td>
                                    <td class="px-6 py-4 text-right text-sm" @click.stop>
                                        <ActionMenu>
                                            <ActionMenuItem icon="eye" @click="viewingBatch = batch">View</ActionMenuItem>
                                            <ActionMenuItem icon="edit" @click="openEditCutting(batch)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteBatchTarget = batch">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="11" class="px-6 py-8 text-center text-sm text-gray-500">
                                        No cutting batches yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>

                <div class="mt-8">
                    <h3 class="mb-3 text-base font-semibold text-gray-900">Recovery Cutting</h3>
                    <TableControls :table="recoveryTable" placeholder="Search recovery cutting...">
                        <div class="table-scroll">
                            <table class="min-w-[1040px] divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">From Recoverable</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">To Good Part</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Input</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Good</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Scrap</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Wage</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Paid</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr v-for="recovery in recoveryTable.rows.value" :key="recovery.id">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ recovery.code }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ recovery.from }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-600">{{ recovery.to }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ recovery.input_quantity }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-emerald-700">{{ recovery.good_quantity }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-red-700">{{ recovery.scrap_quantity }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-gray-900">{{ number(recovery.wage_amount) }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-gray-600">{{ number(recovery.wage_paid_amount) }}</td>
                                        <td class="px-6 py-4 text-right text-sm">
                                            <ActionMenu>
                                                <ActionMenuItem icon="edit" @click="openEditRecovery(recovery)">Edit</ActionMenuItem>
                                                <ActionMenuItem icon="trash" danger @click="deleteRecoveryTarget = recovery">Delete</ActionMenuItem>
                                            </ActionMenu>
                                        </td>
                                    </tr>
                                    <tr v-if="recoveryTable.rows.value.length === 0">
                                        <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No recovery cutting yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </TableControls>
                </div>
            </div>
        </div>

        <Modal :show="!!viewingBatch" @close="viewingBatch = null" max-width="wide">
            <div v-if="viewingBatch" class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cutting Batch</div>
                        <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ viewingBatch.code }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ viewingBatch.material }}</p>
                    </div>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="viewingBatch = null">
                        <AppIcon name="x" />
                    </button>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase text-gray-500">Material used</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ viewingBatch.material_quantity }}</div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase text-gray-500">Cut date</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ viewingBatch.cut_on || '-' }}</div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase text-gray-500">Staff</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ viewingBatch.staff || 'No paid staff' }}</div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-xs uppercase text-gray-500">Wage / Paid</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ decimal(viewingBatch.wage_amount) }} / {{ decimal(viewingBatch.wage_paid_amount) }}</div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-md bg-emerald-50 p-4 dark:bg-emerald-950/30">
                        <div class="text-xs uppercase text-emerald-700 dark:text-emerald-300">Good parts</div>
                        <div class="mt-1 text-xl font-semibold text-emerald-800 dark:text-emerald-200">{{ number(viewingBatch.good_total) }}</div>
                    </div>
                    <div class="rounded-md bg-amber-50 p-4 dark:bg-amber-950/30">
                        <div class="text-xs uppercase text-amber-700 dark:text-amber-300">Recoverable</div>
                        <div class="mt-1 text-xl font-semibold text-amber-800 dark:text-amber-200">{{ number(viewingBatch.recoverable_total) }}</div>
                    </div>
                    <div class="rounded-md bg-red-50 p-4 dark:bg-red-950/30">
                        <div class="text-xs uppercase text-red-700 dark:text-red-300">Scrap</div>
                        <div class="mt-1 text-xl font-semibold text-red-800 dark:text-red-200">{{ number(viewingBatch.scrap_total) }}</div>
                    </div>
                </div>

                <section class="mt-6 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Output breakdown</h3>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-[760px] divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Product / Part</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Yield / Unit</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Good</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Recoverable</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Scrap</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="output in viewingBatch.outputs" :key="output.label">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ output.label }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ output.yield_per_material_unit || '-' }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ number(output.good_quantity) }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-amber-700 dark:text-amber-300">{{ number(output.recoverable_quantity) }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-red-700 dark:text-red-300">{{ number(output.scrap_quantity) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="viewingBatch.notes" class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-xs font-semibold uppercase text-gray-500">Notes</div>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ viewingBatch.notes }}</p>
                </section>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="viewingBatch = null">Close</SecondaryButton>
                    <PrimaryButton type="button" @click="openEditCutting(viewingBatch); viewingBatch = null">Edit Batch</PrimaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="showCuttingForm" @close="showCuttingForm = false" max-width="xwide">
            <form novalidate @submit.prevent="submitCutting" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingBatch ? 'Edit Cutting Batch' : 'New Cutting Batch' }}</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="raw_material_variant_id" value="Raw material / roll" />
                        <SearchableSelect
                            id="raw_material_variant_id"
                            v-model="cuttingForm.raw_material_variant_id"
                            :options="rawMaterialSearchOptions"
                            placeholder="Search material..."
                            :invalid="cuttingFieldInvalid('raw_material_variant_id')"
                            class="mt-1"
                        />
                        <p v-if="selectedRawMaterial" class="mt-1 text-xs" :class="Number(cuttingForm.material_quantity || 0) > Number(selectedRawMaterial.available_quantity) ? 'text-red-600' : 'text-gray-500'">
                            Available stock: {{ selectedRawMaterial.available_quantity }}
                        </p>
                        <InputError :message="cuttingForm.errors.raw_material_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="material_quantity" value="Material quantity used" />
                        <TextInput id="material_quantity" v-model="cuttingForm.material_quantity" type="number" min="0.001" step="0.001" :class="inputClass(cuttingFieldInvalid('material_quantity'))" />
                        <InputError :message="cuttingForm.errors.material_quantity" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        <input v-model="cuttingForm.paid_labor" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="block font-semibold">Paid cutting labor</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Turn off for owner/family work with no piece rate or wage payment.</span>
                        </span>
                    </label>
                    <div v-if="cuttingForm.paid_labor">
                        <InputLabel for="staff_id" value="Cutting staff" />
                        <SearchableSelect
                            id="staff_id"
                            v-model="cuttingForm.staff_id"
                            :options="staffSearchOptions"
                            placeholder="Search cutting staff..."
                            :invalid="cuttingFieldInvalid('staff_id')"
                            class="mt-1"
                        />
                        <InputError :message="cuttingForm.errors.staff_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Started at" />
                        <DatePicker v-model="cuttingForm.started_at" mode="datetime" :invalid="cuttingFieldInvalid('started_at')" class="mt-1" />
                        <InputError :message="cuttingForm.errors.started_at" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Finished at" />
                        <DatePicker v-model="cuttingForm.completed_at" mode="datetime" />
                        <InputError :message="cuttingForm.errors.completed_at" class="mt-1" />
                    </div>
                    <div v-if="cuttingForm.paid_labor">
                        <InputLabel for="piece_rate" value="Piece rate" />
                        <TextInput id="piece_rate" v-model="cuttingForm.piece_rate" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-filled from Piece Rates when staff and product variant are selected. You can override it.</p>
                        <InputError :message="cuttingForm.errors.piece_rate" class="mt-1" />
                    </div>
                    <div v-if="cuttingForm.paid_labor">
                        <InputLabel for="wage_paid_amount" value="Wage paid" />
                        <TextInput id="wage_paid_amount" v-model="cuttingForm.wage_paid_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="cuttingForm.errors.wage_paid_amount" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 rounded-md border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-900">Outputs from this material</h3>
                        <div class="text-xs" :class="totalOutputMaterialUsage > Number(cuttingForm.material_quantity || 0) + 0.000001 ? 'text-red-600' : 'text-gray-500 dark:text-gray-400'">
                            Uses {{ totalOutputMaterialUsage.toFixed(3) }} / {{ Number(cuttingForm.material_quantity || 0).toFixed(3) }} material units
                        </div>
                        <SecondaryButton type="button" @click="addOutput">+ Add Row</SecondaryButton>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-[1080px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Product Variant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Part</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Yield / Unit</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Good</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Recoverable</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Scrap</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(output, index) in cuttingForm.outputs" :key="index">
                                    <td class="px-4 py-3">
                                        <SearchableSelect
                                            v-model="output.product_variant_id"
                                            :options="variantSearchOptions"
                                            placeholder="Search variant..."
                                            :invalid="outputFieldInvalid(index, output, 'product_variant_id')"
                                            @update:model-value="syncCuttingVariant(output, index)"
                                        />
                                        <InputError :message="cuttingForm.errors[`outputs.${index}.product_variant_id`]" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <SearchableSelect
                                            v-model="output.part_id"
                                            :options="partOptionsForOutput(output, index)"
                                            placeholder="Search part..."
                                            :invalid="outputFieldInvalid(index, output, 'part_id')"
                                            @update:model-value="syncCuttingYield(output)"
                                        />
                                        <p v-if="duplicateOutputIndexes.has(index)" class="mt-1 text-xs text-red-600">This product/part is already added.</p>
                                        <InputError :message="cuttingForm.errors[`outputs.${index}.part_id`]" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="cuttingOutputCapacity(output) !== null" class="mt-1 text-right text-[11px]" :class="outputActualTotal(output) > cuttingOutputCapacity(output) ? 'text-red-600' : 'text-gray-500'">
                                            Max {{ cuttingOutputCapacity(output) }}
                                        </div>
                                        <div v-else class="mt-1 text-right text-[11px] text-red-600">
                                            No yield rule
                                        </div>
                                        <InputError :message="cuttingForm.errors[`outputs.${index}.yield_per_material_unit`]" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <TextInput v-model="output.good_quantity" type="number" min="0" step="1" :class="inputClass(outputFieldInvalid(index, output, 'good_quantity'), 'block w-32 text-right')" />
                                        <InputError :message="cuttingForm.errors[`outputs.${index}.good_quantity`]" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3"><TextInput v-model="output.recoverable_quantity" type="number" min="0" step="1" :class="inputClass(outputFieldInvalid(index, output, 'recoverable_quantity'), 'block w-32 text-right')" /></td>
                                    <td class="px-4 py-3"><TextInput v-model="output.scrap_quantity" type="number" min="0" step="1" :class="inputClass(outputFieldInvalid(index, output, 'scrap_quantity'), 'block w-32 text-right')" /></td>
                                    <td class="px-4 py-3 text-right">
                                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30" title="Remove row" @click="removeOutput(index)">
                                            <AppIcon name="trash" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="notes" value="Notes" />
                    <textarea id="notes" v-model="cuttingForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                    <InputError :message="cuttingForm.errors.notes" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showCuttingForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': cuttingForm.processing }" :disabled="cuttingForm.processing">
                        {{ editingBatch ? 'Save Changes' : 'Save Cutting Batch' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showRecoveryForm" @close="showRecoveryForm = false" max-width="wide">
            <form @submit.prevent="submitRecovery" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">{{ editingRecovery ? 'Edit Recovery Cutting' : 'Recovery Cutting' }}</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="From recoverable stock" />
                        <SearchableSelect
                            :model-value="recoverableKey(selectedRecoverable || {})"
                            :options="recoverableSearchOptions"
                            placeholder="Search recoverable part..."
                            class="mt-1"
                            @update:model-value="setRecoverableSource"
                        />
                        <p v-if="selectedRecoverable" class="mt-1 text-xs text-gray-500">{{ selectedRecoverable.quantity }} recoverable pieces available.</p>
                        <InputError :message="recoveryForm.errors.from_product_variant_id || recoveryForm.errors.from_part_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="input_quantity" value="Recoverable quantity used" />
                        <TextInput id="input_quantity" v-model="recoveryForm.input_quantity" type="number" min="1" step="1" required class="mt-1 block w-full" @input="syncRecoveryExpected" />
                        <InputError :message="recoveryForm.errors.input_quantity" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="to_product_variant_id" value="To product variant" />
                        <SearchableSelect
                            id="to_product_variant_id"
                            v-model="recoveryForm.to_product_variant_id"
                            :options="variantSearchOptions"
                            placeholder="Search variant..."
                            class="mt-1"
                            @update:model-value="syncRecoveryExpected"
                        />
                        <InputError :message="recoveryForm.errors.to_product_variant_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="to_part_id" value="To part" />
                        <SearchableSelect
                            id="to_part_id"
                            v-model="recoveryForm.to_part_id"
                            :options="partSearchOptions"
                            placeholder="Search part..."
                            class="mt-1"
                            @update:model-value="syncRecoveryExpected"
                        />
                        <InputError :message="recoveryForm.errors.to_part_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="expected_quantity" value="Expected output" />
                        <TextInput id="expected_quantity" v-model="recoveryForm.expected_quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                        <InputError :message="recoveryForm.errors.expected_quantity" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="good_quantity" value="Actual good output" />
                        <TextInput id="good_quantity" v-model="recoveryForm.good_quantity" type="number" min="0" step="1" required class="mt-1 block w-full" />
                        <InputError :message="recoveryForm.errors.good_quantity" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="scrap_quantity" value="Scrap / unusable" />
                        <TextInput id="scrap_quantity" v-model="recoveryForm.scrap_quantity" type="number" min="0" step="1" class="mt-1 block w-full" />
                        <InputError :message="recoveryForm.errors.scrap_quantity" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        <input v-model="recoveryForm.paid_labor" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        <span>
                            <span class="block font-semibold">Paid recovery labor</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">Turn off when this work has no wage.</span>
                        </span>
                    </label>
                    <div v-if="recoveryForm.paid_labor">
                        <InputLabel for="recovery_staff_id" value="Cutting staff" />
                        <SearchableSelect
                            id="recovery_staff_id"
                            v-model="recoveryForm.staff_id"
                            :options="staffSearchOptions"
                            placeholder="Search cutting staff..."
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Started at" />
                        <DatePicker v-model="recoveryForm.started_at" mode="datetime" />
                    </div>
                    <div>
                        <InputLabel value="Finished at" />
                        <DatePicker v-model="recoveryForm.completed_at" mode="datetime" />
                    </div>
                    <div v-if="recoveryForm.paid_labor">
                        <InputLabel for="recovery_piece_rate" value="Piece rate" />
                        <TextInput id="recovery_piece_rate" v-model="recoveryForm.piece_rate" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Auto-filled from Piece Rates when staff and target variant are selected. You can override it.</p>
                        <InputError :message="recoveryForm.errors.piece_rate" class="mt-1" />
                    </div>
                    <div v-if="recoveryForm.paid_labor">
                        <InputLabel for="recovery_wage_paid_amount" value="Wage paid" />
                        <TextInput id="recovery_wage_paid_amount" v-model="recoveryForm.wage_paid_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="recoveryForm.errors.wage_paid_amount" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="recovery_notes" value="Notes" />
                    <textarea id="recovery_notes" v-model="recoveryForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showRecoveryForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': recoveryForm.processing }" :disabled="recoveryForm.processing">
                        {{ editingRecovery ? 'Save Changes' : 'Save Recovery' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteBatchTarget"
            title="Delete cutting batch?"
            :message="`This will reverse raw material and part stock for ${deleteBatchTarget?.code}.`"
            :processing="deleteBatchForm.processing"
            @confirm="confirmDeleteBatch"
            @cancel="deleteBatchTarget = null"
        />

        <ConfirmModal
            :show="!!deleteRecoveryTarget"
            title="Delete recovery cutting?"
            :message="`This will reverse recoverable and good part stock for ${deleteRecoveryTarget?.code}.`"
            :processing="deleteRecoveryForm.processing"
            @confirm="confirmDeleteRecovery"
            @cancel="deleteRecoveryTarget = null"
        />
    </AuthenticatedLayout>
</template>
