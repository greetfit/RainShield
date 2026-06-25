<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    settings: Object,
    currencyOptions: Array,
    timezoneOptions: Array,
    dateFormatOptions: Array,
    timeFormatOptions: Array,
});

const page = usePage();
const logoInput = ref(null);
const logoPreview = ref(page.props.appSettings?.company_logo_url || null);
const logoFileName = computed(() => form.company_logo?.name || (logoPreview.value ? 'Current logo' : 'No logo selected'));

const form = useForm({
    _method: 'put',
    company_name: props.settings.company_name,
    company_phone: props.settings.company_phone,
    company_email: props.settings.company_email,
    company_logo: null,
    currency_code: props.settings.currency_code,
    currency_symbol: props.settings.currency_symbol,
    timezone: props.settings.timezone,
    date_format: props.settings.date_format,
    time_format: props.settings.time_format,
});

function applyCurrency(option) {
    form.currency_code = option.code;
    form.currency_symbol = option.symbol;
}

function setLogo(file) {
    if (!file) return;
    form.company_logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function handleLogoChange(event) {
    setLogo(event.target.files?.[0]);
}

function handleLogoDrop(event) {
    setLogo(event.dataTransfer.files?.[0]);
}

function submit() {
    form.post(route('business-settings.general.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.company_logo = null;
            if (logoInput.value) logoInput.value.value = '';
        },
    });
}
</script>

<template>
    <Head title="Business Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Business Settings</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <form class="space-y-6" @submit.prevent="submit">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Company Identity</h3>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Company name" />
                                <TextInput v-model="form.company_name" class="mt-1 block w-full" />
                                <InputError :message="form.errors.company_name" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Phone number" />
                                <TextInput v-model="form.company_phone" class="mt-1 block w-full" placeholder="Company phone" />
                                <InputError :message="form.errors.company_phone" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Email address" />
                                <TextInput v-model="form.company_email" type="email" class="mt-1 block w-full" placeholder="Company email" />
                                <InputError :message="form.errors.company_email" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Company logo" />
                                <div
                                    class="mt-1 flex min-h-32 cursor-pointer items-center gap-4 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 transition hover:border-indigo-400 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-indigo-500 dark:hover:bg-indigo-950/30"
                                    @click="logoInput.click()"
                                    @dragover.prevent
                                    @drop.prevent="handleLogoDrop"
                                >
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                                        <img v-if="logoPreview" :src="logoPreview" alt="Company logo preview" class="h-full w-full object-contain" />
                                        <span v-else class="text-xs text-gray-400">Logo</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Drop logo here or click to browse</div>
                                        <div class="mt-1 truncate text-xs text-gray-500">{{ logoFileName }}</div>
                                        <div class="mt-2 text-xs text-gray-500">PNG, JPG or WEBP. Used in sidebar, POS, invoice and receipt print pages.</div>
                                    </div>
                                    <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="handleLogoChange" />
                                </div>
                                <InputError :message="form.errors.company_logo" class="mt-1" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Currency</h3>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Currency code" />
                                <TextInput v-model="form.currency_code" class="mt-1 block w-full uppercase" />
                                <InputError :message="form.errors.currency_code" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Currency symbol" />
                                <TextInput v-model="form.currency_symbol" class="mt-1 block w-full" />
                                <InputError :message="form.errors.currency_symbol" class="mt-1" />
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="option in currencyOptions"
                                :key="option.code"
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm transition"
                                :class="form.currency_code === option.code ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800'"
                                @click="applyCurrency(option)"
                            >
                                {{ option.code }} - {{ option.symbol }}
                            </button>
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Date & Time</h3>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Timezone" />
                                <select v-model="form.timezone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    <option v-for="timezone in timezoneOptions" :key="timezone" :value="timezone">{{ timezone }}</option>
                                </select>
                                <InputError :message="form.errors.timezone" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Date format" />
                                <select v-model="form.date_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    <option v-for="option in dateFormatOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.date_format" class="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Time format" />
                                <select v-model="form.time_format" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                    <option v-for="option in timeFormatOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <InputError :message="form.errors.time_format" class="mt-1" />
                            </div>
                        </div>
                    </section>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing" :class="{ 'opacity-50': form.processing }">
                            Save Settings
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
