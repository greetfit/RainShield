<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TableControls from '@/Components/TableControls.vue';
import TextInput from '@/Components/TextInput.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ sales: Array, customers: Array, products: Array, paymentMethods: Array });
const table = useTableControls(() => props.sales, ['invoice_no', 'customer', 'payment_status', 'payment_method', 'sold_at']);
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const paymentMethodOptions = computed(() => props.paymentMethods.map((method) => ({
    value: method.value,
    label: method.label,
})));
const nowInput = () => {
    const date = new Date();
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
};

const showForm = ref(false);
const form = useForm({
    customer_id: '',
    sold_at: nowInput(),
    payment_method: 'cash',
    payment_reference: '',
    discount: 0,
    tax: 0,
    shipping: 0,
    paid: 0,
    notes: '',
    items: [],
});

const subtotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));
const total = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0) + Number(form.tax || 0) + Number(form.shipping || 0)));

function addRow(product = null) {
    form.items.push({
        product_variant_id: product?.value ?? '',
        label: product?.label ?? '',
        stock: product?.stock ?? 0,
        quantity: 1,
        unit_price: product?.price ?? 0,
    });
}

function onProductChange(index) {
    const product = props.products.find((item) => String(item.value) === String(form.items[index].product_variant_id));
    form.items[index].label = product?.label ?? '';
    form.items[index].stock = product?.stock ?? 0;
    form.items[index].unit_price = product?.price ?? 0;
}

function openCreate() {
    form.reset();
    form.clearErrors();
    form.sold_at = nowInput();
    form.payment_method = props.paymentMethods?.[0]?.value ?? 'cash';
    form.items = [];
    addRow();
    showForm.value = true;
}

function submit() {
    form.transform((data) => ({
        ...data,
        paid: Number(data.paid || 0),
        discount: Number(data.discount || 0),
        tax: Number(data.tax || 0),
        shipping: Number(data.shipping || 0),
        print_mode: 'invoice',
        items: data.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            quantity: Number(item.quantity || 0),
            unit_price: Number(item.unit_price || 0),
        })),
    })).post(route('sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
}

const paymentTarget = ref(null);
const paymentsTarget = ref(null);
const editPaymentTarget = ref(null);
const deletePaymentTarget = ref(null);
const paymentForm = useForm({ paid_on: new Date().toISOString().slice(0, 10), amount: '', method: 'cash', reference: '', notes: '' });
const editPaymentForm = useForm({ paid_on: '', amount: '', method: '', reference: '', notes: '' });
const deletePaymentForm = useForm({});
function openPayment(sale) {
    paymentTarget.value = sale;
    paymentForm.clearErrors();
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.amount = Math.max(0, Number(sale.due || 0)).toFixed(2);
    paymentForm.method = sale.payment_method || 'cash';
    paymentForm.reference = '';
    paymentForm.notes = '';
}

function openPayments(sale) {
    paymentsTarget.value = sale;
}

function openEditPayment(payment) {
    editPaymentTarget.value = payment;
    editPaymentForm.clearErrors();
    editPaymentForm.paid_on = payment.paid_on_input ?? '';
    editPaymentForm.amount = payment.amount;
    editPaymentForm.method = payment.method ?? 'cash';
    editPaymentForm.reference = payment.reference ?? '';
    editPaymentForm.notes = payment.notes ?? '';
}

function submitPayment() {
    paymentForm.post(route('sales.payments.store', paymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentTarget.value = null;
            paymentForm.reset();
        },
    });
}

function submitEditPayment() {
    editPaymentForm.put(route('sale-payments.update', editPaymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editPaymentTarget.value = null;
            paymentsTarget.value = null;
            editPaymentForm.reset();
        },
    });
}

function deletePayment() {
    deletePaymentForm.delete(route('sale-payments.destroy', deletePaymentTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletePaymentTarget.value = null;
            paymentsTarget.value = null;
        },
    });
}

