<script setup>
import Modal from '@/Components/Modal.vue';

defineProps({ show: Boolean, title: String, maxWidth: { type: String, default: '2xl' } });
const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="emit('close')">
        <div class="flex items-center justify-between border-b px-5 py-3">
            <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="emit('close')">✕</button>
        </div>
        <div class="max-h-[75vh] overflow-y-auto p-5">
            <slot />
        </div>
        <div v-if="$slots.footer" class="flex justify-end gap-2 border-t bg-gray-50 px-5 py-3">
            <slot name="footer" />
        </div>
    </Modal>
</template>
