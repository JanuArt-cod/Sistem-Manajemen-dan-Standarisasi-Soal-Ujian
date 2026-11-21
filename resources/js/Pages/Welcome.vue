<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        default: 'SIMSOAL',
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Inisialisasi Sistem" />

    <div class="min-h-screen flex items-center justify-center bg-slate-50 px-4">
        <div class="max-w-lg w-full bg-white border border-slate-200 rounded-xl shadow-sm p-8">
            <div class="flex items-center justify-between mb-4">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                    Milestone 1 — Foundation
                </span>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    Sistem Aktif
                </span>
            </div>

            <h1 class="text-xl font-bold text-slate-900">
                {{ appName }} — Sistem Manajemen & Standarisasi Soal Ujian
            </h1>
            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                Integrasi Laravel 12, Inertia.js, Vue 3, dan Tailwind CSS berhasil dikonfigurasi.
            </p>

            <div class="mt-6 border-t border-slate-100 pt-4 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="text-xs text-slate-500">Laravel Framework</div>
                    <div class="font-semibold text-slate-800">v{{ laravelVersion }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">PHP Runtime</div>
                    <div class="font-semibold text-slate-800">v{{ phpVersion }}</div>
                </div>
            </div>
        </div>
    </div>
</template>