<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const page = usePage();
const visible = ref(false);
const currentType = ref('success');
const currentMessage = ref('');
let timer = null;

const flash = computed(() => page.props.flash || {});

const hide = () => {
    visible.value = false;
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
};

const showToast = (type, message) => {
    if (!message) return;
    currentType.value = type;
    currentMessage.value = message;
    visible.value = true;

    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        visible.value = false;
    }, 5000);
};

watch(
    () => [flash.value.success, flash.value.error, flash.value.warning],
    ([success, error, warning]) => {
        if (error) {
            showToast('error', error);
        } else if (warning) {
            showToast('warning', warning);
        } else if (success) {
            showToast('success', success);
        }
    },
    { immediate: true }
);

onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});

const stylesByType = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    error: 'border-rose-200 bg-rose-50 text-rose-900',
};

const badgeByType = {
    success: 'Berhasil',
    warning: 'Perhatian',
    error: 'Gagal',
};
</script>

<template>
    <div
        v-if="visible && currentMessage"
        role="status"
        aria-live="polite"
        class="fixed bottom-5 right-5 z-50 max-w-md w-full rounded-xl border px-4 py-3.5 shadow-lg transition"
        :class="stylesByType[currentType]"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="text-sm">
                <p class="font-semibold">{{ badgeByType[currentType] }}</p>
                <p class="mt-0.5 leading-snug">{{ currentMessage }}</p>
            </div>
            <button
                type="button"
                @click="hide"
                class="rounded-md px-1.5 py-0.5 text-xs font-semibold opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-slate-500"
                aria-label="Tutup notifikasi"
            >
                Tutup
            </button>
        </div>
    </div>
</template>