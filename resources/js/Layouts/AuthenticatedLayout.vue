<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppIcon from '@/Components/AppIcon.vue';
import FlashToast from '@/Components/FlashToast.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDrawer = ref(false);
const isDark = ref(false);
const openGroups = ref({});
const accountMenuOpen = ref(false);
const desktopNav = ref(null);
const mobileNav = ref(null);
const sidebarScrollKey = 'rainshield.sidebar.scrollTop';
const sidebarGroupsKey = 'rainshield.sidebar.openGroups';
const props = defineProps({
    sidebarHidden: { type: Boolean, default: false },
});

const page = usePage();
const roles = computed(() => page.props.auth.roles ?? []);
const user = computed(() => page.props.auth.user);
const appSettings = computed(() => page.props.appSettings ?? {});
const hasAny = (...wanted) => wanted.some((r) => roles.value.includes(r));

const menuGroups = computed(() => [
    {
        label: 'Masters',
        icon: 'layers',
        can: hasAny('admin', 'stock_manager', 'production_manager'),
        links: [
            { label: 'Products', route: 'masters.products.index', icon: 'package' },
            { label: 'Raw Materials', route: 'masters.raw-materials.index', icon: 'box' },
            { label: 'Parts', route: 'masters.parts.index', icon: 'puzzle' },
            { label: 'Suppliers', route: 'masters.suppliers.index', icon: 'truck' },
            { label: 'Customers', route: 'masters.customers.index', icon: 'users' },
            { label: 'Staff', route: 'masters.staff.index', icon: 'user' },
        ],
    },
    {
        label: 'Inventory',
        icon: 'warehouse',
        can: hasAny('admin', 'stock_manager', 'production_manager'),
        links: [
            { label: 'Purchases', route: 'purchases.index', icon: 'cart', can: hasAny('admin', 'stock_manager') },
            { label: 'Purchase Returns', route: 'purchase-returns.index', icon: 'undo', can: hasAny('admin', 'stock_manager') },
            { label: 'Raw Material Stock', route: 'stock.index', icon: 'box', can: hasAny('admin', 'stock_manager') },
            { label: 'Part Stock', route: 'part-stock.index', icon: 'puzzle', can: hasAny('admin', 'stock_manager') },
            { label: 'Finished Goods', route: 'finished-goods.index', icon: 'package', can: hasAny('admin', 'production_manager') },
        ],
    },
    {
        label: 'Production',
        icon: 'factory',
        can: hasAny('admin', 'production_manager'),
        links: [
            { label: 'Cutting Batches', route: 'cutting-batches.index', icon: 'scissors' },
            { label: 'Work Orders', route: 'work-orders.index', icon: 'clipboard' },
            { label: 'Wages', route: 'wages.index', icon: 'cash' },
        ],
    },
    {
        label: 'Sales',
        icon: 'receipt',
        can: hasAny('admin', 'production_manager', 'cashier'),
        links: [
            { label: 'POS', route: 'sales.pos', icon: 'monitor' },
            { label: 'POS Sessions', route: 'sales.pos-sessions', icon: 'calendar' },
            { label: 'Sales Invoices', route: 'sales.index', icon: 'receipt' },
            { label: 'Sales Returns', route: 'sale-returns.index', icon: 'undo' },
        ],
    },
    {
        label: 'Accounting',
        icon: 'calculator',
        can: hasAny('admin', 'stock_manager', 'production_manager', 'accountant'),
        links: [
            { label: 'Overview', route: 'accounting.index', icon: 'chart' },
            { label: 'Daily Profit & Loss', route: 'accounting.daily-profit-loss', icon: 'calendar' },
            { label: 'Customer Due Invoices', route: 'accounting.customer-due-invoices', icon: 'users' },
            { label: 'Supplier Payables', route: 'accounting.supplier-payables', icon: 'truck' },
            { label: 'Wage Balances', route: 'accounting.wage-balances', icon: 'cash' },
            { label: 'Expenses', route: 'expenses.index', icon: 'receipt' },
            { label: 'Money Movement', route: 'accounting.money-movement', icon: 'swap' },
        ],
    },
    {
        label: 'Reports',
        icon: 'chart',
        can: hasAny('admin', 'stock_manager', 'production_manager', 'accountant'),
        links: [
            { label: 'Reports Overview', route: 'reports.index', icon: 'file-chart' },
            { label: 'Production Flow', route: 'reports.production-flow', icon: 'flow' },
            { label: 'Part In / Out', route: 'reports.part-flow', icon: 'swap' },
            { label: 'Finished Goods Flow', route: 'reports.finished-good-flow', icon: 'package' },
            { label: 'Sales By Product', route: 'reports.sales-by-product', icon: 'receipt' },
            { label: 'Work Order Status', route: 'reports.work-order-status', icon: 'clipboard' },
            { label: 'Stock Alerts', route: 'reports.stock-alerts', icon: 'alert' },
        ],
    },
    {
        label: 'Business Settings',
        icon: 'settings',
        can: hasAny('admin', 'production_manager'),
        links: [
            { label: 'General Settings', route: 'business-settings.general.edit', icon: 'settings' },
            { label: 'System Users', route: 'business-settings.system-users.index', icon: 'users', can: hasAny('admin') },
            { label: 'Costing & Pricing', route: 'costing.index', icon: 'calculator' },
            { label: 'Piece Rates', route: 'piece-rates.index', icon: 'cash' },
            { label: 'Production Stages', route: 'business-settings.production-stages.index', icon: 'flow' },
            { label: 'Cutting Yield Rules', route: 'business-settings.cutting-yield-rules.index', icon: 'scissors' },
            { label: 'Part Conversion Rules', route: 'business-settings.part-conversion-rules.index', icon: 'swap' },
            { label: 'Units of Measure', route: 'business-settings.unit-of-measures.index', icon: 'ruler' },
            { label: 'Payment Methods', route: 'business-settings.payment-methods.index', icon: 'cash' },
            { label: 'Expense Categories', route: 'business-settings.expense-categories.index', icon: 'tag' },
            { label: 'Product Categories', route: 'masters.product-categories.index', icon: 'tag' },
            { label: 'Product Sizes', route: 'masters.product-sizes.index', icon: 'ruler' },
            { label: 'Product Layers', route: 'masters.product-layers.index', icon: 'layers' },
            { label: 'Product Grades', route: 'masters.product-grades.index', icon: 'award' },
            { label: 'Designations', route: 'masters.designations.index', icon: 'badge' },
        ],
    },
]
    .map((group) => ({
        ...group,
        links: group.links.filter((link) => link.can ?? true),
    }))
    .filter((group) => group.can && group.links.length));

