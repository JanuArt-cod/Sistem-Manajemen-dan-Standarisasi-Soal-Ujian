<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '../Components/EmptyState.vue';
import PageHeader from '../Components/PageHeader.vue';
import StatCard from '../Components/StatCard.vue';
import StatusBadge from '../Components/StatusBadge.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    activePeriods: {
        type: Array,
        required: true,
    },
    recentDocuments: {
        type: Array,
        required: true,
    },
    schoolConfigured: {
        type: Boolean,
        default: false,
    },
    schoolName: {
        type: String,
        default: null,
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
            <template #actions>
                <Link
                    href="/exam-periods"
                    class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                >
                    Buat Periode
                </Link>
                <Link
                    href="/exam-documents/create"
                    class="inline-flex items-center rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Upload Soal
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="!schoolConfigured"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="font-semibold">Profil sekolah belum dikonfigurasi</p>
                <p class="mt-0.5 text-xs text-amber-800">
                    Identitas sekolah diperlukan untuk penyisipan kop surat pada halaman pertama naskah ujian.
                </p>
            </div>
            <Link
                v-if="isAdmin"
                href="/school-profile"
                class="shrink-0 rounded-lg bg-amber-800 px-3.5 py-2 text-xs font-semibold text-white hover:bg-amber-900"
            >
                Atur Profil Sekolah
            </Link>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Periode Aktif"
                :value="stats.active_periods"
                description="Periode ujian siap menerima soal"
            />
            <StatCard
                label="Total Dokumen"
                :value="stats.total_documents"
                description="Naskah soal terdaftar di sistem"
            />
            <StatCard
                label="Perlu Pemeriksaan"
                :value="stats.needs_review_documents"
                description="Dokumen menunggu verifikasi operator"
            />
            <StatCard
                label="Disetujui"
                :value="stats.approved_documents"
                description="Dokumen final siap cetak/arsip"
            />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <!-- Dokumen Terbaru -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Dokumen Soal Terbaru</h2>
                            <p class="text-xs text-slate-500">Naskah soal yang terakhir diunggah ke penyimpanan privat.</p>
                        </div>
                        <Link href="/exam-documents" class="text-xs font-semibold text-blue-800 hover:underline">
                            Lihat Semua
                        </Link>
                    </div>

                    <div v-if="recentDocuments.length > 0" class="divide-y divide-slate-100">
                        <div
                            v-for="doc in recentDocuments"
                            :key="doc.id"
                            class="flex items-center justify-between py-3 text-sm"
                        >
                            <div>
                                <Link
                                    :href="`/exam-documents/${doc.id}`"
                                    class="font-semibold text-slate-900 hover:text-blue-800"
                                >
                                    {{ doc.title }}
                                </Link>
                                <p class="text-xs text-slate-500">
                                    {{ doc.subject_name }} • {{ doc.class_name }} • Guru: {{ doc.teacher_name }}
                                </p>
                            </div>
                            <StatusBadge :status="doc.status" :label="doc.status_label" />
                        </div>
                    </div>

                    <EmptyState
                        v-else
                        title="Belum ada dokumen soal diunggah"
                        description="Mulai dengan mengunggah file .docx dari guru ke dalam periode ujian yang aktif."
                    >
                        <template #action>
                            <Link
                                href="/exam-documents/create"
                                class="inline-flex rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900"
                            >
                                Upload Dokumen Soal
                            </Link>
                        </template>
                    </EmptyState>
                </div>

                <!-- Periode Ujian Aktif -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Periode Ujian Aktif</h2>
                            <p class="text-xs text-slate-500">Daftar periode yang sedang berjalan.</p>
                        </div>
                        <Link href="/exam-periods" class="text-xs font-semibold text-blue-800 hover:underline">
                            Kelola Periode
                        </Link>
                    </div>

                    <div v-if="activePeriods.length > 0" class="divide-y divide-slate-100">
                        <div
                            v-for="period in activePeriods"
                            :key="period.id"
                            class="flex items-center justify-between py-3 text-sm"
                        >
                            <div>
                                <p class="font-semibold text-slate-900">{{ period.name }}</p>
                                <p class="text-xs text-slate-500">
                                    TP {{ period.academic_year }} • Semester {{ period.semester_label }} • {{ period.date_range }}
                                </p>
                            </div>
                            <StatusBadge status="ACTIVE" />
                        </div>
                    </div>

                    <p v-else class="text-xs text-slate-500">
                        Belum ada periode ujian berstatus Aktif.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs h-fit">
                <h2 class="text-base font-bold text-slate-900">Konfigurasi Standar</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Parameter default pemrosesan dokumen SIMSOAL.
                </p>

                <dl class="mt-4 space-y-3 border-t border-slate-100 pt-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Institusi Sekolah</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">
                            {{ schoolName || 'Belum diatur' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Ukuran Halaman Default</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ systemStatus.default_paper }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Tipografi Default</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ systemStatus.default_font }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Posisi Kop Surat</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">Halaman Pertama Saja</dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>