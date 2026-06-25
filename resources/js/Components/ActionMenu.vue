<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    label: {
        type: String,
        default: 'Actions',
    },
});

const root = ref(null);
const open = ref(false);
const menuStyle = ref({});

function close() {
    open.value = false;
}

function toggle() {
    open.value = !open.value;

    if (open.value) {
        nextTick(updatePosition);
    }
}

function updatePosition() {
    if (!root.value || !open.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();
    const width = 224;
    const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));

    menuStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 6}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
}

function handleClick(event) {
    if (!root.value?.contains(event.target) && !event.target.closest?.('[data-action-menu-panel]')) {
        close();
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleClick);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClick);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button
            type="button"
            :aria-label="label"
            :title="label"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="toggle"
        >
            <AppIcon name="more-horizontal" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                data-action-menu-panel
                :style="menuStyle"
                class="z-[9999] overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-950"
                @click="close"
            >
                <slot />
            </div>
        </Teleport>
    </div>
</template>
