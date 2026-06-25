<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
    options: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Search...',
    },
    noResultsText: {
        type: String,
        default: 'No matches found',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    invalid: {
        type: Boolean,
        default: false,
    },
});

const model = defineModel({
    type: [String, Number, null],
    default: '',
});

const root = ref(null);
const input = ref(null);
const isOpen = ref(false);
const query = ref('');
const activeIndex = ref(0);
const dropdownStyle = ref({});
const teleportTarget = ref('body');

const sameValue = (left, right) => String(left ?? '') === String(right ?? '');

const selectedOption = computed(() => props.options.find((option) => sameValue(option.value, model.value)));

const filteredOptions = computed(() => {
    const term = query.value.trim().toLowerCase();

    if (!term) {
        return props.options;
    }

    return props.options.filter((option) => {
        const label = String(option.label ?? '').toLowerCase();
        const description = String(option.description ?? '').toLowerCase();

        return label.includes(term) || description.includes(term);
    });
});

watch(
    () => model.value,
    () => {
        query.value = selectedOption.value?.label ?? '';
    },
    { immediate: true },
);

watch(filteredOptions, () => {
    activeIndex.value = 0;
});

function open() {
    if (props.disabled) {
        return;
    }

    teleportTarget.value = root.value?.closest('dialog') ?? 'body';
    isOpen.value = true;
    nextTick(updateDropdownPosition);
}

function close() {
    isOpen.value = false;
    query.value = selectedOption.value?.label ?? '';
}

function clear() {
    if (props.disabled) {
        return;
    }

    model.value = '';
    query.value = '';
    activeIndex.value = 0;
    open();
    nextTick(() => input.value?.focus());
}

function select(option) {
    if (props.disabled) {
        return;
    }

    model.value = option.value;
    query.value = option.label;
    isOpen.value = false;
}

function move(delta) {
    if (props.disabled) {
        return;
    }

    open();

    if (filteredOptions.value.length === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + delta + filteredOptions.value.length) % filteredOptions.value.length;
}

function updateDropdownPosition() {
    if (!root.value || !isOpen.value) {
        return;
    }

    const rect = root.value.getBoundingClientRect();
    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
}

function selectActive() {
    if (props.disabled) {
        return;
    }

    const option = filteredOptions.value[activeIndex.value];

    if (option) {
        select(option);
    }
}

function handleDocumentClick(event) {
    const isInsideDropdown = event.target?.closest?.('[data-searchable-select-panel]');

    if (!root.value?.contains(event.target) && !isInsideDropdown) {
        close();
    }
}

onMounted(() => {
    teleportTarget.value = root.value?.closest('dialog') ?? 'body';
    document.addEventListener('mousedown', handleDocumentClick);
    window.addEventListener('scroll', updateDropdownPosition, true);
    window.addEventListener('resize', updateDropdownPosition);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleDocumentClick);
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});
</script>

<template>
    <div ref="root" class="relative">
        <div
            class="flex rounded-md border border-gray-300 bg-white shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950"
            :class="{
                'opacity-70': disabled,
                'border-red-500 bg-red-50/80 focus-within:border-red-500 focus-within:ring-red-500 dark:border-red-500 dark:bg-red-950/30': invalid,
            }"
        >
            <input
                :id="id"
                ref="input"
                v-model="query"
                type="text"
                autocomplete="off"
                :disabled="disabled"
                :placeholder="placeholder"
                class="min-w-0 flex-1 rounded-l-md border-0 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 disabled:cursor-not-allowed dark:text-gray-100 dark:placeholder:text-gray-500"
                :class="{ 'text-red-900 dark:text-red-100': invalid }"
                @focus="open"
                @input="open"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter.prevent="selectActive"
                @keydown.esc.prevent="close"
            />
            <button
                v-if="model"
                type="button"
                :disabled="disabled"
                class="px-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                aria-label="Clear selection"
                @click="clear"
            >
                x
            </button>
            <button
                type="button"
                :disabled="disabled"
                class="rounded-r-md px-3 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                aria-label="Open options"
                @click="isOpen ? close() : open()"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <Teleport :to="teleportTarget">
            <div
                v-if="isOpen"
                :style="dropdownStyle"
                data-searchable-select-panel
                class="z-[9999] max-h-60 overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-950"
            >
                <button
                    v-for="(option, index) in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm"
                    :class="index === activeIndex ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800'"
                    @mouseenter="activeIndex = index"
                    @mousedown.prevent="select(option)"
                >
                    <span class="block font-medium">{{ option.label }}</span>
                    <span v-if="option.description" class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                        {{ option.description }}
                    </span>
                </button>
                <div v-if="filteredOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                    {{ noResultsText }}
                </div>
            </div>
        </Teleport>
    </div>
</template>