function voidSale(sale) {
    if (confirm(`Void ${sale.invoice_no}? Stock will be restored.`)) {
        router.post(route('sales.void', sale.id), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Sales Invoices" />
    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold leading-tight text-gray-800">Sales Invoices</h2></template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap justify-end gap-2">
                    <Link :href="route('sales.pos')"><SecondaryButton>POS</SecondaryButton></Link>
                    <PrimaryButton @click="openCreate">+ New Invoice</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search sales invoices...">
                    <div class="table-scroll">
                        <table class="min-w-[980px] divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Invoice</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Customer</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Items</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Total</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Paid</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Due</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="sale in table.rows.value" :key="sale.id">
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ sale.invoice_no }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ sale.sold_at }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700">{{ sale.customer }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-500">{{ sale.items_count }}</td>
                                    <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900">{{ money(sale.total) }}</td>
                                    <td class="px-6 py-4 text-right text-sm text-emerald-600">{{ money(sale.paid) }}</td>
                                    <td class="px-6 py-4 text-right text-sm" :class="sale.due > 0 ? 'text-red-600' : 'text-gray-500'">{{ money(sale.due) }}</td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="sale.status === 'void' ? 'bg-red-100 text-red-700' : sale.payment_status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                            {{ sale.status === 'void' ? 'void' : sale.payment_status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="eye" :href="route('sales.show', sale.id)">View</ActionMenuItem>
                                            <ActionMenuItem icon="print" :href="route('sales.print', sale.id)">Print invoice</ActionMenuItem>
                                            <ActionMenuItem icon="receipt" :href="route('sales.receipt', sale.id)">Thermal receipt</ActionMenuItem>
                                            <ActionMenuItem v-if="sale.due > 0 && sale.status !== 'void'" icon="cash" @click="openPayment(sale)">Add payment</ActionMenuItem>
                                            <ActionMenuItem icon="cash" @click="openPayments(sale)">View payments</ActionMenuItem>
                                            <ActionMenuItem v-if="sale.status !== 'void'" icon="undo" :href="route('sale-returns.create', sale.id)">Sales return</ActionMenuItem>
                                            <ActionMenuItem v-if="sale.status !== 'void'" icon="x" danger @click="voidSale(sale)">Void</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">No sales invoices yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <div v-if="showForm" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="showForm = false">
            <form class="h-full w-full overflow-y-auto rounded-l-2xl bg-white p-6 shadow-xl dark:bg-gray-900 md:w-2/3" @submit.prevent="submit">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">New Sales Invoice</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Customer</label>
                        <SearchableSelect v-model="form.customer_id" :options="customers" placeholder="Walk-in customer" class="mt-1" />
                        <InputError :message="form.errors.customer_id" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Date</label>
                        <DatePicker v-model="form.sold_at" mode="datetime" class="mt-1" />
                        <InputError :message="form.errors.sold_at" class="mt-1" />
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">Items</h3>
                        <SecondaryButton type="button" @click="addRow()">+ Add Row</SecondaryButton>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[860px] divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Stock</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Total</th>
                                    <th class="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(item, index) in form.items" :key="index">
                                    <td class="px-4 py-3"><SearchableSelect v-model="item.product_variant_id" :options="products" placeholder="Search finished good..." @update:model-value="onProductChange(index)" /></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-500">{{ item.stock }}</td>
                                    <td class="px-4 py-3"><TextInput v-model="item.quantity" type="number" min="1" :max="item.stock" step="1" class="ml-auto block w-24 text-right" /></td>
                                    <td class="px-4 py-3"><TextInput v-model="item.unit_price" type="number" min="0" step="0.01" class="ml-auto block w-28 text-right" /></td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold">{{ money(Number(item.quantity || 0) * Number(item.unit_price || 0)) }}</td>
                                    <td class="px-4 py-3 text-right"><button type="button" class="text-sm text-red-600" @click="form.items.splice(index, 1)">Remove</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError :message="form.errors.items" class="p-4" />
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Notes</label>
                        <textarea v-model="form.notes" rows="4" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></textarea>
                    </div>
                    <div class="space-y-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                        <div class="flex justify-between text-sm"><span>Subtotal</span><strong>{{ money(subtotal) }}</strong></div>
                        <div class="flex items-center justify-between text-sm"><span>Discount</span><TextInput v-model="form.discount" type="number" min="0" step="0.01" class="w-32 text-right" /></div>
                        <div class="flex items-center justify-between text-sm"><span>Tax</span><TextInput v-model="form.tax" type="number" min="0" step="0.01" class="w-32 text-right" /></div>
                        <div class="flex items-center justify-between text-sm"><span>Shipping</span><TextInput v-model="form.shipping" type="number" min="0" step="0.01" class="w-32 text-right" /></div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-base font-semibold dark:border-gray-700"><span>Total</span><span>{{ money(total) }}</span></div>
                        <div class="grid grid-cols-2 gap-3">
                            <SearchableSelect v-model="form.payment_method" :options="paymentMethodOptions" placeholder="Search method..." />
                            <TextInput v-model="form.paid" type="number" min="0" step="0.01" class="text-right" placeholder="Paid" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing || form.items.length === 0" :class="{ 'opacity-50': form.processing || form.items.length === 0 }">Save Invoice</PrimaryButton>
                </div>
            </form>
        </div>

        <div v-if="paymentTarget" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="paymentTarget = null">
            <form class="h-full w-full overflow-y-auto rounded-l-2xl bg-white p-6 shadow-xl dark:bg-gray-900 sm:w-[420px]" @submit.prevent="submitPayment">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Add Payment</h2>
                <p class="mt-1 text-sm text-gray-500">{{ paymentTarget.invoice_no }} due {{ money(paymentTarget.due) }}</p>
                <div class="mt-5 space-y-4">
                    <DatePicker v-model="paymentForm.paid_on" />
                    <TextInput v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="block w-full" />
                    <SearchableSelect v-model="paymentForm.method" :options="paymentMethodOptions" placeholder="Search method..." />
                    <TextInput v-model="paymentForm.reference" class="block w-full" placeholder="Reference" />
                    <InputError :message="paymentForm.errors.amount" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="paymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="paymentForm.processing">Save</PrimaryButton>
                </div>
            </form>
        </div>

        <div v-if="paymentsTarget" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="paymentsTarget = null">
            <div class="h-full w-full overflow-y-auto rounded-l-2xl bg-white p-6 shadow-xl dark:bg-gray-900 sm:w-[680px]">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Payment History</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ paymentsTarget.invoice_no }} /
                    Total {{ money(paymentsTarget.total) }} /
                    Paid {{ money(paymentsTarget.paid) }} /
                    Balance {{ money(paymentsTarget.due) }}
                </p>

                <div class="mt-5 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Paid on</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Reference</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="payment in paymentsTarget.payments" :key="payment.id">
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ payment.paid_on }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ payment.method || '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ payment.reference || '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(payment.amount) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" title="Edit payment" @click="openEditPayment(payment)">
                                            <AppIcon name="edit" />
                                        </button>
                                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950" title="Delete payment" @click="deletePaymentTarget = payment">
                                            <AppIcon name="trash" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!paymentsTarget.payments.length">
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No payments recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton type="button" @click="paymentsTarget = null">Close</SecondaryButton>
                </div>
            </div>
        </div>

        <div v-if="editPaymentTarget" class="fixed inset-0 z-[60] flex justify-end bg-black/40" @click.self="editPaymentTarget = null">
            <form class="h-full w-full overflow-y-auto rounded-l-2xl bg-white p-6 shadow-xl dark:bg-gray-900 sm:w-[420px]" @submit.prevent="submitEditPayment">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit Payment</h2>
                <p class="mt-1 text-sm text-gray-500">{{ paymentsTarget?.invoice_no }}</p>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Paid on</label>
                        <DatePicker v-model="editPaymentForm.paid_on" class="mt-1" />
                        <InputError :message="editPaymentForm.errors.paid_on" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Amount</label>
                        <TextInput v-model="editPaymentForm.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full" />
                        <InputError :message="editPaymentForm.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Method</label>
                        <SearchableSelect v-model="editPaymentForm.method" :options="paymentMethodOptions" placeholder="Search method..." class="mt-1" />
                        <InputError :message="editPaymentForm.errors.method" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Reference</label>
                        <TextInput v-model="editPaymentForm.reference" class="mt-1 block w-full" />
                        <InputError :message="editPaymentForm.errors.reference" class="mt-1" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Notes</label>
                        <textarea v-model="editPaymentForm.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"></textarea>
                        <InputError :message="editPaymentForm.errors.notes" class="mt-1" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="editPaymentTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="editPaymentForm.processing" :class="{ 'opacity-50': editPaymentForm.processing }">Save</PrimaryButton>
                </div>
            </form>
        </div>

        <ConfirmModal
            :show="!!deletePaymentTarget"
            title="Delete payment?"
            :message="`This will remove the payment of ${money(deletePaymentTarget?.amount ?? 0)} and update the invoice balance.`"
            :processing="deletePaymentForm.processing"
            @cancel="deletePaymentTarget = null"
            @confirm="deletePayment"
        />
    </AuthenticatedLayout>
</template>
