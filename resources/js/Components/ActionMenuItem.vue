<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    icon: {
        type: String,
        required: true,
    },
    href: {
        type: String,
        default: '',
    },
    danger: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
        :class="danger ? 'text-red-600 dark:text-red-300' : 'text-gray-700 dark:text-gray-200'"
    >
        <AppIcon :name="icon" />
        <slot />
    </Link>
    <button
        v-else
        type="button"
        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800"
        :class="danger ? 'text-red-600 dark:text-red-300' : 'text-gray-700 dark:text-gray-200'"
    >
        <AppIcon :name="icon" />
        <slot />
    </button>
</template>
