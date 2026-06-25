<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    workOrder: Object,
    materials: Array,
    parts: Array,
    jobCards: Array,
    stageProgress: Array,
    laborCost: Number,
    stockPreview: Array,
    staffOptions: Array,
    stageOptions: Array,
    stageRates: Object,
    paymentMethods: Array,
    pieceRateOptions: Array,
});

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const nowInput = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};
const durationText = (minutes) => {
    if (minutes == null) return '-';
    const hrs = Math.floor(Number(minutes) / 60);
    const mins = Number(minutes) % 60;
    return hrs > 0 ? `${hrs}h ${mins}m` : `${mins}m`;
};
const stageLabel = (stage) => productionStages.value.find((item) => item.value === stage)?.label ?? stage;
const isDraft = () => props.workOrder.status === 'draft';
const inProduction = () => props.workOrder.status === 'in_production';
const canCompleteWorkOrder = computed(
    () => inProduction() && props.jobCards.length > 0 && props.jobCards.every((card) => card.status === 'completed'),
);
const productionStages = computed(() => props.stageOptions ?? []);
const stageSearchOptions = computed(() => productionStages.value.map((stage) => ({ value: stage.value, label: stage.label })));
const paymentMethodSearchOptions = computed(() => [
    { value: '', label: 'Select method' },
    ...props.paymentMethods.map((method) => ({ value: method.value, label: method.label })),
]);
const partSearchOptions = computed(() => props.parts.map((part) => ({ value: part.part_id, label: part.name })));
/* Release */
const releaseForm = useForm({});
function release() {
    releaseForm.post(route('work-orders.release', props.workOrder.id), { preserveScroll: true });
}

/* Complete work order */
const showComplete = ref(false);
const completeForm = useForm({
    completed_quantity: props.workOrder.quantity,
    rejected_quantity: 0,
    completion_notes: '',
});
function completeWorkOrder() {
    completeForm.post(route('work-orders.complete', props.workOrder.id), {
        preserveScroll: true,
        onSuccess: () => (showComplete.value = false),
    });
}

/* Add job card */
const showJobCard = ref(false);
const jobForm = useForm({ stage: '', staff_id: '', quantity_issued: props.workOrder.quantity, piece_rate: 0, started_at: '', notes: '', part_issue_lines: [] });
const issuedForStage = (stage, excludeId = null) => props.jobCards
    .filter((card) => card.stage === stage && Number(card.id) !== Number(excludeId))
    .reduce((total, card) => total + Number(card.quantity_issued || 0), 0);
const previousStageFor = (stage) => {
    const index = productionStages.value.findIndex((item) => item.value === stage);

    return index > 0 ? productionStages.value[index - 1]?.value : null;
};
const availableForStage = (stage) => {
    const previousStage = previousStageFor(stage);

    if (!previousStage) {
        return Number(props.workOrder.quantity || 0);
    }

    return props.jobCards
        .filter((card) => card.stage === previousStage)
        .reduce((total, card) => total + Number(card.quantity_received || 0), 0);
};
const remainingForStage = (stage, excludeId = null) => Math.max(0, availableForStage(stage) - issuedForStage(stage, excludeId));
const stageAvailabilityHelp = (stage, remaining) => {
    const previousStage = previousStageFor(stage);
    const stageLabel = productionStages.value.find((item) => item.value === stage)?.label ?? stage;
    const previousStageLabel = productionStages.value.find((item) => item.value === previousStage)?.label ?? previousStage;

    return previousStage
        ? `Available for ${stageLabel}: ${remaining}. Based on good pieces received from ${previousStageLabel}.`
        : `Remaining for ${stageLabel}: ${remaining} of ${props.workOrder.quantity}.`;
};
const jobStageRemaining = computed(() => remainingForStage(jobForm.stage));
const jobStageIssuesParts = computed(() => {
    const firstStage = productionStages.value[0]?.value;

    return props.parts.length > 0 && jobForm.stage === firstStage;
});
const stagePriority = (stage) => productionStages.value.find((item) => item.value === stage)?.priority ?? null;
const staffForStage = (stage) => {
    const priority = stagePriority(stage);

    return props.staffOptions.filter((staff) => Number(staff.designation_priority_level) === Number(priority));
};
const jobStaffOptions = computed(() => staffForStage(jobForm.stage));
const editStaffOptions = computed(() => staffForStage(editForm.stage));
const jobStaffSearchOptions = computed(() => [
    { value: '', label: '- select staff -' },
    ...jobStaffOptions.value.map((staff) => ({ value: staff.id, label: staffLabel(staff) })),
]);
const editStaffSearchOptions = computed(() => [
    { value: '', label: '- select staff -' },
    ...editStaffOptions.value.map((staff) => ({ value: staff.id, label: staffLabel(staff) })),
]);
const staffMatchesStage = (staffId, stage) => staffForStage(stage).some((staff) => Number(staff.id) === Number(staffId));
const expectedCutPieces = (stage, quantity) => {
    return null;
};
const expectedCutPartBreakdown = (stage, quantity) => {
    return [];
};
const quantityUnitHelp = (stage, quantity) => {
    const cutPieces = expectedCutPieces(stage, quantity);

    return cutPieces == null
        ? 'Quantity is garment/set count for this stage.'
        : `${Number(quantity || 0)} set(s) will produce ${cutPieces} cut part piece(s).`;
};
function stageFromStaff(staff) {
    if (!staff?.designation_priority_level) return null;

    return productionStages.value.find((stage) => Number(stage.priority) === Number(staff.designation_priority_level))?.value ?? null;
}
function syncPieceRate(formState) {
    const staff = props.staffOptions.find((item) => Number(item.id) === Number(formState.staff_id));

    if (staff?.salary_type === 'monthly') {
        formState.piece_rate = 0;
        return;
    }

    formState.piece_rate = resolvePieceRate(formState.stage, formState.staff_id, props.workOrder.product_variant_id);
}
function resolvePieceRate(stage, staffId, variantId) {
    const rates = props.pieceRateOptions || [];
    const staff = Number(staffId || 0);
    const variant = Number(variantId || 0);
    const match = (staffMatcher, variantMatcher) =>
        rates.find((rate) => rate.stage === stage && staffMatcher(rate) && variantMatcher(rate));

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

    return globalDefault?.rate ?? props.stageRates[stage] ?? 0;
}
function syncStaffForStage(formState) {
    if (staffMatchesStage(formState.staff_id, formState.stage)) {
        return;
    }

    formState.staff_id = staffForStage(formState.stage)[0]?.id ?? '';
}
const suggestedPartIssueQuantity = (part, quantity) => {
    const workOrderQuantity = Math.max(1, Number(props.workOrder.quantity || 1));
    const perSet = Number(part.quantity || 0) / workOrderQuantity;
    const suggested = Math.round(perSet * Number(quantity || 0));

    return Math.min(Number(part.quantity_pending ?? part.quantity ?? 0), suggested);
};
function syncJobPartIssueLines() {
    if (!jobStageIssuesParts.value) {
        jobForm.part_issue_lines = [];
        return;
    }

    jobForm.part_issue_lines = props.parts.map((part) => ({
        part_id: part.part_id,
        name: part.name,
        total: part.quantity,
        pending: part.quantity_pending,
        quantity: suggestedPartIssueQuantity(part, jobForm.quantity_issued),
    }));
}

