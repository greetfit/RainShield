<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TableControls from '@/Components/TableControls.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTableControls } from '@/Composables/useTableControls';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    stats: Object,
    lowStockMaterials: Array,
    lowPartStock: Array,
    productStockAlerts: Array,
    partStockSummary: Array,
    staffWorkflows: Array,
    paymentMethods: Array,
});

const roles = computed(() => usePage().props.auth.roles ?? []);
const hasAny = (...r) => r.some((x) => roles.value.includes(x));

const money = (n) => Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const quantity = (n) => Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 3 });
const today = () => new Date().toISOString().slice(0, 10);
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

const cards = computed(() => {
    const c = [];
    if (hasAny('admin', 'stock_manager')) {
        c.push({ label: 'Stock value', value: money(props.stats.stock_value), to: 'stock.index', tone: 'indigo' });
        if (props.stats.low_stock > 0) c.push({ label: 'Raw material alerts', value: props.stats.low_stock, to: 'stock.index', tone: 'amber' });
    }
    if (hasAny('admin', 'production_manager')) {
        c.push({ label: 'In production', value: props.stats.wo_in_production, to: 'work-orders.index', tone: 'blue' });
        c.push({ label: 'Draft orders', value: props.stats.wo_draft, to: 'work-orders.index', tone: 'gray' });
        if (props.stats.part_stock_alerts > 0) c.push({ label: 'Part stock alerts', value: props.stats.part_stock_alerts, to: 'part-stock.index', tone: 'amber' });
        if (props.stats.product_stock_alerts > 0) c.push({ label: 'Product alerts', value: props.stats.product_stock_alerts, to: 'finished-goods.index', tone: 'amber' });
        c.push({ label: 'Completed orders', value: props.stats.wo_completed, to: 'work-orders.index', tone: 'emerald' });
        c.push({ label: 'Finished goods', value: props.stats.finished_units, to: 'finished-goods.index', tone: 'emerald' });
        c.push({ label: 'Pending deliveries', value: props.stats.pending_deliveries, to: 'deliveries.index', tone: 'blue' });
        c.push({ label: 'Wages this month', value: money(props.stats.wages_month), to: 'wages.index', tone: 'indigo' });
    }
    return c;
});
const partStockTable = useTableControls(() => props.partStockSummary ?? [], ['label', 'part', 'quantity', 'alert_quantity']);

const toneClass = (tone) => ({
    indigo: 'border-indigo-200 bg-indigo-50/60 text-indigo-900 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-100',
    amber: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100',
    blue: 'border-blue-200 bg-blue-50/60 text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-100',
    emerald: 'border-emerald-200 bg-emerald-50/60 text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-100',
    gray: 'border-gray-200 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100',
}[tone] ?? 'border-gray-200 bg-white text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-100');

const operationMode = ref(null);
const selectedStaff = ref(null);
const activeStaffTab = ref('job_cards');
const selectedHistoryCard = ref(null);
const paymentTarget = ref(null);
const partTarget = ref(null);
const collectTarget = ref(null);

const paymentForm = useForm({ paid_on: today(), amount: '', method: '', reference: '', notes: '' });
const partMovementForm = useForm({ type: 'issue', part_id: '', quantity: '', notes: '' });
const collectForm = useForm({ quantity_received: 0, quantity_damaged: 0, wage_paid_amount: 0, started_at: '', completed_at: '', notes: '' });

const operationCards = computed(() => [
    {
        mode: 'wages',
        title: 'Pay wages',
        description: 'Staff with pending or overpaid wage records.',
        icon: 'cash',
        count: props.staffWorkflows?.filter((staff) => Number(staff.pending_wage || 0) > 0 || Number(staff.overpaid_wage || 0) > 0).length ?? 0,
    },
    {
        mode: 'parts',
        title: 'Give parts',
        description: 'Issue extra parts or record returned and damaged parts.',
        icon: 'puzzle',
        count: props.staffWorkflows?.filter((staff) => staff.cards?.some((card) => card.parts?.length > 0)).length ?? 0,
    },
    {
        mode: 'collect',
        title: 'Collect finished work',
        description: 'Receive completed pieces and record damage.',
        icon: 'check',
        count: props.staffWorkflows?.filter((staff) => staff.cards?.some((card) => Number(card.pending_quantity || 0) > 0)).length ?? 0,
    },
    {
        mode: 'history',
        title: 'Staff activity',
        description: 'View job cards, payments, receipts, and part movements.',
        icon: 'users',
        count: props.staffWorkflows?.length ?? 0,
    },
]);

