<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import TableControls from '@/Components/TableControls.vue';
import { useTableControls } from '@/Composables/useTableControls';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ rows: Array, total: Number, from: String, to: String });
const table = useTableControls(() => props.rows, ['staff', 'cards', 'pieces', 'wage']);

const money = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const from = ref(props.from);
const to = ref(props.to);
function applyRange() {
    router.get(route('wages.index'), { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Wages" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-100">Staff Wages</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">From</label>
                        <DatePicker v-model="from" class="mt-1 w-40" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">To</label>
                        <DatePicker v-model="to" class="mt-1 w-40" />
                    </div>
                    <button @click="applyRange" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Apply</button>
                    <div class="ml-auto text-right">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Total wages</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ money(total) }}</div>
                    </div>
                </div>

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <TableControls :table="table" placeholder="Search staff wages...">
                        <div class="table-scroll">
                            <table class="min-w-[720px] divide-y divide-gray-200 dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-950">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Staff</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Job cards</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Pieces</th>
                                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Wage</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <tr v-for="(r, i) in table.rows.value" :key="`${r.staff}-${i}`">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ r.staff }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ r.cards }}</td>
                                        <td class="px-6 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ r.pieces }}</td>
                                        <td class="px-6 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ money(r.wage) }}</td>
                                    </tr>
                                    <tr v-if="table.rows.value.length === 0">
                                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                            No completed job cards in this date range.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </TableControls>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

