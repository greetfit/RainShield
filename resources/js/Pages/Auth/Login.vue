<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const isDark = ref(false);
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const features = [
    'Stock, recipes and landed-cost costing',
    'Cutting, stitching and packing tracking',
    'Piece-rate staff wages',
    'Deliveries with lead-time insight',
];

function applyTheme(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

function toggleTheme() {
    applyTheme(!isDark.value);
}

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});
</script>

<template>
    <Head title="Log in" />

    <div class="flex min-h-screen bg-gray-50 dark:bg-gray-950">
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-slate-950 p-12 lg:flex">
            <div class="relative flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="rounded-2xl bg-white p-2.5 shadow-lg dark:bg-gray-900">
                        <ApplicationLogo class="h-11 w-11 object-contain" />
                    </div>
                    <span class="text-xl font-semibold tracking-tight text-white">Rain Shield</span>
                </div>

                <button
                    type="button"
                    class="rounded-md border border-white/15 px-3 py-1.5 text-xs font-medium text-white/80 transition hover:bg-white/10 hover:text-white"
                    @click="toggleTheme"
                >
                    {{ isDark ? 'Light' : 'Dark' }}
                </button>
            </div>

            <div class="relative">
                <h1 class="text-4xl font-bold leading-tight text-white">
                    Production<br />Management System
                </h1>
                <p class="mt-4 max-w-md text-blue-100/90">
                    From raw material purchasing to finished delivery, everything Rain Shield makes in one place.
                </p>
                <ul class="mt-8 space-y-3">
                    <li v-for="feature in features" :key="feature" class="flex items-center gap-3 text-sm text-blue-50/90">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-blue-500/25">
                            <svg class="h-3 w-3 text-blue-100" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        {{ feature }}
                    </li>
                </ul>
            </div>

            <div class="relative text-sm text-blue-100/70">
                Powered by <span class="font-semibold text-white">Home to Globe</span>
            </div>
        </div>

        <div class="flex w-full items-center justify-center p-6 lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center justify-between lg:hidden">
                    <div class="flex items-center gap-3">
                        <ApplicationLogo class="h-14 w-14 object-contain" />
                        <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Rain Shield</span>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-100 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-gray-900"
                        @click="toggleTheme"
                    >
                        {{ isDark ? 'Light' : 'Dark' }}
                    </button>
                </div>

                <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-100 dark:bg-gray-900 dark:ring-gray-800">
                    <div class="mb-6 hidden items-center gap-3 lg:flex">
                        <ApplicationLogo class="h-12 w-12 object-contain" />
                        <div>
                            <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">Rain Shield</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Home to Globe</div>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Welcome back</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sign in to continue to your dashboard.</p>

                    <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="mt-6 space-y-5">
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autofocus autocomplete="username" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required autocomplete="current-password" />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <Checkbox name="remember" v-model:checked="form.remember" />
                                <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">Remember me</span>
                            </label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                                Forgot password?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 dark:focus:ring-offset-gray-900"
                        >
                            {{ form.processing ? 'Signing in...' : 'Sign in' }}
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500">
                    Powered by <span class="font-medium text-gray-600 dark:text-gray-300">Home to Globe</span>
                </p>
            </div>
        </div>
    </div>
</template>
