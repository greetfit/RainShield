<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import TableControls from '@/Components/TableControls.vue';
import InputLabel from '@/Components/InputLabel.vue';
import DatePicker from '@/Components/DatePicker.vue';
import InputError from '@/Components/InputError.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ deliveries: Array });
const table = useTableControls(() => props.deliveries, ['code', 'customer_name', 'product', 'status', 'dispatched_on', 'delivered_on']);

const deliverTarget = ref(null);
const form = useForm({ delivered_on: '' });
function openDeliver(d) {
    deliverTarget.value = d;
    form.reset();
    form.clearErrors();
    form.delivered_on = new Date().toISOString().slice(0, 10);
}
function submitDeliver() {
    form.post(route('deliveries.delivered', deliverTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => (deliverTarget.value = null),
    });
}
</script>

<template>
    <Head title="Deliveries" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Deliveries</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex justify-end">
                    <Link :href="route('deliveries.create')">
                        <PrimaryButton>
                            <span class="inline-flex items-center gap-2">
                                <AppIcon name="truck" />
                                New Delivery
                            </span>
                        </PrimaryButton>
                    </Link>
                </div>

                <TableControls :table="table" placeholder="Search deliveries...">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Dispatched</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Delivered</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Lead (days)</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="d in table.rows.value" :key="d.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ d.code }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.customer_name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.product }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ d.quantity }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.dispatched_on }}</td>
                                <td class="px-6 py-4 text-sm text-gray-700">{{ d.delivered_on || '—' }}</td>
                                <td class="px-6 py-4 text-right text-sm text-gray-700">{{ d.lead_time ?? '—' }}</td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <button v-if="d.status === 'dispatched'" class="text-emerald-600 hover:text-emerald-800" @click="openDeliver(d)">
                                        Mark delivered
                                    </button>
                                    <span v-else class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">delivered</span>
                                </td>
                            </tr>
                            <tr v-if="table.rows.value.length === 0">
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No deliveries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </TableControls>
            </div>
        </div>

        <Modal :show="!!deliverTarget" @close="deliverTarget = null">
            <form @submit.prevent="submitDeliver" class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Mark Delivered — {{ deliverTarget?.code }}</h2>
                <div class="mt-4">
                    <InputLabel for="delivered_on" value="Delivered on" />
                    <DatePicker id="delivered_on" v-model="form.delivered_on" class="mt-1" />
                    <InputError :message="form.errors.delivered_on" class="mt-1" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="deliverTarget = null">Cancel</SecondaryButton>
                    <PrimaryButton :class="{ 'opacity-50': form.processing }" :disabled="form.processing">Save</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
