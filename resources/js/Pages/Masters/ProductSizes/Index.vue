<script setup>
import VariantAttributeIndex from '@/Pages/Masters/VariantAttributes/Index.vue';

defineProps({ sizes: Array });
</script>

<template>
    <VariantAttributeIndex title="Product Sizes" :items="sizes" route-base="masters.product-sizes" singular="Size" />
</template>