function resetJobForm() {
    jobForm.stage = productionStages.value[0]?.value ?? '';
    syncStaffForStage(jobForm);
    jobForm.quantity_issued = remainingForStage(jobForm.stage);
    syncPieceRate(jobForm);
    jobForm.started_at = nowInput();
    jobForm.notes = '';
    syncJobPartIssueLines();
}
function openJobCard() {
    jobForm.clearErrors();
    resetJobForm();
    showJobCard.value = true;
}
function onStageChange() {
    syncStaffForStage(jobForm);
    syncPieceRate(jobForm);
    jobForm.quantity_issued = jobStageRemaining.value;
    syncJobPartIssueLines();
}
function onJobQuantityChange() {
    syncJobPartIssueLines();
}
function submitJobCard() {
    if (Number(jobForm.quantity_issued) > jobStageRemaining.value) {
        jobForm.setError('quantity_issued', `Only ${jobStageRemaining.value} pieces are remaining for this stage.`);
        return;
    }

    jobForm.post(route('job-cards.store', props.workOrder.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetJobForm();
            showJobCard.value = false;
        },
    });
}
watch(() => props.parts, () => syncJobPartIssueLines(), { deep: true });

/* Edit job card */
const editCard = ref(null);
const editForm = useForm({ stage: '', staff_id: '', quantity_issued: 1, piece_rate: 0, started_at: '', notes: '' });
const editStageRemaining = computed(() => remainingForStage(editForm.stage, editCard.value?.id));
function openEditCard(card) {
    editCard.value = card;
    editForm.clearErrors();
    editForm.stage = card.stage;
    editForm.staff_id = card.staff_id ?? '';
    editForm.quantity_issued = card.quantity_issued;
    editForm.piece_rate = card.piece_rate ?? 0;
    editForm.started_at = card.started_at_input ?? '';
    editForm.notes = card.notes ?? '';
}
function onEditStageChange() {
    const alreadyReturned = Number(editCard.value?.quantity_received || 0) + Number(editCard.value?.quantity_damaged || 0);
    syncStaffForStage(editForm);
    syncPieceRate(editForm);
    editForm.quantity_issued = Math.max(alreadyReturned || 1, Math.min(Number(editForm.quantity_issued || 1), editStageRemaining.value));
}
function onEditStaffChange() {
    const staff = props.staffOptions.find((item) => Number(item.id) === Number(editForm.staff_id));
    editForm.stage = stageFromStaff(staff) ?? editForm.stage;
    syncPieceRate(editForm);
    editForm.quantity_issued = Math.min(Number(editForm.quantity_issued || 1), editStageRemaining.value);
}
function submitEditCard() {
    if (Number(editForm.quantity_issued) > editStageRemaining.value) {
        editForm.setError('quantity_issued', `Only ${editStageRemaining.value} pieces are available for this stage.`);
        return;
    }

    editForm.put(route('job-cards.update', editCard.value.id), {
        preserveScroll: true,
        onSuccess: () => (editCard.value = null),
    });
}

