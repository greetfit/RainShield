<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: 'This action cannot be undone.' },
    confirmText: { type: String, default: 'Delete' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" @close="emit('cancel')" max-width="md">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">{{ title }}</h2>
            <p class="mt-2 text-sm text-gray-600">{{ message }}</p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="emit('cancel')">Cancel</SecondaryButton>
                <DangerButton
                    :class="{ 'opacity-50': processing }"
                    :disabled="processing"
                    @click="emit('confirm')"
                >
                    {{ confirmText }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
