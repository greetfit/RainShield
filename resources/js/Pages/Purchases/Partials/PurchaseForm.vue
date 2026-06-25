<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { computed, watch } from 'vue';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    materialOptions: {
        type: Array,
        required: true,
    },
    finishedProductOptions: {
        type: Array,
        default: () => [],
    },
    supplierOptions: {
        type: Array,
        required: true,
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    title: {
        type: String,
        required: true,
    },
    submitLabel: {
        type: String,
        default: 'Save purchase',
    },
});

defineEmits(['submit', 'cancel']);

watch(
    () => props.form.items.length,
    () => {
        if (props.form.items.length === 0) {
            addRow();
        }
    },
);

function addRow() {
    props.form.items.push(blankRow());
}

function removeRow(i) {
    props.form.items.splice(i, 1);
}

const blankRow = () => ({ id: null, item_type: 'raw_material', raw_material_variant_id: '', product_variant_id: '', quantity: '', unit_price: '' });
const itemTypes = [
    { value: 'raw_material', label: 'Raw material' },
    { value: 'finished_good', label: 'Finished product' },
];
const unitFor = (row) => {
    if ((row.item_type ?? 'raw_material') === 'finished_good') {
        return props.finishedProductOptions.find((o) => o.id === Number(row.product_variant_id))?.unit ?? 'piece';
    }

    return props.materialOptions.find((o) => o.id === Number(row.raw_material_variant_id))?.unit ?? '';
};
const materialSelectOptions = computed(() =>
    (props.materialOptions ?? []).map((option) => ({
        value: option.id,
        label: option.label,
        description: option.unit,
    })),
);
const supplierSelectOptions = computed(() =>
    (props.supplierOptions ?? []).map((option) => ({
        value: option.value ?? option.id ?? option.name,
        label: option.label ?? option.name ?? option.value,
        description: option.description ?? option.email ?? option.phone ?? '',
    })),
);
const finishedProductSelectOptions = computed(() =>
    (props.finishedProductOptions ?? []).map((option) => ({
        value: option.id,
        label: option.label,
        description: option.description ?? option.unit ?? 'Finished product',
    })),
);
const optionsFor = (row) => (row.item_type === 'finished_good' ? finishedProductSelectOptions.value : materialSelectOptions.value);
const modelKeyFor = (row) => (row.item_type === 'finished_good' ? 'product_variant_id' : 'raw_material_variant_id');
const placeholderFor = (row) => (row.item_type === 'finished_good' ? 'Search finished product...' : 'Search material...');
const noResultsFor = (row) => (row.item_type === 'finished_good' ? 'No finished products found' : 'No materials found');
function changeItemType(row) {
    row.raw_material_variant_id = '';
    row.product_variant_id = '';
    if (row.item_type === 'finished_good' && Number(row.quantity) > 0) {
        row.quantity = Math.floor(Number(row.quantity));
    }
}
const lineTotal = (row) => (Number(row.quantity) || 0) * (Number(row.unit_price) || 0);
const itemsTotal = computed(() => props.form.items.reduce((sum, row) => sum + lineTotal(row), 0));
const totalQty = computed(() => props.form.items.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0));

function allocated(row) {
    const transport = Number(props.form.transport_charge) || 0;
    const byValue = props.form.allocation_method === 'value' && itemsTotal.value > 0;
    const basisTotal = byValue ? itemsTotal.value : totalQty.value;
    if (basisTotal <= 0) return 0;
    const basis = byValue ? lineTotal(row) : Number(row.quantity) || 0;
    return transport * (basis / basisTotal);
}

function landed(row) {
    const qty = Number(row.quantity) || 0;
    if (qty <= 0) return 0;
    return (lineTotal(row) + allocated(row)) / qty;
}

