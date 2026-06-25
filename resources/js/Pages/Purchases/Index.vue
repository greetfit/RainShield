<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PurchaseForm from './Partials/PurchaseForm.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ purchases: Array, materialOptions: Array, finishedProductOptions: Array, supplierOptions: Array, statusOptions: Array, paymentMethods: Array, today: String });

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const table = useTableControls(() => props.purchases, ['purchased_on', 'reference', 'supplier_name', 'status_label', 'grand_total', 'due_amount']);
const paymentMethodOptions = computed(() => props.paymentMethods.map((method) => ({
    value: method.value,
    label: method.label,
})));
const statusClass = (status) => ({
    placed: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    partially_received: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    received: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    cancelled: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
}[status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200');

const paymentTarget = ref(null);
const paymentsTarget = ref(null);
const viewTarget = ref(null);
const statusTarget = ref(null);
const showPurchaseForm = ref(false);
const editingPurchase = ref(null);
const paymentForm = useForm({ paid_on: new Date().toISOString().slice(0, 10), amount: '', method: '', reference: '', notes: '' });
const statusForm = useForm({ status: 'received' });
const purchaseForm = useForm({
    reference: '',
    supplier_name: '',
    purchased_on: props.today,
    status: 'received',
    transport_charge: 0,
    allocation_method: 'value',
    notes: '',
    items: [{ id: null, item_type: 'raw_material', raw_material_variant_id: '', product_variant_id: '', quantity: '', unit_price: '' }],
});

function resetPurchaseForm() {
    purchaseForm.clearErrors();
    purchaseForm.reference = '';
    purchaseForm.supplier_name = '';
    purchaseForm.purchased_on = props.today;
    purchaseForm.status = 'received';
    purchaseForm.transport_charge = 0;
    purchaseForm.allocation_method = 'value';
    purchaseForm.notes = '';
    purchaseForm.items = [{ id: null, item_type: 'raw_material', raw_material_variant_id: '', product_variant_id: '', quantity: '', unit_price: '' }];
}

function openCreate() {
    editingPurchase.value = null;
    resetPurchaseForm();
    showPurchaseForm.value = true;
}

function openEdit(purchase) {
    editingPurchase.value = purchase;
    purchaseForm.clearErrors();
    purchaseForm.reference = purchase.reference ?? '';
    purchaseForm.supplier_name = purchase.supplier_name ?? '';
    purchaseForm.purchased_on = purchase.purchased_on_input;
    purchaseForm.status = purchase.status ?? 'received';
    purchaseForm.transport_charge = purchase.transport_charge;
    purchaseForm.allocation_method = purchase.allocation_method;
    purchaseForm.notes = purchase.notes ?? '';
    purchaseForm.items = purchase.items.map((item) => ({
        id: item.id,
        item_type: item.item_type ?? 'raw_material',
        raw_material_variant_id: item.raw_material_variant_id,
        product_variant_id: item.product_variant_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
    }));
    showPurchaseForm.value = true;
}

function closePurchaseForm() {
    showPurchaseForm.value = false;
}

function submitPurchase() {
    const data = (payload) => ({
        ...payload,
        items: payload.items.filter((row) => {
            const selectedItem = (row.item_type ?? 'raw_material') === 'finished_good'
                ? row.product_variant_id
                : row.raw_material_variant_id;

            return selectedItem && row.quantity;
        }),
    });

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            closePurchaseForm();
            resetPurchaseForm();
        },
    };

    if (editingPurchase.value) {
        purchaseForm.transform(data).put(route('purchases.update', editingPurchase.value.id), opts);
    } else {
        purchaseForm.transform(data).post(route('purchases.store'), opts);
    }
}

function openPayment(purchase) {
    paymentTarget.value = purchase;
    paymentForm.clearErrors();
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.amount = purchase.due_amount;
    paymentForm.method = props.paymentMethods?.[0]?.value ?? 'cash';
    paymentForm.reference = '';
    paymentForm.notes = '';
}

function submitPayment() {
    paymentForm.post(route('purchases.payments.store', paymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentTarget.value = null;
            paymentForm.reset();
        },
    });
}

function openStatus(purchase) {
    statusTarget.value = purchase;
    statusForm.clearErrors();
    statusForm.status = purchase.status ?? 'received';
}

function submitStatus() {
    statusForm.patch(route('purchases.status.update', statusTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (statusTarget.value = null),
    });
}
</script>

