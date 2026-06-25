<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    customers: Array,
    products: Array,
    paymentMethods: Array,
    posSession: Object,
    posSummary: Object,
});
const page = usePage();
const money = (value) => Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const marginLabel = (product) => product.profit_markup_type === 'flat'
    ? money(product.profit_markup_amount || 0)
    : `${Number(product.profit_margin_percent || 0).toFixed(0)}%`;
const search = ref('');
const selectedCustomer = ref('');
const paymentMethod = ref(props.paymentMethods?.[0]?.value ?? 'cash');
const paid = ref(0);
const discount = ref(0);
const tax = ref(0);
const cart = ref([]);
const showCosting = ref(false);
const revealedProductCosts = ref(new Set());
const hideSidebar = ref(false);
const isFullscreen = ref(false);
const showCloseRegister = ref(false);
const appSettings = computed(() => page.props.appSettings ?? {});
const registerOpen = computed(() => Boolean(props.posSession));
const expectedCash = computed(() => Number(props.posSummary?.expected_cash ?? props.posSession?.opening_amount ?? 0));
const closeDifference = computed(() => Number(closeForm.closing_amount || 0) - expectedCash.value);
const paymentMethodOptions = computed(() => props.paymentMethods.map((method) => ({
    value: method.value,
    label: method.label,
})));

const visibleProducts = computed(() => {
    const term = search.value.trim().toLowerCase();
    const list = term
        ? props.products.filter((product) => `${product.label} ${product.sku || ''}`.toLowerCase().includes(term))
        : props.products;

    return list.slice(0, 48);
});
const subtotal = computed(() => cart.value.reduce((sum, item) => sum + item.quantity * item.unit_price, 0));
const grandTotal = computed(() => Math.max(0, subtotal.value - Number(discount.value || 0) + Number(tax.value || 0)));
const balance = computed(() => Number(paid.value || 0) - grandTotal.value);
const totalQty = computed(() => cart.value.reduce((sum, item) => sum + item.quantity, 0));
const selectedCustomerLabel = computed(() => props.customers.find((customer) => Number(customer.value) === Number(selectedCustomer.value))?.label ?? 'Walk-in customer');
const productCostVisible = (product) => showCosting.value || revealedProductCosts.value.has(Number(product.value));
const openForm = useForm({
    opening_amount: '',
    opening_notes: '',
});
const closeForm = useForm({
    closing_amount: '',
    closing_notes: '',
});

function openRegister() {
    openForm.post(route('sales.pos.open'), {
        preserveScroll: true,
        onSuccess: () => openForm.reset(),
    });
}

function closeRegister() {
    if (!props.posSession) return;

    closeForm.post(route('sales.pos.close', props.posSession.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeForm.reset();
            showCloseRegister.value = false;
        },
    });
}

function addProduct(product) {
    if (!registerOpen.value) return;

    const row = cart.value.find((item) => Number(item.product_variant_id) === Number(product.value));
    if (row) {
        if (row.quantity < row.stock) row.quantity += 1;
        return;
    }
    cart.value.push({
        product_variant_id: product.value,
        label: product.label,
        sku: product.sku,
        stock: product.stock,
        quantity: 1,
        unit_price: Number(product.price || 0),
    });
}

function removeRow(row) {
    cart.value = cart.value.filter((item) => item !== row);
}

function toggleProductCost(product) {
    const next = new Set(revealedProductCosts.value);
    const key = Number(product.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    revealedProductCosts.value = next;
}

function toggleGlobalCosting() {
    if (showCosting.value) {
        showCosting.value = false;
        revealedProductCosts.value = new Set();
        return;
    }

    showCosting.value = true;
}

function syncFullscreenState() {
    isFullscreen.value = Boolean(document.fullscreenElement);
}

async function toggleFullscreen() {
    if (!document.fullscreenElement) {
        hideSidebar.value = true;
        await document.documentElement.requestFullscreen?.();
    } else {
        await document.exitFullscreen?.();
    }
    syncFullscreenState();
}

const form = useForm({});
function checkout() {
    if (!registerOpen.value) {
        form.setError('pos_session', 'Open the POS register before completing a sale.');
        return;
    }

    form.transform(() => ({
        customer_id: selectedCustomer.value || null,
        sold_at: new Date().toISOString(),
        payment_method: paymentMethod.value,
        paid: paid.value || grandTotal.value,
        print_mode: 'receipt',
        discount: Number(discount.value || 0),
        tax: Number(tax.value || 0),
        shipping: 0,
        items: cart.value.map((item) => ({
            product_variant_id: item.product_variant_id,
            quantity: Number(item.quantity || 0),
            unit_price: Number(item.unit_price || 0),
        })),
    })).post(route('sales.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            paid.value = 0;
            discount.value = 0;
            tax.value = 0;
            search.value = '';
            router.reload({ only: ['products'] });
        },
    });
}

