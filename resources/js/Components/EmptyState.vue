<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
        <h3 class="text-sm font-semibold text-slate-900">
            {{ title }}
        </h3>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>