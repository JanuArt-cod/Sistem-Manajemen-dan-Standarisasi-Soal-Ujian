<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        default: '',
    },
});

const statusConfig = {
    ACTIVE: {
        text: 'Aktif',
        classes: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        dot: 'bg-emerald-600',
    },
    INACTIVE: {
        text: 'Nonaktif',
        classes: 'bg-slate-100 text-slate-700 border-slate-300',
        dot: 'bg-slate-500',
    },
    ADMIN: {
        text: 'Administrator',
        classes: 'bg-blue-50 text-blue-800 border-blue-200',
        dot: 'bg-blue-700',
    },
    OPERATOR: {
        text: 'Operator Ujian',
        classes: 'bg-indigo-50 text-indigo-800 border-indigo-200',
        dot: 'bg-indigo-600',
    },
    APPROVED: {
        text: 'Disetujui',
        classes: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        dot: 'bg-emerald-600',
    },
    NEEDS_REVIEW: {
        text: 'Perlu Pemeriksaan',
        classes: 'bg-amber-50 text-amber-800 border-amber-200',
        dot: 'bg-amber-600',
    },
    FAILED: {
        text: 'Gagal',
        classes: 'bg-rose-50 text-rose-800 border-rose-200',
        dot: 'bg-rose-600',
    },
};

const resolved = computed(() => {
    const key = props.status?.toUpperCase();
    return (
        statusConfig[key] || {
            text: props.label || props.status,
            classes: 'bg-slate-100 text-slate-700 border-slate-300',
            dot: 'bg-slate-500',
        }
    );
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-0.5 text-xs font-semibold"
        :class="resolved.classes"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="resolved.dot"></span>
        <span>{{ label || resolved.text }}</span>
    </span>
</template>