onMounted(() => {
    document.addEventListener('fullscreenchange', syncFullscreenState);
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);
});
</script>

<template>
    <Head title="POS" />
    <AuthenticatedLayout :sidebar-hidden="hideSidebar">
        <template v-if="!hideSidebar" #header><h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">POS</h2></template>

        <div class="grid min-h-[calc(100vh-5rem)] grid-cols-1 bg-gray-100 dark:bg-gray-950 xl:grid-cols-[1fr_480px]">
            <section class="p-4 sm:p-6">
                <div class="mb-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-4">
                            <img
                                :src="appSettings.company_logo_url || '/images/logo-small.png'"
                                :alt="appSettings.company_name || 'RainShield'"
                                class="h-14 w-14 rounded-md object-contain"
                            />
                            <div>
                                <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ appSettings.company_name || 'RainShield' }}</h3>
                            </div>
                        </div>
                        <div class="grid min-w-0 gap-3 md:grid-cols-[minmax(260px,1fr)_minmax(220px,320px)] lg:w-[760px]">
                            <input v-model="search" autofocus class="rounded-lg border-gray-300 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" placeholder="Scan or search product, SKU..." />
                            <SearchableSelect v-model="selectedCustomer" :options="customers" placeholder="Walk-in customer" />
                            <div class="flex flex-wrap gap-2 md:col-span-2">
                                <div
                                    v-if="posSession"
                                    class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                                    :title="`Opened ${posSession.opened_at}`"
                                >
                                    <AppIcon name="wallet" />
                                    <span>{{ posSession.session_no }}</span>
                                </div>
                                <button
                                    v-if="posSession"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-red-400 hover:bg-red-50 hover:text-red-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-red-950/40 dark:hover:text-red-200"
                                    title="Close POS register"
                                    @click="showCloseRegister = true; closeForm.closing_amount = expectedCash.toFixed(2)"
                                >
                                    <AppIcon name="lock" />
                                    <span>Close Register</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-200"
                                    :title="hideSidebar ? 'Show navigation' : 'Hide navigation'"
                                    @click="hideSidebar = !hideSidebar"
                                >
                                    <AppIcon :name="hideSidebar ? 'panel-right' : 'panel-left'" />
                                    <span>{{ hideSidebar ? 'Show Nav' : 'Hide Nav' }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-200"
                                    :title="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
                                    @click="toggleFullscreen"
                                >
                                    <AppIcon :name="isFullscreen ? 'minimize' : 'maximize'" />
                                    <span>{{ isFullscreen ? 'Exit Fullscreen' : 'Fullscreen' }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-200"
                                    :class="showCosting ? 'border-indigo-400 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200' : ''"
                                    :title="showCosting ? 'Hide all product costs' : 'Show all product costs'"
                                    @click="toggleGlobalCosting"
                                >
                                    <AppIcon :name="showCosting ? 'eye-off' : 'eye'" />
                                    <span>{{ showCosting ? 'Hide Cost' : 'Show Cost' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="grid border-t border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-950 sm:grid-cols-3">
                        <div class="px-5 py-3">
                            <div class="text-xs uppercase text-gray-500">Customer</div>
                            <div class="mt-1 truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ selectedCustomerLabel }}</div>
                        </div>
                        <div class="px-5 py-3">
                            <div class="text-xs uppercase text-gray-500">Items</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ totalQty }} piece(s)</div>
                        </div>
                        <div class="px-5 py-3">
                            <div class="text-xs uppercase text-gray-500">Invoice total</div>
                            <div class="mt-1 text-sm font-semibold text-indigo-600 dark:text-indigo-300">{{ money(grandTotal) }}</div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!posSession"
                    class="mb-5 rounded-lg border border-indigo-200 bg-white p-5 shadow-sm dark:border-indigo-900/70 dark:bg-gray-900"
                >
                    <div class="grid gap-5 lg:grid-cols-[1fr_420px] lg:items-end">
                        <div>
                            <div class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                <AppIcon name="wallet" />
                                <span>Open POS Register</span>
                            </div>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Enter the cash amount in the drawer before starting sales. The closing count will compare against this opening cash plus cash sales.
                            </p>
                        </div>
                        <form class="grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="openRegister">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                Opening amount
                                <TextInput v-model="openForm.opening_amount" type="number" min="0" step="0.01" class="mt-1 block w-full text-right" placeholder="0.00" />
                                <InputError :message="openForm.errors.opening_amount" class="mt-1" />
                            </label>
                            <PrimaryButton class="mt-6 justify-center" :disabled="openForm.processing" :class="{ 'opacity-50': openForm.processing }">
                                Open Register
                            </PrimaryButton>
                            <label class="sm:col-span-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                Notes
                                <textarea v-model="openForm.opening_notes" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" rows="2" placeholder="Optional opening note"></textarea>
                                <InputError :message="openForm.errors.opening_notes" class="mt-1" />
                            </label>
                        </form>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-3" :class="{ 'pointer-events-none opacity-45': !posSession }">
                    <button
                        v-for="product in visibleProducts"
                        :key="product.value"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:border-indigo-400 hover:shadow-md dark:border-gray-800 dark:bg-gray-900"
                        :title="posSession ? 'Add to invoice' : 'Open POS register before selling'"
                        @click="addProduct(product)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-base font-semibold text-gray-900 dark:text-gray-100">{{ product.label }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ product.sku || 'No SKU' }}</div>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="product.stock <= 3 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-700'">{{ product.stock }} left</span>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-2 rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                            <div>
                                <div class="text-[10px] uppercase text-gray-500">Price</div>
                                <div class="text-sm font-bold text-indigo-600 dark:text-indigo-300">{{ money(product.price) }}</div>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase text-gray-500">Cost</div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 transition hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-300"
                                    :title="productCostVisible(product) ? 'Hide this product cost' : 'Show this product cost'"
                                    @click.stop="toggleProductCost(product)"
                                >
                                    <AppIcon :name="productCostVisible(product) ? 'eye-off' : 'eye'" class="h-3.5 w-3.5" />
                                    <span>{{ productCostVisible(product) ? money(product.cost) : '*****' }}</span>
                                </button>
                            </div>
                            <div>
                                <div class="text-[10px] uppercase text-gray-500">Margin</div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-gray-900 transition hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-300"
                                    :title="productCostVisible(product) ? 'Hide this product margin' : 'Show this product margin'"
                                    @click.stop="toggleProductCost(product)"
                                >
                                    <AppIcon :name="productCostVisible(product) ? 'eye-off' : 'eye'" class="h-3.5 w-3.5" />
                                    <span>{{ productCostVisible(product) ? marginLabel(product) : '*****' }}</span>
                                </button>
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <aside class="border-l border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Invoice</h3>
                        <p class="text-sm text-gray-500">{{ selectedCustomerLabel }}</p>
                    </div>
                    <button type="button" class="text-sm text-red-600" @click="cart = []">Clear</button>
                </div>

                <div
                    v-if="posSession"
                    class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-gray-100">{{ posSession.session_no }}</div>
                            <div class="text-xs text-gray-500">Opened {{ posSession.opened_at }}</div>
                        </div>
                        <button type="button" class="text-xs font-semibold text-indigo-600 dark:text-indigo-300" @click="showCloseRegister = true; closeForm.closing_amount = expectedCash.toFixed(2)">
                            Close
                        </button>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <div>Opening <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ money(posSession.opening_amount) }}</span></div>
                        <div>Expected cash <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ money(expectedCash) }}</span></div>
                    </div>
                </div>

                <div class="mt-5 max-h-[42vh] space-y-3 overflow-y-auto pr-1">
                    <div v-for="item in cart" :key="item.product_variant_id" class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="flex justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ item.label }}</div>
                                <div class="text-xs text-gray-500">Stock {{ item.stock }}</div>
                            </div>
                            <button type="button" class="text-sm text-red-600" @click="removeRow(item)">×</button>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            <TextInput v-model="item.quantity" type="number" min="1" :max="item.stock" step="1" class="text-right" />
                            <TextInput v-model="item.unit_price" type="number" min="0" step="0.01" class="text-right" />
                            <div class="py-2 text-right text-sm font-semibold">{{ money(item.quantity * item.unit_price) }}</div>
                        </div>
                    </div>
                    <div v-if="cart.length === 0" class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">No items in cart.</div>
                </div>

                <div class="mt-6 space-y-3 border-t border-gray-200 pt-5 dark:border-gray-700">
                    <div class="flex justify-between text-sm text-gray-600 dark:text-gray-300"><span>Subtotal</span><span>{{ money(subtotal) }}</span></div>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-sm text-gray-600 dark:text-gray-300">
                            Discount
                            <TextInput v-model="discount" type="number" min="0" step="0.01" class="mt-1 block w-full text-right" />
                        </label>
                        <label class="text-sm text-gray-600 dark:text-gray-300">
                            Tax
                            <TextInput v-model="tax" type="number" min="0" step="0.01" class="mt-1 block w-full text-right" />
                        </label>
                    </div>
                    <div class="flex justify-between rounded-lg bg-gray-950 px-4 py-3 text-xl font-semibold text-white dark:bg-indigo-950"><span>Total</span><span>{{ money(grandTotal) }}</span></div>
                    <SearchableSelect v-model="paymentMethod" :options="paymentMethodOptions" placeholder="Search method..." />
                    <TextInput v-model="paid" type="number" min="0" step="0.01" class="block w-full text-right" placeholder="Paid amount" />
                    <div class="flex justify-between text-sm font-semibold" :class="balance < 0 ? 'text-red-600 dark:text-red-300' : 'text-emerald-600 dark:text-emerald-300'">
                        <span>{{ balance < 0 ? 'Due' : 'Change / advance' }}</span>
                        <span>{{ money(Math.abs(balance)) }}</span>
                    </div>
                    <InputError :message="form.errors.items" />
                    <InputError :message="form.errors.pos_session" />
                    <PrimaryButton class="w-full justify-center" :disabled="form.processing || cart.length === 0 || !posSession" :class="{ 'opacity-50': form.processing || cart.length === 0 || !posSession }" @click="checkout">
                        Complete Sale
                    </PrimaryButton>
                </div>
            </aside>
        </div>

        <div v-if="showCloseRegister && posSession" class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4">
            <form class="w-full max-w-xl rounded-lg border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900" @submit.prevent="closeRegister">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Close POS Register</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ posSession.session_no }} opened {{ posSession.opened_at }}</p>
                    </div>
                    <button type="button" class="rounded-md p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="showCloseRegister = false" title="Close">
                        <AppIcon name="x" />
                    </button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                        <div class="text-xs uppercase text-gray-500">Opening cash</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ money(posSession.opening_amount) }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                        <div class="text-xs uppercase text-gray-500">Cash sales</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ money(posSummary?.cash_sales) }}</div>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                        <div class="text-xs uppercase text-gray-500">Other payments</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ money(posSummary?.other_payments) }}</div>
                    </div>
                    <div class="rounded-lg bg-indigo-50 p-3 dark:bg-indigo-950/40">
                        <div class="text-xs uppercase text-indigo-700 dark:text-indigo-300">Expected cash</div>
                        <div class="mt-1 text-lg font-semibold text-indigo-800 dark:text-indigo-100">{{ money(expectedCash) }}</div>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        Closing counted amount
                        <TextInput v-model="closeForm.closing_amount" type="number" min="0" step="0.01" class="mt-1 block w-full text-right" />
                        <InputError :message="closeForm.errors.closing_amount" class="mt-1" />
                    </label>
                    <div class="rounded-lg border p-3 text-sm dark:border-gray-700" :class="closeDifference === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-200'">
                        <div class="text-xs uppercase">Difference</div>
                        <div class="mt-1 text-xl font-semibold">{{ money(closeDifference) }}</div>
                    </div>
                    <label class="sm:col-span-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                        Closing notes
                        <textarea v-model="closeForm.closing_notes" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" rows="3" placeholder="Optional closing note"></textarea>
                        <InputError :message="closeForm.errors.closing_notes" class="mt-1" />
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="showCloseRegister = false">
                        Cancel
                    </button>
                    <PrimaryButton :disabled="closeForm.processing" :class="{ 'opacity-50': closeForm.processing }">
                        Close Register
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