const operationMeta = computed(() => operationCards.value.find((card) => card.mode === operationMode.value));
const staffListOpen = computed(() => !!operationMode.value);
const filteredStaffWorkflows = computed(() => {
    const staff = props.staffWorkflows ?? [];
    if (operationMode.value === 'wages') {
        return staff.filter((row) => Number(row.pending_wage || 0) > 0 || Number(row.overpaid_wage || 0) > 0);
    }
    if (operationMode.value === 'parts') {
        return staff.filter((row) => row.cards?.some((card) => card.parts?.length > 0));
    }
    if (operationMode.value === 'collect') {
        return staff.filter((row) => row.cards?.some((card) => Number(card.pending_quantity || 0) > 0));
    }
    return staff;
});
const selectedCards = computed(() => selectedStaff.value?.cards ?? []);
const historyTable = useTableControls(() => selectedCards.value, ['work_order_code', 'product', 'stage_label', 'status']);
const cardsWithPendingWages = computed(() => selectedCards.value.filter((card) => Number(card.wage_balance || 0) > 0));
const cardsWithParts = computed(() => selectedCards.value.filter((card) => card.parts?.length > 0));
const cardsToCollect = computed(() => selectedCards.value.filter((card) => Number(card.pending_quantity || 0) > 0));

const staffTabs = [
    { value: 'job_cards', label: 'Job cards' },
    { value: 'parts', label: 'Parts' },
    { value: 'collections', label: 'Collections' },
    { value: 'wages', label: 'Wages' },
    { value: 'history', label: 'History' },
];

const partMovementTypes = [
    { value: 'issue', label: 'Give additional parts' },
    { value: 'return_good', label: 'Take back unused good parts' },
    { value: 'return_recoverable', label: 'Take damaged recoverable parts' },
    { value: 'scrap', label: 'Record waste / scrap' },
];
const paymentMethodOptions = computed(() => [
    { value: '', label: 'Select method' },
    ...(props.paymentMethods ?? []).map((method) => ({ value: method.value, label: method.label })),
]);
const cardPartOptions = computed(() => (partTarget.value?.parts ?? []).map((part) => ({
    value: part.part_id,
    label: part.name,
    description: `Required ${part.required}, issued ${part.issued}`,
})));

const partTypeLabel = (type) => partMovementTypes.find((item) => item.value === type)?.label ?? type;

watch(selectedStaff, () => {
    selectedHistoryCard.value = null;
});

watch(activeStaffTab, () => {
    selectedHistoryCard.value = null;
});

function openOperation(mode) {
    operationMode.value = mode;
    selectedStaff.value = null;
}

function closeOperation() {
    operationMode.value = null;
    selectedStaff.value = null;
}

function openStaff(staff) {
    selectedStaff.value = staff;
    activeStaffTab.value = operationMode.value === 'wages'
        ? 'wages'
        : operationMode.value === 'parts'
          ? 'parts'
          : operationMode.value === 'collect'
            ? 'collections'
            : 'job_cards';
}

function openHistory(card) {
    selectedHistoryCard.value = card;
}

function openPayment(card) {
    paymentTarget.value = card;
    paymentForm.clearErrors();
    paymentForm.paid_on = today();
    paymentForm.amount = Math.max(0, Number(card.wage_balance || 0)).toFixed(2);
    paymentForm.method = props.paymentMethods?.[0]?.value ?? '';
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

function openParts(card) {
    partTarget.value = card;
    partMovementForm.clearErrors();
    partMovementForm.type = 'issue';
    partMovementForm.part_id = card.parts?.[0]?.part_id ?? '';
    partMovementForm.quantity = '';
    partMovementForm.notes = '';
}

function submitPartMovement() {
    partMovementForm.post(route('job-cards.part-movements.store', partTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            partMovementForm.quantity = '';
            partMovementForm.notes = '';
        },
    });
}