const isActive = (routeName) => route().current(routeName);
const groupKey = (group) => group.label.toLowerCase().replace(/\s+/g, '-');
const isGroupActive = (group) => group.links.some((link) => isActive(link.route));
const isGroupOpen = (group) => openGroups.value[groupKey(group)] ?? true;

function closeDrawer() {
    showingNavigationDrawer.value = false;
}

function toggleGroup(group) {
    const key = groupKey(group);
    openGroups.value[key] = !isGroupOpen(group);
    localStorage.setItem(sidebarGroupsKey, JSON.stringify(openGroups.value));
}

function applyTheme(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
}

function toggleTheme() {
    applyTheme(!isDark.value);
}

function toggleAccountMenu() {
    accountMenuOpen.value = !accountMenuOpen.value;
}

function closeAccountMenu() {
    accountMenuOpen.value = false;
}

function saveSidebarScroll() {
    const scrollTop = desktopNav.value?.scrollTop ?? mobileNav.value?.scrollTop ?? 0;
    sessionStorage.setItem(sidebarScrollKey, String(scrollTop));
}

function restoreSidebarScroll() {
    const scrollTop = Number(sessionStorage.getItem(sidebarScrollKey) ?? 0);
    if (desktopNav.value) {
        desktopNav.value.scrollTop = scrollTop;
    }
    if (mobileNav.value) {
        mobileNav.value.scrollTop = scrollTop;
    }
}

