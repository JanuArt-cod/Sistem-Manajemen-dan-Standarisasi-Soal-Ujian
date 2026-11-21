<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ label }}
        </p>
        <p class="mt-2 text-2xl font-bold text-slate-900">
            {{ value }}
        </p>
        <p v-if="description" class="mt-1 text-xs text-slate-500">
            {{ description }}
        </p>
    </div>
</template>