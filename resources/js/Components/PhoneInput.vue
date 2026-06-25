<script setup>
import { computed, onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

const maskedValue = computed({
    get() {
        return formatPhone(model.value);
    },
    set(value) {
        model.value = formatPhone(value);
    },
});

function formatPhone(value) {
    const digits = String(value ?? '').replace(/\D/g, '').slice(0, 10);
    const parts = [];

    if (digits.length > 0) {
        parts.push(digits.slice(0, 3));
    }
    if (digits.length > 3) {
        parts.push(digits.slice(3, 6));
    }
    if (digits.length > 6) {
        parts.push(digits.slice(6, 10));
    }

    return parts.filter(Boolean).join(' ');
}

onMounted(() => {
    model.value = formatPhone(model.value);

    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        ref="input"
        v-model="maskedValue"
        type="tel"
        inputmode="numeric"
        autocomplete="tel"
        maxlength="12"
        placeholder="077 123 4567"
        class="rounded-md border-gray-300 bg-white text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-500"
    />
</template>