function openCollect(card) {
    collectTarget.value = card;
    collectForm.clearErrors();
    collectForm.quantity_received = card.pending_quantity;
    collectForm.quantity_damaged = 0;
    collectForm.wage_paid_amount = 0;
    collectForm.started_at = card.started_at_input ?? '';
    collectForm.completed_at = nowInput();
    collectForm.notes = '';
}

function submitCollection() {
    collectForm.post(route('job-cards.complete', collectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            collectTarget.value = null;
            collectForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="cards.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                    <Link v-for="(card, i) in cards" :key="i" :href="route(card.to)" class="rounded-lg border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" :class="toneClass(card.tone)">
                        <div class="text-xs uppercase tracking-wide opacity-70">{{ card.label }}</div>
                        <div class="mt-1 text-2xl font-semibold">{{ card.value }}</div>
                    </Link>
                </div>

                <section v-if="hasAny('admin', 'production_manager')" class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Quick operations</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Start with the work type, pick the staff member, then finish the action in the next slide.</p>
                        </div>
                        <Link :href="route('work-orders.index')" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-800">
                            <AppIcon name="clipboard" /> Work orders
                        </Link>
                    </div>
                    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                        <button v-for="operation in operationCards" :key="operation.mode" type="button" class="operation-card" @click="openOperation(operation.mode)">
                            <span class="operation-icon"><AppIcon :name="operation.icon" /></span>
                            <span class="block">
                                <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ operation.title }}</span>
                                <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ operation.description }}</span>
                            </span>
                            <span class="ml-auto rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">{{ operation.count }}</span>
                        </button>
                    </div>
                </section>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="alert-panel">
                        <div class="alert-title"><AppIcon name="alert" /> Raw material alerts</div>
                        <div class="space-y-2 p-4">
                            <div v-for="material in lowStockMaterials" :key="material.id" class="alert-row">
                                <div>
                                    <div class="font-semibold">{{ material.name }}</div>
                                    <div class="text-xs opacity-70">Alert at {{ quantity(material.alert_quantity) }} {{ material.unit }}</div>
                                </div>
                                <div class="text-right">
                                    <div>{{ quantity(material.current_quantity) }} {{ material.unit }}</div>
                                    <div class="text-xs font-semibold text-red-600 dark:text-red-300">Short {{ quantity(material.short_by) }}</div>
                                </div>
                            </div>
                            <div v-if="!lowStockMaterials?.length" class="empty-alert">No raw material stock alerts.</div>
                        </div>
                    </section>

                    <section class="alert-panel">
                        <div class="alert-title"><AppIcon name="puzzle" /> Part alerts</div>
                        <div class="space-y-2 p-4">
                            <div v-for="row in lowPartStock" :key="`${row.source}-${row.product_variant_id}-${row.part_id}`" class="alert-row">
                                <div>
                                    <div class="font-semibold">{{ row.label }}</div>
                                    <div class="text-xs opacity-70">{{ row.part }} / {{ row.source }}</div>
                                </div>
                                <div class="text-right">
                                    <div>{{ quantity(row.available) }} / {{ quantity(row.needed) }}</div>
                                    <div class="text-xs font-semibold text-red-600 dark:text-red-300">Short {{ quantity(row.short_by) }}</div>
                                </div>
                            </div>
                            <div v-if="!lowPartStock?.length" class="empty-alert">No part stock alerts.</div>
                        </div>
                    </section>

                    <section class="alert-panel">
                        <div class="alert-title"><AppIcon name="package" /> Product alerts</div>
                        <div class="space-y-2 p-4">
                            <div v-for="row in productStockAlerts" :key="row.id" class="alert-row">
                                <div>
                                    <div class="font-semibold">{{ row.label }}</div>
                                    <div class="text-xs opacity-70">Alert at {{ quantity(row.alert) }}</div>
                                </div>
                                <div class="text-right">
                                    <div>{{ quantity(row.current) }}</div>
                                    <div class="text-xs font-semibold text-red-600 dark:text-red-300">Short {{ quantity(row.short_by) }}</div>
                                </div>
                            </div>
                            <div v-if="!productStockAlerts?.length" class="empty-alert">No product stock alerts.</div>
                        </div>
                    </section>
                </div>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Part stock snapshot</h3>
                        <Link :href="route('part-stock.index')" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-100 dark:hover:bg-gray-800">
                            <AppIcon name="eye" /> View all
                        </Link>
                    </div>
                    <div class="p-4">
                        <TableControls :table="partStockTable" placeholder="Search part stock...">
                            <div class="table-scroll">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                    <thead class="bg-gray-50 dark:bg-gray-950">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Product</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Part</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Current</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Alert</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                        <tr v-for="row in partStockTable.rows.value" :key="`${row.label}-${row.part}`" class="hover:bg-gray-50 dark:hover:bg-gray-950">
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ row.label }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">{{ row.part }}</td>
                                            <td class="px-6 py-4 text-right text-sm font-semibold" :class="row.is_alert ? 'text-amber-600 dark:text-amber-300' : 'text-gray-900 dark:text-gray-100'">{{ quantity(row.quantity) }}</td>
                                            <td class="px-6 py-4 text-right text-sm text-gray-600 dark:text-gray-300">{{ quantity(row.alert_quantity) }}</td>
                                            <td class="px-6 py-4 text-sm">
                                                <span v-if="row.is_alert" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200">Alert</span>
                                                <span v-else class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">OK</span>
                                            </td>
                                        </tr>
                                        <tr v-if="partStockTable.rows.value.length === 0">
                                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No part stock found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </TableControls>
                    </div>
                </section>
            </div>
        </div>

        <Modal :show="staffListOpen" max-width="lg" @close="closeOperation">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ operationMeta?.title ?? 'Staff' }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ operationMeta?.description }}</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">{{ filteredStaffWorkflows.length }} staff</span>
                </div>

                <div class="mt-6 space-y-3">
                    <button v-for="staff in filteredStaffWorkflows" :key="staff.id" type="button" class="staff-row" @click="openStaff(staff)">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                            {{ staff.name.slice(0, 2).toUpperCase() }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ staff.name }}</span>
                            <span class="mt-1 block truncate text-sm text-gray-500 dark:text-gray-400">{{ staff.designation || 'No designation' }} / {{ staff.salary_type?.replace('_', ' ') }}</span>
                        </span>
                        <span class="grid grid-cols-3 gap-2 text-center text-xs">
                            <span class="rounded-md bg-gray-50 px-2 py-1 dark:bg-gray-950"><strong class="block text-gray-900 dark:text-gray-100">{{ staff.open_cards_count }}</strong>Open</span>
                            <span class="rounded-md bg-amber-50 px-2 py-1 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"><strong class="block">{{ money(staff.pending_wage) }}</strong>Due</span>
                            <span class="rounded-md bg-red-50 px-2 py-1 text-red-700 dark:bg-red-950/40 dark:text-red-200"><strong class="block">{{ money(staff.overpaid_wage) }}</strong>Over</span>
                        </span>
                        <AppIcon name="chevron-right" />
                    </button>

                    <div v-if="!filteredStaffWorkflows.length" class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        No staff records for this operation.
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="!!selectedStaff" max-width="wide" @close="selectedStaff = null">
            <div class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ selectedStaff?.name }}</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedStaff?.designation || 'No designation' }} / {{ selectedStaff?.salary_type?.replace('_', ' ') }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-950"><strong class="block text-gray-900 dark:text-gray-100">{{ selectedStaff?.open_cards_count ?? 0 }}</strong>Open</div>
                        <div class="rounded-md bg-amber-50 px-3 py-2 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"><strong class="block">{{ money(selectedStaff?.pending_wage) }}</strong>Due</div>
                        <div class="rounded-md bg-red-50 px-3 py-2 text-red-700 dark:bg-red-950/40 dark:text-red-200"><strong class="block">{{ money(selectedStaff?.overpaid_wage) }}</strong>Over</div>
                    </div>
                </div>

                <div class="mt-5 flex gap-2 overflow-x-auto border-b border-gray-200 pb-2 dark:border-gray-800">
                    <button v-for="tab in staffTabs" :key="tab.value" type="button" class="tab-button" :class="{ 'tab-button-active': activeStaffTab === tab.value }" @click="activeStaffTab = tab.value">
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="activeStaffTab === 'job_cards'" class="mt-5 space-y-3">
                    <div v-for="card in selectedCards" :key="card.id" class="job-card-row">
                        <div>
                            <Link :href="route('work-orders.show', card.work_order_id)" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-300">{{ card.work_order_code }}</Link>
                            <div class="mt-1 text-gray-900 dark:text-gray-100">{{ card.product }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ card.stage_label }} / Issued {{ card.quantity_issued }} / Received {{ card.quantity_received }} / Damaged {{ card.quantity_damaged }} / Pending {{ card.pending_quantity }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="dash-action" :disabled="card.wage_balance <= 0" @click="openPayment(card)"><AppIcon name="cash" /> Pay</button>
                            <button type="button" class="dash-action" @click="openParts(card)"><AppIcon name="puzzle" /> Parts</button>
                            <button type="button" class="dash-action" :disabled="card.pending_quantity <= 0" @click="openCollect(card)"><AppIcon name="check" /> Collect</button>
                        </div>
                    </div>
                </div>

                <div v-if="activeStaffTab === 'parts'" class="mt-5 space-y-3">
                    <div v-for="card in cardsWithParts" :key="card.id" class="job-card-row">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ card.work_order_code }} / {{ card.product }}</div>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div v-for="part in card.parts" :key="part.part_id" class="part-chip">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ part.name }}</span>
                                    <span class="text-gray-600 dark:text-gray-300">{{ part.issued }} issued / {{ part.required }} required</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="dash-action" @click="openParts(card)"><AppIcon name="puzzle" /> Record part movement</button>
                    </div>
                </div>

                <div v-if="activeStaffTab === 'collections'" class="mt-5 space-y-3">
                    <div v-for="card in cardsToCollect" :key="card.id" class="job-card-row">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ card.work_order_code }} / {{ card.product }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ card.stage_label }} / {{ card.pending_quantity }} pieces pending</div>
                        </div>
                        <button type="button" class="dash-action" @click="openCollect(card)"><AppIcon name="check" /> Collect now</button>
                    </div>
                    <div v-if="!cardsToCollect.length" class="empty-state">No pending pieces to collect.</div>
                </div>

                <div v-if="activeStaffTab === 'wages'" class="mt-5 space-y-3">
                    <div v-for="card in cardsWithPendingWages" :key="card.id" class="job-card-row">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ card.work_order_code }} / {{ card.product }}</div>
                            <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">Wage {{ money(card.wage_amount) }} / Paid {{ money(card.wage_paid_amount) }} / Balance {{ money(card.wage_balance) }}</div>
                        </div>
                        <button type="button" class="dash-action" @click="openPayment(card)"><AppIcon name="cash" /> Pay now</button>
                    </div>
                    <div v-if="!cardsWithPendingWages.length" class="empty-state">No pending wage balance.</div>
                </div>

                <div v-if="activeStaffTab === 'history'" class="mt-5">
                    <TableControls :table="historyTable" placeholder="Search staff history...">
                        <div class="table-scroll">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Work order</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Product</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Stage</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Issued</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Received</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Balance</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                    <tr
                                        v-for="card in historyTable.rows.value"
                                        :key="card.id"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-950"
                                        :class="{ 'bg-indigo-50/60 dark:bg-indigo-950/30': selectedHistoryCard?.id === card.id }"
                                    >
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ card.work_order_code }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ card.product }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ card.stage_label }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ card.quantity_issued }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-600 dark:text-gray-300">{{ card.quantity_received }}</td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold" :class="card.wage_balance > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-gray-900 dark:text-gray-100'">{{ money(card.wage_balance) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" class="dash-action" @click="openHistory(card)"><AppIcon name="eye" /> View</button>
                                        </td>
                                    </tr>
                                    <tr v-if="historyTable.rows.value.length === 0">
                                        <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No staff history found.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </TableControls>

                    <div v-if="selectedHistoryCard" class="mt-5 rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ selectedHistoryCard.work_order_code }} / {{ selectedHistoryCard.product }}</div>
                                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ selectedHistoryCard.stage_label }} / Issued {{ selectedHistoryCard.quantity_issued }} / Received {{ selectedHistoryCard.quantity_received }} / Damaged {{ selectedHistoryCard.quantity_damaged }} / Pending {{ selectedHistoryCard.pending_quantity }}
                                </div>
                            </div>
                            <Link :href="route('work-orders.show', selectedHistoryCard.work_order_id)" class="dash-action"><AppIcon name="eye" /> Open work order</Link>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-3">
                            <section class="history-box">
                                <div class="history-title">Collections</div>
                                <div v-for="receipt in selectedHistoryCard.receipts" :key="receipt.id" class="history-line">
                                    {{ receipt.received_at }} / Good {{ receipt.quantity_received }} / Damaged {{ receipt.quantity_damaged }} / {{ durationText(receipt.duration_minutes) }}
                                </div>
                                <div v-if="!selectedHistoryCard.receipts.length" class="history-empty">No collections.</div>
                            </section>
                            <section class="history-box">
                                <div class="history-title">Payments</div>
                                <div v-for="payment in selectedHistoryCard.payments" :key="payment.id" class="history-line">
                                    {{ payment.paid_on }} / {{ money(payment.amount) }} / {{ payment.method || '-' }}
                                </div>
                                <div v-if="!selectedHistoryCard.payments.length" class="history-empty">No payments.</div>
                            </section>
                            <section class="history-box">
                                <div class="history-title">Part movements</div>
                                <div v-for="movement in selectedHistoryCard.part_movements" :key="movement.id" class="history-line">
                                    {{ movement.created_at }} / {{ movement.part }} / {{ movement.quantity }} / {{ partTypeLabel(movement.type) }}
                                </div>
                                <div v-if="!selectedHistoryCard.part_movements.length" class="history-empty">No part movement.</div>
                            </section>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        Select a history row to view receipts, payments, and part movements.
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="selectedStaff = null">Back to staff list</SecondaryButton>
                </div>
            </div>
        </Modal>

        <Modal :show="!!paymentTarget" @close="paymentTarget = null">
            <form class="p-6" @submit.prevent="submitPayment">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Pay Wage</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ paymentTarget?.work_order_code }} / {{ paymentTarget?.product }} / Balance {{ money(paymentTarget?.wage_balance) }}</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Paid on" />
                        <DatePicker v-model="paymentForm.paid_on" class="mt-1" />
                        <InputError :message="paymentForm.errors.paid_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Amount" />
                        <TextInput v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="paymentForm.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Method" />
                        <SearchableSelect v-model="paymentForm.method" :options="paymentMethodOptions" placeholder="Search method..." class="mt-1" />
                        <InputError :message="paymentForm.errors.method" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Reference" />
                        <TextInput v-model="paymentForm.reference" class="mt-1 block w-full" />
                        <InputError :message="paymentForm.errors.reference" class="mt-1" />
                    </div>
                </div>
                <div class="mt-4">
                    <InputLabel value="Notes" />
                    <textarea v-model="paymentForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="paymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing" :class="{ 'opacity-50': paymentForm.processing }">Save payment</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!partTarget" max-width="wide" @close="partTarget = null">
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Part Movement</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ partTarget?.work_order_code }} / {{ partTarget?.product }}</p>
                <form class="mt-5 rounded-lg border border-gray-200 p-4 dark:border-gray-800" @submit.prevent="submitPartMovement">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel value="Movement" />
                            <SearchableSelect v-model="partMovementForm.type" :options="partMovementTypes" placeholder="Search movement..." class="mt-1" />
                            <InputError :message="partMovementForm.errors.type" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Part" />
                            <SearchableSelect v-model="partMovementForm.part_id" :options="cardPartOptions" placeholder="Search part..." class="mt-1" />
                            <InputError :message="partMovementForm.errors.part_id" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Quantity" />
                            <TextInput v-model="partMovementForm.quantity" type="number" min="1" step="1" class="mt-1 block w-full" />
                            <InputError :message="partMovementForm.errors.quantity" class="mt-1" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Notes" />
                        <TextInput v-model="partMovementForm.notes" class="mt-1 block w-full" />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <PrimaryButton :disabled="partMovementForm.processing" :class="{ 'opacity-50': partMovementForm.processing }">Record movement</PrimaryButton>
                    </div>
                </form>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div v-for="movement in partTarget?.part_movements ?? []" :key="movement.id" class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-800">
                        <div class="flex justify-between gap-3">
                            <div>
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ movement.part }}</div>
                                <div class="text-gray-500 dark:text-gray-400">{{ partTypeLabel(movement.type) }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-gray-900 dark:text-gray-100">{{ movement.quantity }}</div>
                                <div class="text-gray-500 dark:text-gray-400">{{ movement.created_at }}</div>
                            </div>
                        </div>
                        <div v-if="movement.notes" class="mt-2 text-gray-500 dark:text-gray-400">{{ movement.notes }}</div>
                    </div>
                    <div v-if="!(partTarget?.part_movements ?? []).length" class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">
                        No part movements yet.
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="!!collectTarget" @close="collectTarget = null">
            <form class="p-6" @submit.prevent="submitCollection">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Collect Finished Work</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ collectTarget?.work_order_code }} / {{ collectTarget?.stage_label }} / {{ collectTarget?.pending_quantity }} pending</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel value="Good pieces received" />
                        <TextInput v-model="collectForm.quantity_received" type="number" min="0" :max="collectTarget?.pending_quantity" step="1" class="mt-1 block w-full" />
                        <InputError :message="collectForm.errors.quantity_received" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Damaged pieces" />
                        <TextInput v-model="collectForm.quantity_damaged" type="number" min="0" :max="collectTarget?.pending_quantity" step="1" class="mt-1 block w-full" />
                        <InputError :message="collectForm.errors.quantity_damaged" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Started at" />
                        <DatePicker v-model="collectForm.started_at" mode="datetime" class="mt-1" />
                        <InputError :message="collectForm.errors.started_at" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Finished / received at" />
                        <DatePicker v-model="collectForm.completed_at" mode="datetime" class="mt-1" />
                        <InputError :message="collectForm.errors.completed_at" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Wage paid now" />
                        <TextInput v-model="collectForm.wage_paid_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="collectForm.errors.wage_paid_amount" class="mt-1" />
                    </div>
                </div>
                <div class="mt-4">
                    <InputLabel value="Notes" />
                    <textarea v-model="collectForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="collectTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="collectForm.processing" :class="{ 'opacity-50': collectForm.processing }">Save collection</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.operation-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    border-radius: 0.5rem;
    border: 1px solid rgb(229 231 235);
    padding: 1rem;
    text-align: left;
    transition: border-color 150ms ease, background-color 150ms ease, transform 150ms ease;
}
.operation-card:hover {
    border-color: rgb(129 140 248);
    background: rgb(238 242 255 / 0.55);
    transform: translateY(-1px);
}
:global(.dark) .operation-card {
    border-color: rgb(31 41 55);
}
:global(.dark) .operation-card:hover {
    border-color: rgb(67 56 202);
    background: rgb(30 27 75 / 0.28);
}
.operation-icon {
    display: inline-flex;
    height: 2.5rem;
    width: 2.5rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background: rgb(238 242 255);
    color: rgb(67 56 202);
}
:global(.dark) .operation-icon {
    background: rgb(30 27 75);
    color: rgb(199 210 254);
}
.staff-row,
.job-card-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    width: 100%;
    border-radius: 0.5rem;
    border: 1px solid rgb(229 231 235);
    padding: 1rem;
    text-align: left;
}
.staff-row:hover {
    border-color: rgb(129 140 248);
    background: rgb(238 242 255 / 0.55);
}
:global(.dark) .staff-row,
:global(.dark) .job-card-row {
    border-color: rgb(31 41 55);
}
:global(.dark) .staff-row:hover {
    border-color: rgb(67 56 202);
    background: rgb(30 27 75 / 0.28);
}
.job-card-row {
    align-items: flex-start;
}
.part-chip {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(229 231 235);
    background: rgb(249 250 251);
    padding: 0.55rem 0.75rem;
    font-size: 0.875rem;
}
:global(.dark) .part-chip {
    border-color: rgb(31 41 55);
    background: rgb(3 7 18);
}
.tab-button {
    white-space: nowrap;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: rgb(75 85 99);
}
.tab-button:hover {
    background: rgb(243 244 246);
}
.tab-button-active {
    background: rgb(79 70 229);
    color: white;
}
:global(.dark) .tab-button {
    color: rgb(209 213 219);
}
:global(.dark) .tab-button:hover {
    background: rgb(31 41 55);
}
.dash-action {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(209 213 219);
    padding: 0.45rem 0.7rem;
    font-size: 0.8rem;
    font-weight: 700;
    color: rgb(55 65 81);
    transition: background-color 150ms ease, border-color 150ms ease;
}
.dash-action:hover:not(:disabled) {
    border-color: rgb(129 140 248);
    background: rgb(238 242 255);
}
.dash-action:disabled {
    cursor: not-allowed;
    opacity: 0.45;
}
:global(.dark) .dash-action {
    border-color: rgb(55 65 81);
    color: rgb(229 231 235);
}
:global(.dark) .dash-action:hover:not(:disabled) {
    border-color: rgb(99 102 241);
    background: rgb(30 27 75 / 0.5);
}
.history-title {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    color: rgb(107 114 128);
}
.history-box {
    border-radius: 0.5rem;
    border: 1px solid rgb(229 231 235);
    padding: 1rem;
}
:global(.dark) .history-box {
    border-color: rgb(31 41 55);
}
.history-line {
    margin-top: 0.35rem;
    font-size: 0.8rem;
    color: rgb(75 85 99);
}
.history-empty,
.empty-state {
    margin-top: 0.5rem;
    color: rgb(156 163 175);
    font-size: 0.875rem;
}
:global(.dark) .history-line {
    color: rgb(209 213 219);
}
.alert-panel {
    overflow: hidden;
    border-radius: 0.5rem;
    border: 1px solid rgb(252 211 77);
    background: rgb(255 251 235);
    box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
}
:global(.dark) .alert-panel {
    border-color: rgb(120 53 15 / 0.6);
    background: rgb(69 26 3 / 0.3);
}
.alert-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-bottom: 1px solid rgb(252 211 77);
    padding: 1rem 1.25rem;
    font-weight: 700;
    color: rgb(120 53 15);
}
:global(.dark) .alert-title {
    border-color: rgb(120 53 15 / 0.6);
    color: rgb(254 243 199);
}
.alert-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.5rem;
    background: rgb(255 255 255 / 0.7);
    padding: 0.8rem;
    color: rgb(120 53 15);
}
:global(.dark) .alert-row {
    background: rgb(17 24 39 / 0.5);
    color: rgb(254 243 199);
}
.empty-alert {
    border-radius: 0.5rem;
    border: 1px dashed rgb(252 211 77);
    padding: 2rem 1rem;
    text-align: center;
    font-size: 0.875rem;
    color: rgb(146 64 14);
}
:global(.dark) .empty-alert {
    border-color: rgb(120 53 15 / 0.6);
    color: rgb(254 243 199);
}
</style>
