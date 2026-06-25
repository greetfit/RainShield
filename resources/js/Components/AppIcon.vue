<script setup>
defineProps({
    name: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <svg
        class="h-4 w-4"
        fill="none"
        stroke="currentColor"
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        viewBox="0 0 24 24"
        aria-hidden="true"
    >
        <template v-if="name === 'edit'">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </template>
        <template v-else-if="name === 'trash'">
            <path d="M3 6h18" />
            <path d="M8 6V4h8v2" />
            <path d="M19 6l-1 14H6L5 6" />
            <path d="M10 11v5" />
            <path d="M14 11v5" />
        </template>
        <template v-else-if="name === 'sun'">
            <circle cx="12" cy="12" r="4" />
            <path d="M12 2v2" />
            <path d="M12 20v2" />
            <path d="m4.93 4.93 1.41 1.41" />
            <path d="m17.66 17.66 1.41 1.41" />
            <path d="M2 12h2" />
            <path d="M20 12h2" />
            <path d="m6.34 17.66-1.41 1.41" />
            <path d="m19.07 4.93-1.41 1.41" />
        </template>
        <template v-else-if="name === 'moon'">
            <path d="M20.99 13.2A8.5 8.5 0 1 1 10.8 3.01 6.5 6.5 0 0 0 20.99 13.2Z" />
        </template>
        <template v-else-if="name === 'user'">
            <path d="M20 21a8 8 0 0 0-16 0" />
            <circle cx="12" cy="7" r="4" />
        </template>
        <template v-else-if="name === 'logout'">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="M16 17l5-5-5-5" />
            <path d="M21 12H9" />
        </template>
        <template v-else-if="name === 'cash'">
            <rect x="3" y="6" width="18" height="12" rx="2" />
            <circle cx="12" cy="12" r="2" />
            <path d="M7 10v4" />
            <path d="M17 10v4" />
        </template>
        <template v-else-if="name === 'undo'">
            <path d="M9 14 4 9l5-5" />
            <path d="M4 9h10a6 6 0 0 1 0 12h-1" />
        </template>
        <template v-else-if="name === 'recipe'">
            <path d="M8 3h8" />
            <path d="M9 3v5l-4.5 8A3.5 3.5 0 0 0 7.55 21h8.9a3.5 3.5 0 0 0 3.05-5L15 8V3" />
            <path d="M8 14h8" />
        </template>
        <template v-else-if="name === 'check'">
            <path d="m20 6-11 11-5-5" />
        </template>
        <template v-else-if="name === 'x'">
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
        </template>
        <template v-else-if="name === 'eye'">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
        </template>
        <template v-else-if="name === 'eye-off'">
            <path d="M3 3l18 18" />
            <path d="M10.6 10.6A2 2 0 0 0 13.4 13.4" />
            <path d="M9.9 5.2A9.7 9.7 0 0 1 12 5c6.5 0 10 7 10 7a17.8 17.8 0 0 1-3.1 4.2" />
            <path d="M6.1 6.7A17.4 17.4 0 0 0 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4-.8" />
        </template>
        <template v-else-if="name === 'chevron-down'">
            <path d="m6 9 6 6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-left'">
            <path d="m15 18-6-6 6-6" />
        </template>
        <template v-else-if="name === 'chevron-right'">
            <path d="m9 18 6-6-6-6" />
        </template>
        <template v-else-if="name === 'calendar'">
            <path d="M8 2v4" />
            <path d="M16 2v4" />
            <rect x="3" y="4" width="18" height="18" rx="2" />
            <path d="M3 10h18" />
        </template>
        <template v-else-if="name === 'more-horizontal'">
            <circle cx="5" cy="12" r="1" />
            <circle cx="12" cy="12" r="1" />
            <circle cx="19" cy="12" r="1" />
        </template>
        <template v-else-if="name === 'copy'">
            <rect x="8" y="8" width="12" height="12" rx="2" />
            <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2" />
        </template>
        <template v-else-if="name === 'print'">
            <path d="M6 9V2h12v7" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <path d="M6 14h12v8H6z" />
        </template>
        <template v-else-if="name === 'receipt'">
            <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z" />
            <path d="M8 7h8" />
            <path d="M8 11h8" />
            <path d="M8 15h5" />
        </template>
        <template v-else-if="name === 'phone'">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.6 2.6a2 2 0 0 1-.45 2.11L8 9.69a16 16 0 0 0 6.31 6.31l1.26-1.26a2 2 0 0 1 2.11-.45c.83.28 1.7.48 2.6.6A2 2 0 0 1 22 16.92Z" />
        </template>
        <template v-else-if="name === 'message'">
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
        </template>
        <template v-else-if="name === 'alert'">
            <path d="m10.29 3.86-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.71-3.14l-8-14a2 2 0 0 0-3.42 0Z" />
            <path d="M12 9v4" />
            <path d="M12 17h.01" />
        </template>
        <template v-else-if="name === 'dashboard'">
            <rect x="3" y="3" width="7" height="8" rx="1" />
            <rect x="14" y="3" width="7" height="5" rx="1" />
            <rect x="14" y="12" width="7" height="9" rx="1" />
            <rect x="3" y="15" width="7" height="6" rx="1" />
        </template>
        <template v-else-if="name === 'layers'">
            <path d="m12 2 9 5-9 5-9-5 9-5Z" />
            <path d="m3 12 9 5 9-5" />
            <path d="m3 17 9 5 9-5" />
        </template>
        <template v-else-if="name === 'package'">
            <path d="m16.5 9.4-9-5.2" />
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
            <path d="m3.3 7 8.7 5 8.7-5" />
            <path d="M12 22V12" />
        </template>
        <template v-else-if="name === 'box'">
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M3 11h18" />
            <path d="M8 7V5h8v2" />
        </template>
        <template v-else-if="name === 'puzzle'">
            <path d="M9 3h6v4h3a3 3 0 0 1 0 6h-3v8H9v-4H6a3 3 0 0 1 0-6h3V3Z" />
        </template>
        <template v-else-if="name === 'truck'">
            <path d="M3 6h11v9H3z" />
            <path d="M14 9h4l3 3v3h-7" />
            <circle cx="7" cy="18" r="2" />
            <circle cx="18" cy="18" r="2" />
        </template>
        <template v-else-if="name === 'users'">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </template>
        <template v-else-if="name === 'warehouse'">
            <path d="M3 21V8l9-5 9 5v13" />
            <path d="M7 21v-8h10v8" />
            <path d="M7 13h10" />
            <path d="M7 17h10" />
        </template>
        <template v-else-if="name === 'cart'">
            <circle cx="9" cy="20" r="1" />
            <circle cx="18" cy="20" r="1" />
            <path d="M2 3h3l3 12h10l3-8H7" />
        </template>
        <template v-else-if="name === 'factory'">
            <path d="M3 21V9l6 4V9l6 4V5h6v16" />
            <path d="M3 21h18" />
            <path d="M7 17h1" />
            <path d="M12 17h1" />
            <path d="M17 17h1" />
        </template>
        <template v-else-if="name === 'scissors'">
            <circle cx="6" cy="6" r="3" />
            <circle cx="6" cy="18" r="3" />
            <path d="M20 4 8.5 15.5" />
            <path d="M8.5 8.5 20 20" />
        </template>
        <template v-else-if="name === 'clipboard'">
            <rect x="5" y="4" width="14" height="17" rx="2" />
            <path d="M9 4a3 3 0 0 1 6 0" />
            <path d="M9 9h6" />
            <path d="M9 13h6" />
            <path d="M9 17h3" />
        </template>
        <template v-else-if="name === 'monitor'">
            <rect x="3" y="4" width="18" height="12" rx="2" />
            <path d="M8 20h8" />
            <path d="M12 16v4" />
        </template>
        <template v-else-if="name === 'maximize'">
            <path d="M8 3H5a2 2 0 0 0-2 2v3" />
            <path d="M16 3h3a2 2 0 0 1 2 2v3" />
            <path d="M8 21H5a2 2 0 0 1-2-2v-3" />
            <path d="M16 21h3a2 2 0 0 0 2-2v-3" />
        </template>
        <template v-else-if="name === 'minimize'">
            <path d="M8 3v3a2 2 0 0 1-2 2H3" />
            <path d="M16 3v3a2 2 0 0 0 2 2h3" />
            <path d="M8 21v-3a2 2 0 0 0-2-2H3" />
            <path d="M16 21v-3a2 2 0 0 1 2-2h3" />
        </template>
        <template v-else-if="name === 'panel-left'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M9 4v16" />
            <path d="m14 9-3 3 3 3" />
        </template>
        <template v-else-if="name === 'panel-right'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M15 4v16" />
            <path d="m10 9 3 3-3 3" />
        </template>
        <template v-else-if="name === 'calculator'">
            <rect x="4" y="2" width="16" height="20" rx="2" />
            <path d="M8 6h8" />
            <path d="M8 10h.01" />
            <path d="M12 10h.01" />
            <path d="M16 10h.01" />
            <path d="M8 14h.01" />
            <path d="M12 14h.01" />
            <path d="M16 14h.01" />
            <path d="M8 18h.01" />
            <path d="M12 18h.01" />
            <path d="M16 18h.01" />
        </template>
        <template v-else-if="name === 'chart'">
            <path d="M3 3v18h18" />
            <path d="M7 15v2" />
            <path d="M12 10v7" />
            <path d="M17 6v11" />
        </template>
        <template v-else-if="name === 'swap'">
            <path d="M16 3h5v5" />
            <path d="M21 3 14 10" />
            <path d="M8 21H3v-5" />
            <path d="M3 21l7-7" />
        </template>
        <template v-else-if="name === 'file-chart'">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6" />
            <path d="M8 17v-3" />
            <path d="M12 17v-6" />
            <path d="M16 17v-4" />
        </template>
        <template v-else-if="name === 'settings'">
            <circle cx="12" cy="12" r="3" />
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 8.6 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 8.6a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.31.5.6 1 .6h.6a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1.4Z" />
        </template>
        <template v-else-if="name === 'flow'">
            <rect x="3" y="4" width="6" height="6" rx="1" />
            <rect x="15" y="4" width="6" height="6" rx="1" />
            <rect x="9" y="15" width="6" height="6" rx="1" />
            <path d="M9 7h6" />
            <path d="M12 10v5" />
        </template>
        <template v-else-if="name === 'tag'">
            <path d="M20.59 13.41 12 22l-9-9V4h9l8.59 8.59a2 2 0 0 1 0 2.82Z" />
            <circle cx="7.5" cy="8.5" r=".5" />
        </template>
        <template v-else-if="name === 'ruler'">
            <path d="m16 2 6 6L8 22l-6-6Z" />
            <path d="m7 15 2 2" />
            <path d="m10 12 2 2" />
            <path d="m13 9 2 2" />
        </template>
        <template v-else-if="name === 'award'">
            <circle cx="12" cy="8" r="6" />
            <path d="M15.5 13 17 22l-5-3-5 3 1.5-9" />
        </template>
        <template v-else-if="name === 'badge'">
            <rect x="4" y="3" width="16" height="18" rx="2" />
            <circle cx="12" cy="9" r="3" />
            <path d="M8 17a4 4 0 0 1 8 0" />
        </template>
    </svg>
</template>
