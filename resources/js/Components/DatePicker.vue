<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const model = defineModel({ type: String, default: '' });
const props = defineProps({
    mode: { type: String, default: 'date' },
    placeholder: { type: String, default: '' },
    readonly: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    align: { type: String, default: 'left' },
});

const root = ref(null);
const open = ref(false);
const panelStyle = ref({});
const teleportTarget = ref('body');
const viewDate = ref(new Date());
const time = ref({ hour: '09', minute: '00' });
const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const isDateTime = computed(() => props.mode === 'datetime');

const pad = (value) => String(value).padStart(2, '0');
const toDateValue = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const parseDate = (value) => {
    if (!value) return null;
    const normalized = String(value).replace(' ', 'T');
    const [datePart] = normalized.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    if (!year || !month || !day) return null;
    return new Date(year, month - 1, day);
};

function syncFromModel() {
    const parsed = parseDate(model.value);
    if (parsed) viewDate.value = parsed;

    const normalized = String(model.value || '').replace(' ', 'T');
    if (isDateTime.value && normalized.includes('T')) {
        const [, timePart] = normalized.split('T');
        const [hour = '09', minute = '00'] = timePart.split(':');
        time.value = { hour: pad(hour), minute: pad(minute) };
    }
}

const selectedDate = computed(() => parseDate(model.value));
const displayValue = computed(() => {
    const date = selectedDate.value;
    if (!date) return '';

    const dateText = `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
    if (!isDateTime.value) return dateText;

    const normalized = String(model.value || '').replace(' ', 'T');
    const [hour = '00', minute = '00'] = (normalized.split('T')[1] ?? '').split(':');
    const hourNumber = Number(hour);
    const period = hourNumber >= 12 ? 'PM' : 'AM';
    const twelveHour = hourNumber % 12 || 12;

    return `${dateText} ${pad(twelveHour)}:${pad(minute)} ${period}`;
});

const calendarDays = computed(() => {
    const year = viewDate.value.getFullYear();
    const month = viewDate.value.getMonth();
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7;
    const days = [];
    const cursor = new Date(year, month, 1 - offset);

    for (let i = 0; i < 42; i += 1) {
        days.push({
            key: toDateValue(cursor),
            day: cursor.getDate(),
            date: new Date(cursor),
            inMonth: cursor.getMonth() === month,
        });
        cursor.setDate(cursor.getDate() + 1);
    }

    return days;
});

const monthLabel = computed(() => `${months[viewDate.value.getMonth()]} ${viewDate.value.getFullYear()}`);
const hourOptions = computed(() => Array.from({ length: 24 }, (_, i) => pad(i)));
const minuteOptions = computed(() => Array.from({ length: 60 }, (_, i) => pad(i)));

function updateModel(date = selectedDate.value ?? new Date()) {
    const dateValue = toDateValue(date);
    model.value = isDateTime.value ? `${dateValue}T${time.value.hour}:${time.value.minute}` : dateValue;
}

function selectDate(date) {
    viewDate.value = date;
    updateModel(date);
    if (!isDateTime.value) open.value = false;
}

function changeMonth(amount) {
    viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + amount, 1);
}

function setToday() {
    const today = new Date();
    time.value = { hour: pad(today.getHours()), minute: pad(today.getMinutes()) };
    selectDate(today);
}

function clear() {
    model.value = '';
    open.value = false;
}

function toggle() {
    if (props.readonly) return;
    syncFromModel();
    teleportTarget.value = root.value?.closest('dialog') ?? 'body';
    open.value = !open.value;
    if (open.value) {
        nextTick(updatePanelPosition);
    }
}

function closeOnOutside(event) {
    if (!root.value?.contains(event.target) && !event.target.closest?.('[data-date-picker-panel]')) {
        open.value = false;
    }
}

function updatePanelPosition() {
    if (!open.value || !root.value) return;

    const rect = root.value.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const margin = 8;
    const preferredWidth = isDateTime.value ? 384 : 320;
    const width = Math.min(preferredWidth, viewportWidth - margin * 2);
    const preferredLeft = props.align === 'right' ? rect.right - width : rect.left;
    const left = Math.min(Math.max(margin, preferredLeft), viewportWidth - width - margin);

    panelStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
        width: `${width}px`,
    };
}

watch(time, () => {
    if (isDateTime.value && selectedDate.value) {
        updateModel(selectedDate.value);
    }
}, { deep: true });

watch(() => model.value, syncFromModel);

watch(open, (value) => {
    if (value) {
        nextTick(updatePanelPosition);
    }
});

onMounted(() => {
    syncFromModel();
    teleportTarget.value = root.value?.closest('dialog') ?? 'body';
    document.addEventListener('mousedown', closeOnOutside);
    window.addEventListener('resize', updatePanelPosition);
    window.addEventListener('scroll', updatePanelPosition, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', closeOnOutside);
    window.removeEventListener('resize', updatePanelPosition);
    window.removeEventListener('scroll', updatePanelPosition, true);
});
</script>

<template>
    <div ref="root" class="relative" :class="open ? 'z-[10000]' : ''">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
            :class="[
                readonly ? 'cursor-not-allowed opacity-75' : 'hover:border-indigo-400',
                invalid ? 'border-red-500 bg-red-50/80 text-red-900 focus:border-red-500 focus:ring-red-500 dark:border-red-500 dark:bg-red-950/30 dark:text-red-100' : '',
            ]"
            @click="toggle"
        >
            <span :class="displayValue ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400 dark:text-gray-500'">
                {{ displayValue || placeholder || (isDateTime ? 'Select date and time' : 'Select date') }}
            </span>
            <AppIcon name="calendar" />
        </button>

        <Teleport :to="teleportTarget">
            <div
                v-if="open"
                data-date-picker-panel
                class="z-[99999] max-h-[calc(100vh-1rem)] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-950"
                :style="panelStyle"
            >
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <button type="button" class="rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="changeMonth(-1)">
                        <AppIcon name="chevron-left" />
                    </button>
                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ monthLabel }}</div>
                    <button type="button" class="rounded-md p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="changeMonth(1)">
                        <AppIcon name="chevron-right" />
                    </button>
                </div>

                <div class="grid gap-4 p-4" :class="isDateTime ? 'sm:grid-cols-[1fr_8rem]' : ''">
                    <div>
                        <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-gray-500 dark:text-gray-400">
                            <div v-for="day in weekdays" :key="day" class="py-1">{{ day }}</div>
                        </div>
                        <div class="mt-1 grid grid-cols-7 gap-1">
                            <button
                                v-for="day in calendarDays"
                                :key="day.key"
                                type="button"
                                class="h-9 rounded-md text-sm transition"
                                :class="[
                                    day.key === (selectedDate && toDateValue(selectedDate))
                                        ? 'bg-indigo-600 font-semibold text-white'
                                        : day.inMonth
                                          ? 'text-gray-800 hover:bg-indigo-50 dark:text-gray-100 dark:hover:bg-gray-800'
                                          : 'text-gray-300 hover:bg-gray-50 dark:text-gray-600 dark:hover:bg-gray-900',
                                ]"
                                @click="selectDate(day.date)"
                            >
                                {{ day.day }}
                            </button>
                        </div>
                    </div>

                    <div v-if="isDateTime" class="grid grid-cols-2 gap-2 sm:block sm:space-y-3">
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Hour</label>
                            <select v-model="time.hour" class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                <option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Minute</label>
                            <select v-model="time.minute" class="mt-1 w-full rounded-md border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                <option v-for="minute in minuteOptions" :key="minute" :value="minute">{{ minute }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-sm dark:border-gray-800">
                    <button type="button" class="font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100" @click="clear">Clear</button>
                    <div class="flex gap-2">
                        <button type="button" class="font-medium text-indigo-600 hover:text-indigo-700" @click="setToday">Today</button>
                        <button v-if="isDateTime" type="button" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-white hover:bg-indigo-700" @click="open = false">Done</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