const grandTotal = computed(() => itemsTotal.value + (Number(props.form.transport_charge) || 0));
const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <form class="p-6" @submit.prevent="$emit('submit')">
        <h2 class="text-lg font-medium text-gray-900">{{ title }}</h2>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="purchase_date" value="Date" />
                <DatePicker id="purchase_date" v-model="form.purchased_on" class="mt-1" />
                <InputError :message="form.errors.purchased_on" class="mt-1" />
            </div>
            <div>
                <InputLabel for="purchase_reference" value="Reference / Invoice #" />
                <TextInput id="purchase_reference" v-model="form.reference" class="mt-1 block w-full" />
                <InputError :message="form.errors.reference" class="mt-1" />
            </div>
        </div>

        <div class="mt-4">
            <InputLabel for="purchase_status" value="Order status" />
            <select id="purchase_status" v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                <option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
            <InputError :message="form.errors.status" class="mt-1" />
        </div>

        <div class="mt-4">
            <InputLabel for="purchase_supplier" value="Supplier" />
            <SearchableSelect
                id="purchase_supplier"
                v-model="form.supplier_name"
                :options="supplierSelectOptions"
                placeholder="Search supplier..."
                class="mt-1"
            />
            <InputError :message="form.errors.supplier_name" class="mt-1" />
        </div>

        <div class="mt-6 overflow-hidden rounded-lg border border-gray-200">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h3 class="font-semibold text-gray-800">Purchased items</h3>
                <SecondaryButton type="button" @click="addRow">+ Add row</SecondaryButton>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Item</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Unit price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Line total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="(row, i) in form.items" :key="row.id ?? i">
                            <td class="min-w-40 px-4 py-2">
                                <select
                                    v-model="row.item_type"
                                    class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                    @change="changeItemType(row)"
                                >
                                    <option v-for="type in itemTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                            </td>
                            <td class="min-w-72 px-4 py-2">
                                <SearchableSelect
                                    v-model="row[modelKeyFor(row)]"
                                    :options="optionsFor(row)"
                                    :placeholder="placeholderFor(row)"
                                    :no-results-text="noResultsFor(row)"
                                />
                            </td>
                            <td class="px-4 py-2 text-right">
                                <input v-model="row.quantity" type="number" :step="row.item_type === 'finished_good' ? 1 : 0.001" min="0" class="w-24 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                <div class="text-[10px] text-gray-400">{{ unitFor(row) }}</div>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <input v-model="row.unit_price" type="number" step="0.01" min="0" class="w-28 rounded-md border-gray-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-gray-700">{{ money(lineTotal(row)) }}</td>
                            <td class="px-4 py-2 text-right">
                                <button type="button" class="text-red-600 hover:text-red-800" @click="removeRow(i)">x</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <InputError :message="form.errors.items" class="px-4 py-2" />
        </div>

        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="purchase_transport" value="Transport / freight charge" />
                <TextInput id="purchase_transport" v-model="form.transport_charge" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                <InputError :message="form.errors.transport_charge" class="mt-1" />
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <InputLabel for="purchase_allocation" value="Spread transport by" />
                    <span class="group relative inline-flex">
                        <button
                            type="button"
                            class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-xs font-semibold text-gray-500 transition hover:border-indigo-400 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-600 dark:text-gray-300 dark:hover:border-indigo-400"
                            aria-label="Transport allocation help"
                        >
                            ?
                        </button>
                        <span
                            class="pointer-events-none absolute left-1/2 top-7 z-30 hidden w-80 -translate-x-1/2 rounded-md border border-gray-200 bg-white p-3 text-xs leading-5 text-gray-700 shadow-lg group-hover:block group-focus-within:block dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <strong class="block text-gray-900 dark:text-white">Line value</strong>
                            Shares transport by each item's value. Expensive items carry more freight cost.
                            <strong class="mt-2 block text-gray-900 dark:text-white">Quantity</strong>
                            Shares transport by item quantity. Use when freight depends more on count than price.
                        </span>
                    </span>
                </div>
                <select id="purchase_allocation" v-model="form.allocation_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="value">Line value</option>
                    <option value="quantity">Quantity</option>
                </select>
            </div>
        </div>

        <div class="mt-4">
            <InputLabel for="purchase_notes" value="Notes" />
            <textarea id="purchase_notes" v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div class="mt-5 rounded-md bg-gray-50 p-4 text-sm">
            <div class="flex justify-between"><span class="text-gray-500">Items total</span><span>{{ money(itemsTotal) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Transport</span><span>{{ money(Number(form.transport_charge) || 0) }}</span></div>
            <div class="mt-1 flex justify-between border-t pt-1 font-semibold text-gray-900"><span>Grand total</span><span>{{ money(grandTotal) }}</span></div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <SecondaryButton type="button" @click="$emit('cancel')">Cancel</SecondaryButton>
            <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">{{ submitLabel }}</PrimaryButton>
        </div>
    </form>
</template>
