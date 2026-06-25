<script setup>
import { useAttrs } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const page = usePage();
</script>

<template>
    <img
        v-if="page.props.appSettings?.company_logo_url"
        :src="page.props.appSettings.company_logo_url"
        :alt="page.props.appSettings.company_name"
        v-bind="attrs"
        :class="attrs.class"
    />
    <template v-else>
        <img
            src="/images/logo-small.png"
            alt="Rain Shield"
            v-bind="attrs"
            :class="[attrs.class, 'dark:hidden']"
        />
        <img
            src="/images/logo-dark.png"
            alt="Rain Shield"
            v-bind="attrs"
            :class="[attrs.class, 'hidden dark:block']"
        />
    </template>
</template>
