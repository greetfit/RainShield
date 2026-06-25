<script setup>
import ActionMenu from '@/Components/ActionMenu.vue';
import ActionMenuItem from '@/Components/ActionMenuItem.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TableControls from '@/Components/TableControls.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    expenses: Array,
    categories: Array,
    paymentMethods: Array,
});

const table = useTableControls(() => props.expenses, ['expense_on', 'category', 'amount', 'payment_method', 'reference', 'notes']);
const totalAmount = computed(() => props.expenses.reduce((sum, expense) => sum + Number(expense.amount || 0), 0));
const showForm = ref(false);
const editingId = ref(null);
const deleteTarget = ref(null);
const form = useForm({
    expense_on: new Date().toISOString().slice(0, 10),
    expense_category_id: '',
    amount: '',
    payment_method: props.paymentMethods?.[0]?.value ?? 'cash',
    reference: '',
    notes: '',
});
const deleteForm = useForm({});
const money = (value) => Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const paymentMethodOptions = computed(() => props.paymentMethods.map((method) => ({
    value: method.value,
    label: method.label,
})));

function resetForm() {
    form.expense_on = new Date().toISOString().slice(0, 10);
    form.expense_category_id = '';
    form.amount = '';
    form.payment_method = props.paymentMethods?.[0]?.value ?? 'cash';
    form.reference = '';
    form.notes = '';
}

function openCreate() {
    editingId.value = null;
    form.clearErrors();
    resetForm();
    showForm.value = true;
}

function openEdit(expense) {
    editingId.value = expense.id;
    form.clearErrors();
    form.expense_on = expense.expense_on_input;
    form.expense_category_id = expense.category_id;
    form.amount = expense.amount;
    form.payment_method = expense.payment_method ?? '';
    form.reference = expense.reference ?? '';
    form.notes = expense.notes ?? '';
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            showForm.value = false;
        },
    };

    if (editingId.value) {
        form.put(route('expenses.update', editingId.value), options);
    } else {
        form.post(route('expenses.store'), options);
    }
}

function confirmDelete() {
    deleteForm.delete(route('expenses.destroy', deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteTarget.value = null),
    });
}
</script>

<template>
    <Head title="Expenses" />

    <AuthenticatedLayout>
        <template #header><h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Expenses</h2></template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="text-xs uppercase text-gray-500">Total expenses recorded</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ money(totalAmount) }}</div>
                    </div>
                    <PrimaryButton @click="openCreate">+ Add Expense</PrimaryButton>
                </div>

                <TableControls :table="table" placeholder="Search expenses...">
                    <div class="table-scroll">
                        <table class="min-w-[980px] divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Date</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Category</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Method</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Reference</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Notes</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Amount</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <tr v-for="expense in table.rows.value" :key="expense.id">
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.expense_on }}</td>
                                    <td class="px-5 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ expense.category }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.payment_method || '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.reference || '-' }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ expense.notes || '-' }}</td>
                                    <td class="px-5 py-3 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(expense.amount) }}</td>
                                    <td class="px-5 py-3 text-right text-sm">
                                        <ActionMenu>
                                            <ActionMenuItem icon="edit" @click="openEdit(expense)">Edit</ActionMenuItem>
                                            <ActionMenuItem icon="trash" danger @click="deleteTarget = expense">Delete</ActionMenuItem>
                                        </ActionMenu>
                                    </td>
                                </tr>
                                <tr v-if="table.rows.value.length === 0">
                                    <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No expenses recorded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="2xl">
            <form class="p-6" @submit.prevent="submit">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ editingId ? 'Edit Expense' : 'Add Expense' }}</h2>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel value="Date" />
                        <DatePicker v-model="form.expense_on" class="mt-1" />
                        <InputError :message="form.errors.expense_on" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Category" />
                        <SearchableSelect v-model="form.expense_category_id" :options="categories" placeholder="Search category..." class="mt-1" />
                        <InputError :message="form.errors.expense_category_id" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="expense_amount" value="Amount" />
                        <TextInput id="expense_amount" v-model="form.amount" type="number" min="0.01" step="0.01" class="mt-1 block w-full text-right" />
                        <InputError :message="form.errors.amount" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Payment method" />
                        <SearchableSelect v-model="form.payment_method" :options="paymentMethodOptions" placeholder="Search method..." class="mt-1" />
                        <InputError :message="form.errors.payment_method" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="expense_reference" value="Reference" />
                        <TextInput id="expense_reference" v-model="form.reference" class="mt-1 block w-full" placeholder="Bill number, voucher, cheque number..." />
                        <InputError :message="form.errors.reference" class="mt-1" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="expense_notes" value="Notes" />
                        <textarea id="expense_notes" v-model="form.notes" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                        <InputError :message="form.errors.notes" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showForm = false">Cancel</SecondaryButton>
                    <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">{{ editingId ? 'Save' : 'Add' }}</PrimaryButton>
                </div>
            </form>
        </Modal>

        <ConfirmModal
            :show="!!deleteTarget"
            title="Delete expense?"
            :message="`${deleteTarget?.category} expense of ${money(deleteTarget?.amount)} will be removed.`"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @cancel="deleteTarget = null"
        />
    </AuthenticatedLayout>
</template>
