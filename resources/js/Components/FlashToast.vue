<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);
const message = ref('');
const type = ref('success');

const flash = computed(() => page.props.flash ?? {});
const errors = computed(() => page.props.errors ?? {});

watch(
    flash,
    (value) => {
        if (value?.success) {
            message.value = value.success;
            type.value = 'success';
            show();
        } else if (value?.error) {
            message.value = value.error;
            type.value = 'error';
            show();
        }
    },
    { deep: true, immediate: true },
);

watch(
    errors,
    (value) => {
        const first = Object.values(value ?? {})[0];
        if (!first) return;

        message.value = Array.isArray(first) ? first[0] : first;
        type.value = 'error';
        show();
    },
    { deep: true },
);

let timer = null;
function show() {
    visible.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (visible.value = false), 3500);
}

function handleToast(event) {
    message.value = event.detail?.message ?? '';
    type.value = event.detail?.type ?? 'success';
    if (message.value) show();
}

onMounted(() => window.addEventListener('app:toast', handleToast));
onBeforeUnmount(() => window.removeEventListener('app:toast', handleToast));
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed right-4 top-4 z-50 rounded-md px-4 py-3 text-sm font-medium text-white shadow-lg"
            :class="type === 'success' ? 'bg-emerald-600' : 'bg-red-600'"
        >
            {{ message }}
        </div>
    </Transition>
</template>
