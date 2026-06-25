<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    icon: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    variant: {
        type: String,
        default: 'default',
    },
});
</script>

<template>
    <button
        type="button"
        :title="title"
        :aria-label="title"
        class="inline-flex h-8 w-8 items-center justify-center rounded-md transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
        :class="
            variant === 'danger'
                ? 'text-red-600 hover:bg-red-50 hover:text-red-800 dark:hover:bg-red-950'
                : variant === 'success'
                  ? 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-800 dark:hover:bg-emerald-950'
                : 'text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white'
        "
    >
        <AppIcon :name="icon" />
    </button>
</template>
