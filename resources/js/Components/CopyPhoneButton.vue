<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    phone: {
        type: [String, Number, null],
        default: '',
    },
});

const copied = ref(false);
const root = ref(null);
const open = ref(false);
const menuStyle = ref({});

const digits = () => String(props.phone ?? '').replace(/\D/g, '');
const hasPhone = () => digits().length > 0;
const whatsappUrl = () => `https://wa.me/${digits().startsWith('0') ? `94${digits().slice(1)}` : digits()}`;

async function copyPhone() {
    if (!hasPhone()) {
        return;
    }

    await navigator.clipboard.writeText(digits());
    copied.value = true;
    open.value = false;
    window.setTimeout(() => {
        copied.value = false;
    }, 1200);
}

function toggle() {
    if (!hasPhone()) {
        return;
    }

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
    const width = 184;
    const left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));

    menuStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 6}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
}

function closeOnOutsideClick(event) {
    if (!root.value?.contains(event.target) && !event.target.closest?.('[data-phone-actions-panel]')) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('mousedown', closeOnOutsideClick);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', closeOnOutsideClick);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <span ref="root" class="relative inline-flex">
        <button
            type="button"
            :disabled="!hasPhone()"
            :title="copied ? 'Copied' : 'Phone actions'"
            :aria-label="copied ? 'Copied phone number' : 'Phone actions'"
            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-white"
            @click="toggle"
        >
            <AppIcon :name="copied ? 'check' : 'phone'" />
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                data-phone-actions-panel
                :style="menuStyle"
                class="z-[9999] overflow-hidden rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-950"
                @click="open = false"
            >
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800" @click.stop="copyPhone">
                    <AppIcon name="copy" /> Copy number
                </button>
                <a :href="`tel:${digits()}`" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                    <AppIcon name="phone" /> Call number
                </a>
                <a :href="whatsappUrl()" target="_blank" rel="noopener" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800">
                    <AppIcon name="message" /> Open WhatsApp
                </a>
            </div>
        </Teleport>
    </span>
</template>