function handleAccountMenuClick(event) {
    if (!event.target?.closest?.('[data-account-menu]')) {
        closeAccountMenu();
    }
}

const initials = computed(() =>
    (user.value?.name ?? 'U')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
    const savedGroups = JSON.parse(localStorage.getItem(sidebarGroupsKey) || '{}');
    menuGroups.value.forEach((group) => {
        const key = groupKey(group);
        if (savedGroups[key] !== undefined) {
            openGroups.value[key] = savedGroups[key];
            return;
        }
        if (openGroups.value[key] === undefined) {
            openGroups.value[key] = true;
        }
    });
    document.addEventListener('mousedown', handleAccountMenuClick);
    nextTick(restoreSidebarScroll);
});

onBeforeUnmount(() => {
    saveSidebarScroll();
    document.removeEventListener('mousedown', handleAccountMenuClick);
});
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <aside
            v-if="!props.sidebarHidden"
            class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-gray-200 bg-white lg:flex lg:flex-col"
        >
            <div class="flex h-16 shrink-0 items-center border-b border-gray-100 px-6">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <ApplicationLogo class="block h-9 w-auto object-contain fill-current text-gray-800" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ appSettings.company_name || 'RainShield' }}</span>
                        <span class="block text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ appSettings.signature || 'By H to G' }}</span>
                    </span>
                </Link>
            </div>

            <nav ref="desktopNav" class="sidebar-scroll flex-1 overflow-y-auto px-4 py-5" @scroll.passive="saveSidebarScroll">
                <Link
                    :href="route('dashboard')"
                    class="mb-5 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="
                        isActive('dashboard')
                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200'
                            : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white'
                    "
                >
                    <AppIcon name="dashboard" />
                    Dashboard
                </Link>

                <div v-for="group in menuGroups" :key="group.label" class="mb-3">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                        :class="
                            isGroupActive(group)
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                : 'text-gray-400 hover:bg-gray-50 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200'
                        "
                        @click="toggleGroup(group)"
                    >
                        <span class="flex items-center gap-2">
                            <AppIcon :name="group.icon" />
                            {{ group.label }}
                        </span>
                        <svg
                            class="h-4 w-4 transition-transform"
                            :class="{ 'rotate-90': isGroupOpen(group) }"
                            stroke="currentColor"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    <div v-show="isGroupOpen(group)" class="mt-1 space-y-1 pl-3">
                        <Link
                            v-for="link in group.links"
                            :key="link.route"
                            :href="route(link.route)"
                            class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                isActive(link.route)
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200'
                                    : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white'
                            "
                        >
                            <AppIcon :name="link.icon" class="shrink-0" />
                            {{ link.label }}
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="border-t border-gray-100 p-4">
                <div class="relative" data-account-menu>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-md bg-gray-50 px-3 py-2 text-left transition hover:bg-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800"
                        @click="toggleAccountMenu"
                    >
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                            {{ initials }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
                        </span>
                        <AppIcon name="chevron-down" />
                    </button>

                    <div
                        v-if="accountMenuOpen"
                        class="absolute bottom-full left-0 z-50 mb-2 w-full overflow-hidden rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-950"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="toggleTheme(); closeAccountMenu()"
                        >
                            <AppIcon :name="isDark ? 'moon' : 'sun'" />
                            {{ isDark ? 'Dark theme' : 'Light theme' }}
                        </button>
                        <Link
                            :href="route('profile.edit')"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="closeAccountMenu"
                        >
                            <AppIcon name="user" />
                            Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="closeAccountMenu"
                        >
                            <AppIcon name="logout" />
                            Log Out
                        </Link>
                    </div>
                </div>
            </div>
        </aside>

        <div :class="props.sidebarHidden ? '' : 'lg:pl-72'">
            <div v-if="!props.sidebarHidden" class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:hidden">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <ApplicationLogo class="block h-8 w-auto object-contain fill-current text-gray-800" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ appSettings.company_name || 'RainShield' }}</span>
                        <span class="block text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ appSettings.signature || 'By H to G' }}</span>
                    </span>
                </Link>

                <button
                    type="button"
                    @click="showingNavigationDrawer = true"
                    class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none"
                >
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </button>
            </div>

            <div
                v-if="showingNavigationDrawer && !props.sidebarHidden"
                class="fixed inset-0 z-50 lg:hidden"
                role="dialog"
                aria-modal="true"
            >
                <div class="fixed inset-0 bg-gray-900/40" @click="closeDrawer" />
                <aside class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl">
                    <div class="flex h-16 items-center justify-between border-b border-gray-100 px-5">
                        <Link :href="route('dashboard')" class="flex items-center gap-3" @click="closeDrawer">
                            <ApplicationLogo class="block h-8 w-auto object-contain fill-current text-gray-800" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ appSettings.company_name || 'RainShield' }}</span>
                                <span class="block text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ appSettings.signature || 'By H to G' }}</span>
                            </span>
                        </Link>
                        <button
                            type="button"
                            @click="closeDrawer"
                            class="rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                        >
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <nav ref="mobileNav" class="sidebar-scroll flex-1 overflow-y-auto px-4 py-5" @scroll.passive="saveSidebarScroll">
                        <Link
                            :href="route('dashboard')"
                            class="mb-5 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                            :class="
                                isActive('dashboard')
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200'
                                    : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white'
                            "
                            @click="closeDrawer"
                        >
                            <AppIcon name="dashboard" />
                            Dashboard
                        </Link>

                        <div v-for="group in menuGroups" :key="group.label" class="mb-3">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-md px-3 py-2 text-xs font-semibold uppercase tracking-wide transition"
                                :class="
                                    isGroupActive(group)
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300'
                                        : 'text-gray-400 hover:bg-gray-50 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200'
                                "
                                @click="toggleGroup(group)"
                            >
                                <span class="flex items-center gap-2">
                                    <AppIcon :name="group.icon" />
                                    {{ group.label }}
                                </span>
                                <svg
                                    class="h-4 w-4 transition-transform"
                                    :class="{ 'rotate-90': isGroupOpen(group) }"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <div v-show="isGroupOpen(group)" class="mt-1 space-y-1 pl-3">
                                <Link
                                    v-for="link in group.links"
                                    :key="link.route"
                                    :href="route(link.route)"
                                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                                    :class="
                                        isActive(link.route)
                                            ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200'
                                            : 'text-gray-700 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-white'
                                    "
                                    @click="closeDrawer"
                                >
                                    <AppIcon :name="link.icon" class="shrink-0" />
                                    {{ link.label }}
                                </Link>
                            </div>
                        </div>
                    </nav>

                    <div class="border-t border-gray-100 p-4">
                        <div class="relative" data-account-menu>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md bg-gray-50 px-3 py-2 text-left dark:bg-gray-900"
                                @click="toggleAccountMenu"
                            >
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200">
                                    {{ initials }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ user.name }}</span>
                                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
                                </span>
                                <AppIcon name="chevron-down" />
                            </button>

                            <div v-if="accountMenuOpen" class="mt-2 overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="toggleTheme(); closeAccountMenu()"
                            >
                                <AppIcon :name="isDark ? 'moon' : 'sun'" />
                                {{ isDark ? 'Dark theme' : 'Light theme' }}
                            </button>
                            <Link
                                :href="route('profile.edit')"
                                class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="closeAccountMenu(); closeDrawer()"
                            >
                                <AppIcon name="user" />
                                Profile
                            </Link>
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="closeAccountMenu(); closeDrawer()"
                            >
                                <AppIcon name="logout" />
                                Log Out
                            </Link>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <header v-if="$slots.header" class="bg-white shadow">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>

            <footer v-if="!props.sidebarHidden" class="px-4 py-4 text-center text-[11px] font-medium text-gray-400 dark:text-gray-600">
                Powered By Home to Globe | version 1.2
            </footer>
        </div>

        <FlashToast />
    </div>
</template>
