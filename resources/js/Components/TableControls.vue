<script setup>
defineProps({
    table: {
        type: Object,
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
});
</script>

<template>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="w-full sm:max-w-xs">
            <input
                v-model="table.search.value"
                type="search"
                :placeholder="placeholder"
                class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
            />
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <span>Show</span>
            <select
                v-model.number="table.perPage.value"
                class="rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
            >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>
            <span>entries</span>
        </div>
    </div>

    <slot />

    <div class="mt-4 flex flex-col gap-3 text-sm text-gray-600 sm:flex-row sm:items-center sm:justify-between">
        <div>Showing {{ table.from.value }} to {{ table.to.value }} of {{ table.total.value }} entries</div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200"
                :disabled="table.page.value <= 1"
                @click="table.previous"
            >
                Previous
            </button>
            <span>Page {{ table.page.value }} / {{ table.lastPage.value }}</span>
            <button
                type="button"
                class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-200"
                :disabled="table.page.value >= table.lastPage.value"
                @click="table.next"
            >
                Next
            </button>
        </div>
    </div>
</template>