/* Complete job card */
const completeCard = ref(null);
const cardForm = useForm({ quantity_received: 0, quantity_damaged: 0, wage_paid_amount: 0, started_at: '', completed_at: '', notes: '' });
function openCompleteCard(card) {
    completeCard.value = card;
    cardForm.reset();
    cardForm.clearErrors();
    cardForm.quantity_received = card.pending_quantity;
    cardForm.quantity_damaged = 0;
    cardForm.wage_paid_amount = 0;
    cardForm.started_at = card.started_at_input ?? '';
    cardForm.completed_at = nowInput();
    cardForm.notes = '';
}
function submitCompleteCard() {
    cardForm.post(route('job-cards.complete', completeCard.value.id), {
        preserveScroll: true,
        onSuccess: () => (completeCard.value = null),
    });
}

/* Job card payments */
const paymentTarget = ref(null);
const paymentsTarget = ref(null);
const paymentForm = useForm({ paid_on: new Date().toISOString().slice(0, 10), amount: '', method: '', reference: '', notes: '' });
const editPaymentTarget = ref(null);
const editPaymentForm = useForm({ paid_on: '', amount: '', method: '', reference: '', notes: '' });
const deletePaymentTarget = ref(null);
const deletePaymentForm = useForm({});
function openPayment(card) {
    paymentTarget.value = card;
    paymentForm.clearErrors();
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.amount = Math.max(0, Number(card.wage_balance || 0)).toFixed(2);
    paymentForm.method = props.paymentMethods?.[0]?.value ?? 'cash';
    paymentForm.reference = '';
    paymentForm.notes = '';
}
function submitPayment() {
    paymentForm.post(route('job-cards.payments.store', paymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentTarget.value = null;
            paymentForm.reset();
        },
    });
}
function openEditPayment(payment) {
    editPaymentTarget.value = payment;
    editPaymentForm.clearErrors();
    editPaymentForm.paid_on = payment.paid_on_input ?? payment.paid_on ?? '';
    editPaymentForm.amount = payment.amount;
    editPaymentForm.method = payment.method ?? '';
    editPaymentForm.reference = payment.reference ?? '';
    editPaymentForm.notes = payment.notes ?? '';
}
function submitEditPayment() {
    editPaymentForm.put(route('job-card-payments.update', editPaymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editPaymentTarget.value = null;
            paymentsTarget.value = null;
        },
    });
}
function confirmDeletePayment() {
    deletePaymentForm.delete(route('job-card-payments.destroy', deletePaymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletePaymentTarget.value = null;
            paymentsTarget.value = null;
        },
    });
}

/* Job card parts */
const partLedgerTarget = ref(null);
const partMovementForm = useForm({ type: 'issue', part_id: '', quantity: '', notes: '' });
const partMovementTypes = [
    { value: 'issue', label: 'Issue parts to staff' },
    { value: 'return_good', label: 'Return unused good parts' },
    { value: 'return_recoverable', label: 'Return damaged recoverable parts' },
    { value: 'scrap', label: 'Record scrap / waste' },
];
const partMovementTypeLabel = (type) => partMovementTypes.find((item) => item.value === type)?.label ?? type;
function openPartLedger(card) {
    partLedgerTarget.value = card;
    partMovementForm.clearErrors();
    partMovementForm.type = 'issue';
    partMovementForm.part_id = props.parts[0]?.part_id ?? '';
    partMovementForm.quantity = '';
    partMovementForm.notes = '';
}
function submitPartMovement() {
    partMovementForm.post(route('job-cards.part-movements.store', partLedgerTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            partMovementForm.quantity = '';
            partMovementForm.notes = '';
        },
    });
}

/* Delete job card */
const deleteCard = ref(null);
const deleteCardForm = useForm({});
function confirmDeleteCard() {
    deleteCardForm.delete(route('job-cards.destroy', deleteCard.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteCard.value = null),
    });
}

const statusClass = (s) => ({
    draft: 'bg-gray-100 text-gray-700',
    in_production: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
}[s] ?? 'bg-gray-100 text-gray-700');
const wageStatusClass = (status) => ({
    pending: 'text-amber-700',
    paid: 'text-emerald-700',
    overpaid: 'text-red-700',
}[status] ?? 'text-gray-500');
const staffLabel = (staff) => staff.salary_type === 'monthly'
    ? `${staff.name} (monthly)`
    : `${staff.name} (piece rate)`;
function onStaffChange() {
    const staff = props.staffOptions.find((item) => Number(item.id) === Number(jobForm.staff_id));
    jobForm.stage = stageFromStaff(staff) ?? jobForm.stage;
    syncPieceRate(jobForm);
    jobForm.quantity_issued = jobStageRemaining.value;
}
const receiptsTarget = ref(null);
</script>