<template>
    <Head title="Purchases" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Purchases</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <PrimaryButton @click="openCreate">+ New Purchase</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search purchases...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Items</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Transport</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Due</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="p in table.rows.value" :key="p.id">
                                <td class="px-6 py-4 text-sm text-gray-700">{{ p.purchased_on }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="font-medium text-gray-900">{{ p.reference || '—' }}</div>
                                    <span
                                        v-if="p.has_return"
                                        class="mt-1 inline-flex rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-rose-700 dark:bg-rose-950/40 dark:text-rose-200"
                                    >
                                        Return
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ p.supplier_name || '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(p.status)">
                                        {{ p.status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ p.items_count }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ money(p.transport_charge) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(p.grand_total) }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium" :class="p.is_due ? 'text-red-600' : 'text-emerald-600'">
                                    {{ money(p.due_amount) }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <ActionMenu>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="viewTarget = p">
                                            <AppIcon name="user" /> View
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="openEdit(p)">
                                            <AppIcon name="edit" /> Edit
                                        </button>
                                        <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click="openStatus(p)">
                                            <AppIcon name="check" /> Update status
                                        </button>
                                        <button
                                            v-if="p.is_due"
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                            @click="openPayment(p)"
                                        >
                                            <AppIcon name="cash" /> Add payment
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                            @click="paymentsTarget = p"
                                        >
                                            <AppIcon name="cash" /> View payments
                                        </button>
                                        <Link :href="route('purchase-returns.create', p.id)" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                                            <AppIcon name="undo" /> Purchase return
                                        </Link>
                                    </ActionMenu>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">
                                    No purchases yet. Record your first raw-material purchase.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="!!paymentTarget" @close="paymentTarget = null">
            <form class="p-6" @submit.prevent="submitPayment">
                <h2 class="text-lg font-medium text-gray-900">Add payment</h2>
                <p class="mt-1 text-sm text-gray-500">Due: {{ money(paymentTarget?.due_amount ?? 0) }}</p>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="paid_on" value="Date" />
                        <DatePicker id="paid_on" v-model="paymentForm.paid_on" class="mt-1" />
                        <InputError :message="paymentForm.errors.paid_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="amount" value="Amount" />
                        <TextInput id="amount" v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="paymentForm.errors.amount" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="method" value="Method" />
                        <SearchableSelect id="method" v-model="paymentForm.method" :options="paymentMethodOptions" placeholder="Search method..." class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="reference" value="Reference" />
                        <TextInput id="reference" v-model="paymentForm.reference" class="mt-1 block w-full" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="paymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing" :class="{ 'opacity-50': paymentForm.processing }">Save payment</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!statusTarget" @close="statusTarget = null">
            <form class="p-6" @submit.prevent="submitStatus">
                <h2 class="text-lg font-medium text-gray-900">Update status</h2>
                <p class="mt-1 text-sm text-gray-500">{{ statusTarget?.reference || 'Purchase #' + statusTarget?.id }}</p>

                <div class="mt-4">
                    <InputLabel for="purchase_status_update" value="Status" />
                    <select
                        id="purchase_status_update"
                        v-model="statusForm.status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    >
                        <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                            {{ status.label }}
                        </option>
                    </select>
                    <InputError :message="statusForm.errors.status" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="statusTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="statusForm.processing" :class="{ 'opacity-50': statusForm.processing }">Save status</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="showPurchaseForm" max-width="wide" @close="closePurchaseForm">
            <PurchaseForm
                :form="purchaseForm"
                :material-options="materialOptions"
                :finished-product-options="finishedProductOptions"
                :supplier-options="supplierOptions"
                :status-options="statusOptions"
                :title="editingPurchase ? 'Edit Purchase' : 'New Purchase'"
                :submit-label="editingPurchase ? 'Save changes' : 'Save purchase'"
                @submit="submitPurchase"
                @cancel="closePurchaseForm"
            />
        </Modal>

        <Modal :show="!!viewTarget" max-width="2xl" @close="viewTarget = null">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-medium text-gray-900">{{ viewTarget?.reference || 'Purchase #' + viewTarget?.id }}</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ viewTarget?.supplier_name || '-' }}</p>
                    </div>
                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-900" @click="openEdit(viewTarget); viewTarget = null">Edit</button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div><div class="text-gray-500">Date</div><div class="font-medium text-gray-900">{{ viewTarget?.purchased_on }}</div></div>
                    <div>
                        <div class="text-gray-500">Status</div>
                        <div>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(viewTarget?.status)">
                                {{ viewTarget?.status_label || 'Received' }}
                            </span>
                        </div>
                    </div>
                    <div><div class="text-gray-500">Recorded by</div><div class="font-medium text-gray-900">{{ viewTarget?.created_by || '-' }}</div></div>
                    <div><div class="text-gray-500">Transport</div><div class="font-medium text-gray-900">{{ money(viewTarget?.transport_charge ?? 0) }}</div></div>
                    <div><div class="text-gray-500">Returned</div><div class="font-medium text-gray-900">{{ money(viewTarget?.returned_total ?? 0) }}</div></div>
                    <div><div class="text-gray-500">Due</div><div class="font-medium text-gray-900">{{ money(viewTarget?.due_amount ?? 0) }}</div></div>
                </div>

                <div class="mt-6 overflow-hidden rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Material</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit price</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in viewTarget?.items ?? []" :key="item.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.label }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ item.quantity }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-700">{{ money(item.unit_price) }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(item.line_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5 rounded-md bg-gray-50 p-4 text-sm">
                    <div class="flex justify-between"><span class="text-gray-500">Items total</span><span>{{ money(viewTarget?.items_total ?? 0) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Transport</span><span>{{ money(viewTarget?.transport_charge ?? 0) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Returned</span><span>-{{ money(viewTarget?.returned_total ?? 0) }}</span></div>
                    <div class="mt-1 flex justify-between border-t pt-1 font-semibold text-gray-900"><span>Net payable</span><span>{{ money(viewTarget?.net_total ?? viewTarget?.grand_total ?? 0) }}</span></div>
                </div>

                <p v-if="viewTarget?.notes" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-600">{{ viewTarget.notes }}</p>
            </div>
        </Modal>

        <Modal :show="!!paymentsTarget" @close="paymentsTarget = null">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Payments</h2>
                <div class="mt-4 overflow-hidden rounded-md border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="payment in paymentsTarget?.payments ?? []" :key="payment.id">
                                <td class="px-4 py-3 text-sm text-gray-700">{{ payment.paid_on }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ payment.method || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ payment.reference || '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ money(payment.amount) }}</td>
                            </tr>
                            <tr v-if="(paymentsTarget?.payments ?? []).length === 0">
                                <td class="px-4 py-6 text-center text-sm text-gray-500">No payments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="paymentsTarget = null">Close</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
