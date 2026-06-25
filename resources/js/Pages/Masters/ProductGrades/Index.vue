<script setup>
import VariantAttributeIndex from '@/Pages/Masters/VariantAttributes/Index.vue';

defineProps({ grades: Array });
</script>

<template>
    <VariantAttributeIndex title="Product Grades" :items="grades" route-base="masters.product-grades" singular="Grade" />
</template>
