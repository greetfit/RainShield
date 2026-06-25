<script setup>
import VariantAttributeIndex from '@/Pages/Masters/VariantAttributes/Index.vue';

defineProps({ layers: Array });
</script>

<template>
    <VariantAttributeIndex title="Product Layers" :items="layers" route-base="masters.product-layers" singular="Layer" />
</template>