<template>
    <Head :title="`Work Order ${workOrder.code}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('work-orders.index')" class="text-sm text-indigo-600 hover:underline">Work Orders</Link>
                <span class="text-gray-400">/</span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ workOrder.code }}</h2>
                <span class="ml-2 rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusClass(workOrder.status)">
                    {{ workOrder.status.replace('_', ' ') }}
                </span>
            </div>
        </template>

        <div class="space-y-6 py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Summary -->
                <div class="grid grid-cols-2 gap-4 rounded-lg bg-white p-6 text-sm shadow-sm sm:grid-cols-5">
                    <div><div class="text-gray-500">Product</div><div class="font-medium">{{ workOrder.product }}</div></div>
                    <div><div class="text-gray-500">Quantity</div><div class="font-medium">{{ workOrder.quantity }}</div></div>
                    <div><div class="text-gray-500">Target delivery</div><div class="font-medium">{{ workOrder.target_delivery_date || '-' }}</div></div>
                    <div><div class="text-gray-500">Material cost</div><div class="font-medium">{{ money(workOrder.material_cost) }}</div></div>
                    <div><div class="text-gray-500">Labor cost</div><div class="font-medium">{{ money(laborCost) }}</div></div>
                </div>

                <!-- Release error -->
                <div v-if="releaseForm.errors.release" class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <div class="font-medium">Cannot release - insufficient stock:</div>
                    <div>{{ releaseForm.errors.release }}</div>
                </div>

                <!-- Draft: stock check + release -->
                <section v-if="isDraft()" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">Material check (before release)</h3>
                        <PrimaryButton :class="{ 'opacity-50': releaseForm.processing }" :disabled="releaseForm.processing" @click="release">
                            Release to production
                        </PrimaryButton>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Required part</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Needed</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Available</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase text-gray-500">OK?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(r, i) in stockPreview" :key="i" :class="{ 'bg-red-50': !r.ok }">
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ r.label }}</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-700">{{ r.needed }}</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-700">{{ r.available }}</td>
                                <td class="px-6 py-3 text-center text-sm">
                                    <span :class="r.ok ? 'text-emerald-600' : 'text-red-600'">{{ r.ok ? 'OK' : 'NO' }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="px-6 py-3 text-xs text-gray-500">
                        Releasing checks pre-cut part stock and issues the required parts for this work order.
                    </p>
                </section>

                <!-- Released: required parts issued -->
                <div v-if="!isDraft()" class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-800">Raw materials</h3>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Material</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Cost</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(m, i) in materials" :key="i">
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ m.label }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-gray-700">{{ m.quantity }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-gray-700">{{ money(m.total_cost) }}</td>
                                </tr>
                                <tr v-if="materials.length === 0">
                                    <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500">
                                        Raw material is consumed in cutting batches before work orders.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                        <h3 class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-800">Required parts issued</h3>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Part</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Total pieces</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Issued</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Damaged</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Pending</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(p, i) in parts" :key="i">
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ p.name }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-medium text-gray-700">{{ p.quantity }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-medium text-emerald-700">{{ p.quantity_issued }}</td>
                                    <td class="px-4 py-2 text-right text-sm" :class="p.quantity_damaged > 0 ? 'font-medium text-red-600' : 'text-gray-500'">
                                        {{ p.quantity_damaged || '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm" :class="p.quantity_pending > 0 ? 'font-medium text-amber-700' : 'text-gray-500'">
                                        {{ p.quantity_pending }}
                                    </td>
                                </tr>
                                <tr v-if="parts.length === 0">
                                    <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">No parts defined in recipe.</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <section v-if="!isDraft()" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">Production stage flow</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Stages come from Business Settings. Each stage can only issue the good quantity received from the previous stage.
                        </p>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Stage</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Available</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Issued</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Good output</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Damaged</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Waiting</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Ready</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="stage in stageProgress" :key="stage.stage">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                        <div>{{ stage.label }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ stage.is_first ? 'Issued parts to production' : stage.is_final ? 'Final product output' : 'Work in progress' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm text-gray-700">{{ stage.available }}</td>
                                    <td class="px-4 py-2 text-right text-sm text-gray-700">{{ stage.issued }}</td>
                                    <td class="px-4 py-2 text-right text-sm font-medium text-emerald-700">{{ stage.good }}</td>
                                    <td class="px-4 py-2 text-right text-sm" :class="stage.damaged > 0 ? 'font-medium text-red-600' : 'text-gray-500'">
                                        {{ stage.damaged || '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm" :class="stage.waiting_to_receive > 0 ? 'font-medium text-amber-700' : 'text-gray-500'">
                                        {{ stage.waiting_to_receive || '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-sm" :class="stage.ready_for_next > 0 || stage.available_to_issue > 0 ? 'font-medium text-indigo-700' : 'text-gray-500'">
                                        {{ stage.is_final ? stage.good : stage.ready_for_next }}
                                        <div v-if="stage.available_to_issue > 0" class="text-xs text-gray-500">
                                            {{ stage.available_to_issue }} can issue
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Job cards -->
                <section v-if="!isDraft()" class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <h3 class="font-semibold text-gray-800">Job cards (stage tracking &amp; wages)</h3>
                        <div class="flex gap-2">
                            <PrimaryButton v-if="inProduction()" @click="openJobCard">+ Issue Job Card</PrimaryButton>
                            <SecondaryButton
                                v-if="inProduction()"
                                :disabled="!canCompleteWorkOrder"
                                :class="{ 'opacity-50': !canCompleteWorkOrder }"
                                :title="canCompleteWorkOrder ? 'Complete work order' : 'Complete all job cards first'"
                                @click="canCompleteWorkOrder && (showComplete = true)"
                            >
                                Complete Work Order
                            </SecondaryButton>
                        </div>
                    </div>
                    <p v-if="inProduction() && !canCompleteWorkOrder" class="border-b border-gray-100 px-6 py-2 text-xs text-amber-700 dark:border-gray-800 dark:text-amber-300">
                        Complete all issued job cards before completing this work order.
                    </p>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Stage</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Staff</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Issued</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Good</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Damaged</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Pending</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Rate</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Wage</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500">Time</th>
                                <th class="px-4 py-2 text-right text-xs font-medium uppercase text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="j in jobCards" :key="j.id">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ stageLabel(j.stage) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700">{{ j.staff || '-' }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ j.quantity_issued }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ j.quantity_received ?? '-' }}</td>
                                <td class="px-4 py-2 text-right text-sm" :class="j.quantity_damaged > 0 ? 'font-medium text-red-600' : 'text-gray-500'">
                                    {{ j.quantity_damaged || '-' }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm" :class="j.pending_quantity > 0 ? 'font-medium text-amber-700' : 'text-gray-500'">
                                    {{ j.pending_quantity || '-' }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ money(j.piece_rate) }}</td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">
                                    <div>{{ j.wage_amount != null ? money(j.wage_amount) : '-' }}</div>
                                    <div class="text-xs" :class="wageStatusClass(j.wage_status)">
                                        Paid {{ money(j.wage_paid_amount || 0) }} / {{ j.wage_status }}
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-right text-sm text-gray-700">{{ durationText(j.duration_minutes) }}</td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <ActionMenu>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="openEditCard(j)">
                                            <AppIcon name="edit" /> Edit
                                        </button>
                                        <button
                                            v-if="j.status !== 'completed'"
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                            @click="openCompleteCard(j)"
                                        >
                                            <AppIcon name="check" /> Receive
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="receiptsTarget = j">
                                            <AppIcon name="eye" /> View receipts
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="openPartLedger(j)">
                                            <AppIcon name="recipe" /> Part movements
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="openPayment(j)">
                                            <AppIcon name="cash" /> Add payment
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="paymentsTarget = j">
                                            <AppIcon name="cash" /> View payments
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-950" @click="deleteCard = j">
                                            <AppIcon name="x" /> Delete
                                        </button>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="jobCards.length === 0">
                                <td colspan="10" class="px-4 py-6 text-center text-sm text-gray-500">
                                    No job cards yet. Issue work to staff by production stage.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <div v-if="workOrder.status === 'completed'" class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800">
                    Completed {{ workOrder.completed_at }} - {{ workOrder.completed_quantity }} garments finished,
                    {{ workOrder.rejected_quantity || 0 }} rejected,
                    {{ workOrder.shortfall_quantity || 0 }} shortfall.
                    <div v-if="workOrder.completion_notes" class="mt-2 text-emerald-700">
                        {{ workOrder.completion_notes }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Add job card modal -->
        <Modal :show="showJobCard" @close="showJobCard = false">
            <form @submit.prevent="submitJobCard" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Issue Job Card</h2>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="stage" value="Stage" />
                        <SearchableSelect id="stage" v-model="jobForm.stage" :options="stageSearchOptions" placeholder="Search stage..." class="mt-1" @update:model-value="onStageChange" />
                        <InputError :message="jobForm.errors.stage" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="staff" value="Staff" />
                        <SearchableSelect id="staff" v-model="jobForm.staff_id" :options="jobStaffSearchOptions" placeholder="Search staff..." class="mt-1" @update:model-value="onStaffChange" />
                        <p v-if="jobStaffOptions.length === 0" class="mt-1 text-xs text-amber-600 dark:text-amber-300">
                            No active staff designation matches this stage priority.
                        </p>
                        <InputError :message="jobForm.errors.staff_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="issued" value="Quantity issued" />
                        <TextInput
                            id="issued"
                            type="number"
                            min="1"
                            :max="jobStageRemaining"
                            step="1"
                            v-model="jobForm.quantity_issued"
                            @input="onJobQuantityChange"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ stageAvailabilityHelp(jobForm.stage, jobStageRemaining) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ quantityUnitHelp(jobForm.stage, jobForm.quantity_issued) }}
                        </p>
                        <div
                            v-if="expectedCutPartBreakdown(jobForm.stage, jobForm.quantity_issued).length"
                            class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <div class="font-semibold">Cut output breakdown</div>
                            <div class="mt-1 grid grid-cols-2 gap-1 sm:grid-cols-3">
                                <div v-for="part in expectedCutPartBreakdown(jobForm.stage, jobForm.quantity_issued)" :key="part.name">
                                    {{ part.name }}: <span class="font-semibold">{{ part.quantity }}</span>
                                </div>
                            </div>
                        </div>
                        <InputError :message="jobForm.errors.quantity_issued" class="mt-1" />
                    </div>
                    <div class="col-span-2">
                        <InputLabel for="job_started_at" value="Started at" />
                        <DatePicker id="job_started_at" v-model="jobForm.started_at" mode="datetime" class="mt-1" />
                        <InputError :message="jobForm.errors.started_at" class="mt-1" />
                    </div>
                    <div v-if="jobStageIssuesParts" class="col-span-2 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Parts out with this job card</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Record what pieces are handed to this staff now. Set 0 if a part is not given yet.</div>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-white dark:bg-gray-950">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Part</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Required</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Pending</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Give now</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="(line, index) in jobForm.part_issue_lines" :key="line.part_id">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ line.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ line.total }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ line.pending }}</td>
                                        <td class="px-4 py-3">
                                            <TextInput
                                                v-model="jobForm.part_issue_lines[index].quantity"
                                                type="number"
                                                min="0"
                                                step="1"
                                                class="ml-auto block w-32 text-right"
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <InputError :message="jobForm.errors.part_issue_lines" class="px-4 py-2" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showJobCard = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': jobForm.processing || jobStageRemaining < 1 || !jobForm.staff_id }" :disabled="jobForm.processing || jobStageRemaining < 1 || !jobForm.staff_id">Issue</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Edit job card modal -->
        <Modal :show="!!editCard" @close="editCard = null">
            <form @submit.prevent="submitEditCard" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Edit Job Card</h2>
                <p v-if="editCard" class="mt-1 text-sm text-gray-500">
                    Already returned {{ Number(editCard.quantity_received || 0) + Number(editCard.quantity_damaged || 0) }} pieces.
                </p>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="edit_stage" value="Stage" />
                        <SearchableSelect id="edit_stage" v-model="editForm.stage" :options="stageSearchOptions" placeholder="Search stage..." class="mt-1" @update:model-value="onEditStageChange" />
                        <InputError :message="editForm.errors.stage" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_staff" value="Staff" />
                        <SearchableSelect id="edit_staff" v-model="editForm.staff_id" :options="editStaffSearchOptions" placeholder="Search staff..." class="mt-1" @update:model-value="onEditStaffChange" />
                        <p v-if="editStaffOptions.length === 0" class="mt-1 text-xs text-amber-600 dark:text-amber-300">
                            No active staff designation matches this stage priority.
                        </p>
                        <InputError :message="editForm.errors.staff_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_issued" value="Quantity issued" />
                        <TextInput
                            id="edit_issued"
                            type="number"
                            :min="Number(editCard?.quantity_received || 0) + Number(editCard?.quantity_damaged || 0) || 1"
                            :max="editStageRemaining"
                            step="1"
                            v-model="editForm.quantity_issued"
                            class="mt-1 block w-full"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ stageAvailabilityHelp(editForm.stage, editStageRemaining) }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ quantityUnitHelp(editForm.stage, editForm.quantity_issued) }}
                        </p>
                        <div
                            v-if="expectedCutPartBreakdown(editForm.stage, editForm.quantity_issued).length"
                            class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <div class="font-semibold">Cut output breakdown</div>
                            <div class="mt-1 grid grid-cols-2 gap-1 sm:grid-cols-3">
                                <div v-for="part in expectedCutPartBreakdown(editForm.stage, editForm.quantity_issued)" :key="part.name">
                                    {{ part.name }}: <span class="font-semibold">{{ part.quantity }}</span>
                                </div>
                            </div>
                        </div>
                        <InputError :message="editForm.errors.quantity_issued" class="mt-1" />
                    </div>
                    <div class="col-span-2">
                        <InputLabel for="edit_started_at" value="Started at" />
                        <DatePicker id="edit_started_at" v-model="editForm.started_at" mode="datetime" class="mt-1" />
                        <InputError :message="editForm.errors.started_at" class="mt-1" />
                    </div>
                    <div class="col-span-2">
                        <InputLabel for="edit_notes" value="Notes" />
                        <TextInput id="edit_notes" v-model="editForm.notes" class="mt-1 block w-full" />
                        <InputError :message="editForm.errors.notes" class="mt-1" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    Piece rate comes from Piece Rates. If staff salary type changes, receipt wages are recalculated and paid amounts stay unchanged.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="editCard = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': editForm.processing }" :disabled="editForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Receive job card modal -->
        <Modal :show="!!completeCard" @close="completeCard = null">
            <form @submit.prevent="submitCompleteCard" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Receive Job Card</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ completeCard?.pending_quantity }} pending of {{ completeCard?.quantity_issued }} issued.
                    Wage is calculated only on good pieces.
                </p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="received" value="Good pieces received now" />
                        <TextInput id="received" type="number" min="0" :max="completeCard?.pending_quantity" step="1"
                            v-model="cardForm.quantity_received" class="mt-1 block w-full" autofocus />
                        <InputError :message="cardForm.errors.quantity_received" class="mt-1" />
                        <p class="mt-1 text-xs text-gray-500">Wage is calculated from good pieces only.</p>
                    </div>
                    <div>
                        <InputLabel for="damaged" value="Damaged / returned pieces" />
                        <TextInput id="damaged" type="number" min="0" :max="completeCard?.pending_quantity" step="1"
                            v-model="cardForm.quantity_damaged" class="mt-1 block w-full" />
                        <InputError :message="cardForm.errors.quantity_damaged" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="started_at" value="Started at" />
                        <DatePicker id="started_at" v-model="cardForm.started_at" mode="datetime" readonly class="mt-1" />
                        <InputError :message="cardForm.errors.started_at" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="completed_at" value="Finished / received at" />
                        <DatePicker id="completed_at" v-model="cardForm.completed_at" mode="datetime" class="mt-1" />
                        <InputError :message="cardForm.errors.completed_at" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="wage_paid_amount" value="Wage paid now" />
                        <TextInput id="wage_paid_amount" type="number" min="0" step="0.01" v-model="cardForm.wage_paid_amount" class="mt-1 block w-full" />
                        <InputError :message="cardForm.errors.wage_paid_amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="receipt_notes" value="Notes" />
                        <TextInput id="receipt_notes" v-model="cardForm.notes" class="mt-1 block w-full" />
                        <InputError :message="cardForm.errors.notes" class="mt-1" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    If a balance remains after this receipt, the job card stays open and can be received again later.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="completeCard = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': cardForm.processing }" :disabled="cardForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!receiptsTarget" max-width="2xl" @close="receiptsTarget = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Receipt History</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ stageLabel(receiptsTarget?.stage) }} / {{ receiptsTarget?.staff || 'Unassigned' }}
                </p>

                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <div class="text-xs uppercase text-gray-500">Issued</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ receiptsTarget?.quantity_issued ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-emerald-50 p-4">
                        <div class="text-xs uppercase text-emerald-700">Received</div>
                        <div class="mt-1 text-2xl font-semibold text-emerald-900">{{ receiptsTarget?.quantity_received ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-4">
                        <div class="text-xs uppercase text-amber-700">Pending</div>
                        <div class="mt-1 text-2xl font-semibold text-amber-900">{{ receiptsTarget?.pending_quantity ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-red-50 p-4">
                        <div class="text-xs uppercase text-red-700">Damaged</div>
                        <div class="mt-1 text-2xl font-semibold text-red-900">{{ receiptsTarget?.quantity_damaged ?? 0 }}</div>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-4">
                        <div class="text-xs uppercase text-indigo-700">Total time</div>
                        <div class="mt-1 text-2xl font-semibold text-indigo-900">{{ durationText(receiptsTarget?.duration_minutes) }}</div>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Received at</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Good</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Damaged</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Time</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Wage</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Paid</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="receipt in receiptsTarget?.receipts ?? []" :key="receipt.id">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ receipt.received_at || receipt.received_on }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ receipt.quantity_received }}</td>
                                <td class="px-4 py-3 text-right text-sm" :class="receipt.quantity_damaged > 0 ? 'text-red-600' : 'text-gray-500'">{{ receipt.quantity_damaged || '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ durationText(receipt.duration_minutes) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(receipt.wage_amount) }}</td>
                                <td class="px-4 py-3 text-right text-sm" :class="receipt.wage_balance > 0 ? 'text-amber-700' : receipt.wage_balance < 0 ? 'text-red-700' : 'text-emerald-700'">
                                    {{ money(receipt.wage_paid_amount) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ receipt.notes || '-' }}</td>
                            </tr>
                            <tr v-if="(receiptsTarget?.receipts ?? []).length === 0">
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No receipts recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="receiptsTarget = null">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="!!partLedgerTarget" max-width="2xl" @close="partLedgerTarget = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Part Movements</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ stageLabel(partLedgerTarget?.stage) }} / {{ partLedgerTarget?.staff || 'Unassigned' }}
                </p>

                <form class="mt-5 rounded-lg border border-gray-200 p-4" @submit.prevent="submitPartMovement">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel for="part_movement_type" value="Movement" />
                            <select
                                id="part_movement_type"
                                v-model="partMovementForm.type"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="type in partMovementTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                            <InputError :message="partMovementForm.errors.type" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="part_movement_part" value="Part" />
                            <SearchableSelect id="part_movement_part" v-model="partMovementForm.part_id" :options="partSearchOptions" placeholder="Search part..." class="mt-1" />
                            <InputError :message="partMovementForm.errors.part_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="part_movement_quantity" value="Quantity" />
                            <TextInput id="part_movement_quantity" v-model="partMovementForm.quantity" type="number" min="1" step="1" class="mt-1 block w-full" />
                            <InputError :message="partMovementForm.errors.quantity" class="mt-1" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel for="part_movement_notes" value="Notes" />
                        <TextInput id="part_movement_notes" v-model="partMovementForm.notes" class="mt-1 block w-full" />
                        <InputError :message="partMovementForm.errors.notes" class="mt-1" />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <PrimaryButton :disabled="partMovementForm.processing" :class="{ 'opacity-50': partMovementForm.processing }">Record movement</PrimaryButton>
                    </div>
                </form>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Movement</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Part</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="movement in partLedgerTarget?.part_movements ?? []" :key="movement.id">
                                <td class="px-4 py-3 text-sm text-gray-500">{{ movement.created_at }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ partMovementTypeLabel(movement.type) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ movement.part }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ movement.quantity }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ movement.notes || '-' }}</td>
                            </tr>
                            <tr v-if="(partLedgerTarget?.part_movements ?? []).length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No part movements recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="partLedgerTarget = null">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="!!paymentTarget" @close="paymentTarget = null">
            <form class="p-6" @submit.prevent="submitPayment">
                <h2 class="text-lg font-medium text-gray-900">Add Job Card Payment</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ stageLabel(paymentTarget?.stage) }} / {{ paymentTarget?.staff || 'Unassigned' }}.
                    Balance: {{ money(paymentTarget?.wage_balance ?? 0) }}
                </p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="job_paid_on" value="Paid on" />
                        <DatePicker id="job_paid_on" v-model="paymentForm.paid_on" class="mt-1" />
                        <InputError :message="paymentForm.errors.paid_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="job_payment_amount" value="Amount" />
                        <TextInput id="job_payment_amount" v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="paymentForm.errors.amount" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="job_payment_method" value="Method" />
                        <SearchableSelect id="job_payment_method" v-model="paymentForm.method" :options="paymentMethodSearchOptions" placeholder="Search method..." class="mt-1" />
                        <InputError :message="paymentForm.errors.method" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="job_payment_reference" value="Reference" />
                        <TextInput id="job_payment_reference" v-model="paymentForm.reference" class="mt-1 block w-full" />
                        <InputError :message="paymentForm.errors.reference" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <InputLabel for="job_payment_notes" value="Notes" />
                    <textarea
                        id="job_payment_notes"
                        v-model="paymentForm.notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <InputError :message="paymentForm.errors.notes" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="paymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing" :class="{ 'opacity-50': paymentForm.processing }">Save payment</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!paymentsTarget" max-width="2xl" @close="paymentsTarget = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Payment History</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Wage {{ money(paymentsTarget?.wage_amount ?? 0) }} /
                    Paid {{ money(paymentsTarget?.wage_paid_amount ?? 0) }} /
                    Balance {{ money(paymentsTarget?.wage_balance ?? 0) }}
                </p>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Paid on</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Source</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="payment in paymentsTarget?.payments ?? []" :key="payment.id">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ payment.paid_on }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ payment.method || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ payment.reference || '-' }}</td>
                                <td class="px-4 py-3 text-sm capitalize text-gray-500">{{ payment.source || '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(payment.amount) }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <div v-if="payment.source === 'manual'" class="inline-flex items-center justify-end gap-2">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" title="Edit payment" @click="openEditPayment(payment)">
                                            <AppIcon name="edit" />
                                        </button>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950" title="Delete payment" @click="deletePaymentTarget = payment">
                                            <AppIcon name="trash" />
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">Locked</span>
                                </td>
                            </tr>
                            <tr v-if="(paymentsTarget?.payments ?? []).length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No payments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="paymentsTarget = null">Close</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="!!editPaymentTarget" @close="editPaymentTarget = null">
            <form class="p-6" @submit.prevent="submitEditPayment">
                <h2 class="text-lg font-medium text-gray-900">Edit Payment</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Manual payment for {{ stageLabel(paymentsTarget?.stage) }} / {{ paymentsTarget?.staff || 'Unassigned' }}
                </p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="edit_job_paid_on" value="Paid on" />
                        <DatePicker id="edit_job_paid_on" v-model="editPaymentForm.paid_on" class="mt-1" />
                        <InputError :message="editPaymentForm.errors.paid_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_job_payment_amount" value="Amount" />
                        <TextInput id="edit_job_payment_amount" v-model="editPaymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="editPaymentForm.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_job_payment_method" value="Method" />
                        <SearchableSelect id="edit_job_payment_method" v-model="editPaymentForm.method" :options="paymentMethodSearchOptions" placeholder="Search method..." class="mt-1" />
                        <InputError :message="editPaymentForm.errors.method" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="edit_job_payment_reference" value="Reference" />
                        <TextInput id="edit_job_payment_reference" v-model="editPaymentForm.reference" class="mt-1 block w-full" />
                        <InputError :message="editPaymentForm.errors.reference" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="edit_job_payment_notes" value="Notes" />
                        <textarea
                            id="edit_job_payment_notes"
                            v-model="editPaymentForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError :message="editPaymentForm.errors.notes" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="editPaymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="editPaymentForm.processing" :class="{ 'opacity-50': editPaymentForm.processing }">Save changes</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deletePaymentTarget"
            title="Delete payment?"
            :message="`This will remove the payment of ${money(deletePaymentTarget?.amount ?? 0)} and update the paid balance.`"
            :processing="deletePaymentForm.processing"
            @confirm="confirmDeletePayment"
            @cancel="deletePaymentTarget = null"
        />

        <!-- Complete work order modal -->
        <Modal :show="showComplete" @close="showComplete = false">
            <form @submit.prevent="completeWorkOrder" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Complete Work Order</h2>
                <div class="mt-4">
                    <InputLabel for="cq" value="Finished quantity" />
                    <TextInput id="cq" type="number" min="0" :max="workOrder.quantity" step="1"
                        v-model="completeForm.completed_quantity" class="mt-1 block w-full" />
                    <InputError :message="completeForm.errors.completed_quantity" class="mt-1" />
                    <p class="mt-1 text-xs text-gray-500">This will be added to finished goods stock.</p>
                </div>
                <div class="mt-4">
                    <InputLabel for="rq" value="Rejected / damaged quantity" />
                    <TextInput id="rq" type="number" min="0" :max="workOrder.quantity" step="1"
                        v-model="completeForm.rejected_quantity" class="mt-1 block w-full" />
                    <InputError :message="completeForm.errors.rejected_quantity" class="mt-1" />
                </div>
                <div class="mt-4">
                    <InputLabel for="completion_notes" value="Completion notes" />
                    <textarea
                        id="completion_notes"
                        v-model="completeForm.completion_notes"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <InputError :message="completeForm.errors.completion_notes" class="mt-1" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showComplete = false">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': completeForm.processing }" :disabled="completeForm.processing">Complete</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteCard"
            title="Remove job card?"
            message="This removes the job card and its recorded wage."
            confirmText="Remove"
            :processing="deleteCardForm.processing"
            @confirm="confirmDeleteCard"
            @cancel="deleteCard = null"
        />
    </AuthenticatedLayout>
</template>
