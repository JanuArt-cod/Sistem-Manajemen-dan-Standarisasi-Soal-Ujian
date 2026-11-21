<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    confirmText: {
        type: String,
        default: 'Konfirmasi',
    },
    cancelText: {
        type: String,
        default: 'Batal',
    },
    variant: {
        type: String,
        default: 'primary', // primary | danger
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'close']);

const handleKeydown = (e) => {
    if (e.key === 'Escape' && props.show && !props.processing) {
        emit('close');
    }
};

onMounted(() => document.addEventListener('keydown', handleKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown));
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
            <h3 class="text-base font-bold text-slate-900">
                {{ title }}
            </h3>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                {{ message }}
            </p>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('close')"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                >
                    {{ cancelText }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    @click="emit('confirm')"
                    class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :class="
                        variant === 'danger'
                            ? 'bg-rose-700 hover:bg-rose-800'
                            : 'bg-blue-800 hover:bg-blue-900'
                    "
                >
                    <span v-if="processing">Memproses...</span>
                    <span v-else>{{ confirmText }}</span>
                </button>
            </div>
        </div>
    </div>
</template>