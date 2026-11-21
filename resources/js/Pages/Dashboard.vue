<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PageHeader from '../Components/PageHeader.vue';
import StatCard from '../Components/StatCard.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    systemStatus: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.role === 'ADMIN');
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard" breadcrumb="SIMSOAL / Ringkasan">
        <PageHeader
            title="Ringkasan Sistem"
            description="Panel kerja internal administrasi dan standarisasi dokumen soal ujian sekolah."
        >
            <template v-if="isAdmin" #actions>
                <Link
                    href="/users"
                    class="inline-flex items-center rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Kelola Pengguna
                </Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <StatCard
                label="Pengguna Aktif"
                :value="stats.active_users"
                description="Total akun Admin dan Operator aktif"
            />
            <StatCard
                label="Operator Ujian"
                :value="stats.operator_count"
                description="Petugas pengelola naskah soal"
            />
            <StatCard
                label="Administrator"
                :value="stats.admin_count"
                description="Pengelola konfigurasi dan hak akses"
            />
        </div>

        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-base font-bold text-slate-900">
                Konfigurasi Standar Aktif
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Parameter bawaan yang dikunci untuk pemrosesan dokumen ujian pada SIMSOAL.
            </p>

            <dl class="mt-4 grid grid-cols-1 gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3 text-sm">
                <div>
                    <dt class="text-xs font-medium text-slate-500">Ukuran Halaman Default</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ systemStatus.default_paper }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">Tipografi Default</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ systemStatus.default_font }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-slate-500">Driver Antrean Proses</dt>
                    <dd class="mt-1 font-semibold text-slate-900 uppercase">{{ systemStatus.queue_driver }}</dd>
                </div>
            </dl>
        </div>
    </AppLayout>
</template>