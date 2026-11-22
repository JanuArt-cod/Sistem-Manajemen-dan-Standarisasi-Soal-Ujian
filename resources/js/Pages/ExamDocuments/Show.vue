<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import DocumentProcessingConfigCard from '../../Components/DocumentProcessingConfigCard.vue';
import DocumentReviewCard from '../../Components/DocumentReviewCard.vue';
import PageHeader from '../../Components/PageHeader.vue';
import PdfPreviewCard from '../../Components/PdfPreviewCard.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    subjects: {
        type: Array,
        required: true,
    },
    schoolClasses: {
        type: Array,
        required: true,
    },
    presets: {
        type: Array,
        required: true,
    },
    templates: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    document_title: props.document.document_title || '',
    teacher_name: props.document.teacher_name || '',
    subject_id: props.document.subject_id || '',
    school_class_id: props.document.school_class_id || '',
    exam_date: props.document.exam_date || '',
    duration_minutes: props.document.duration_minutes || '',
    notes: props.document.notes || '',
});

const processForm = useForm({});
const pdfForm = useForm({});
const deleteForm = useForm({});
const showDeleteDialog = ref(false);

const submit = () => {
    form.put(`/exam-documents/${props.document.id}`, {
        preserveScroll: true,
    });
};

const runStandardization = () => {
    processForm.post(`/exam-documents/${props.document.id}/process`, {
        preserveScroll: true,
    });
};

const retryPdfExport = () => {
    pdfForm.post(`/exam-documents/${props.document.id}/export-pdf`, {
        preserveScroll: true,
    });
};

const confirmDelete = () => {
    deleteForm.delete(`/exam-documents/${props.document.id}`, {
        onFinish: () => {
            showDeleteDialog.value = false;
        },
    });
};

const runStatusClass = (status) => {
    switch (status) {
        case 'COMPLETED':
            return 'bg-emerald-50 text-emerald-800 border-emerald-200';
        case 'COMPLETED_WITH_WARNINGS':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'FAILED':
            return 'bg-rose-50 text-rose-800 border-rose-200';
        default:
            return 'bg-sky-50 text-sky-800 border-sky-200';
    }
};
</script>

<template>
    <Head :title="document.document_title || 'Detail Dokumen'" />

    <AppLayout title="Detail Dokumen Soal" breadcrumb="SIMSOAL / Dokumen Soal / Detail">
        <PageHeader
            :title="document.document_title || 'Detail Dokumen Soal'"
            :description="`Periode: ${document.period.name} (TP ${document.period.academic_year})`"
        >
            <template #actions>
                <StatusBadge :status="document.current_status" :label="document.status_label" />
                <a
                    v-if="document.output_pdf_file"
                    :href="document.output_pdf_file.download_url"
                    class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 transition"
                >
                    Unduh Hasil PDF
                </a>
                <a
                    v-if="document.output_docx_file"
                    :href="document.output_docx_file.download_url"
                    class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 transition"
                >
                    Unduh Hasil DOCX
                </a>
                <a
                    v-if="document.source_file"
                    :href="document.source_file.download_url"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    Unduh Sumber Asli
                </a>
                <button
                    v-if="document.can_delete"
                    type="button"
                    @click="showDeleteDialog = true"
                    class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 transition"
                >
                    Hapus Dokumen
                </button>
                <Link
                    href="/exam-documents"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Kembali
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="document.duplicate_warning"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"
        >
            <p class="font-semibold">Peringatan Duplikasi File Sumber</p>
            <p class="mt-0.5 text-xs text-amber-800">
                Checksum SHA-256 file ini identik dengan dokumen lain pada periode ujian yang sama. Pastikan dokumen tidak diunggah ganda secara tidak sengaja.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Kolom Utama Kiri (2 Kolom) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Konfigurasi Snapshot Preset & Kop Halaman Pertama -->
                <DocumentProcessingConfigCard
                    :exam-document-id="document.id"
                    :current-config="document.processing_config"
                    :presets="presets"
                    :templates="templates"
                    :can-configure="document.can_edit"
                />

                <!-- Panel Eksekusi & Hasil Standarisasi DOCX & PDF -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Standarisasi Dokumen DOCX & Ekspor PDF</h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                Menstandarisasi ukuran kertas F4 (215 × 330 mm), margin, tipografi Times New Roman 12 pt, kop halaman pertama, serta mengekspor PDF siap cetak via LibreOffice.
                            </p>
                        </div>

                        <button
                            v-if="document.can_process"
                            type="button"
                            :disabled="processForm.processing"
                            @click="runStandardization"
                            class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50 transition"
                        >
                            <span v-if="processForm.processing">Memproses DOCX & PDF...</span>
                            <span v-else>{{ document.output_docx_file ? 'Proses Ulang Standarisasi' : 'Proses Standarisasi DOCX & PDF' }}</span>
                        </button>
                    </div>

                    <!-- Status & Statistik Proses Terakhir -->
                    <div
                        v-if="document.latest_run"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 space-y-3"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md border px-2.5 py-0.5 text-xs font-semibold"
                                    :class="runStatusClass(document.latest_run.status)"
                                >
                                    {{ document.latest_run.status_label }}
                                </span>
                                <span
                                    v-if="document.latest_run.pdf_status === 'COMPLETED'"
                                    class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-800"
                                >
                                    PDF Siap
                                </span>
                                <span
                                    v-else-if="document.latest_run.pdf_status === 'FAILED'"
                                    class="rounded-md border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-800"
                                >
                                    Ekspor PDF Gagal
                                </span>
                                <span
                                    v-if="document.latest_run.needs_manual_review"
                                    class="rounded-md border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-800"
                                >
                                    Perlu Tinjauan Manual
                                </span>
                            </div>
                            <span class="text-xs text-slate-500">
                                {{ document.latest_run.finished_at || document.latest_run.created_at }}
                                <template v-if="document.latest_run.duration_ms !== null">
                                    • {{ document.latest_run.duration_ms }} ms
                                </template>
                            </span>
                        </div>

                        <div
                            v-if="document.latest_run.error_message"
                            class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800"
                        >
                            <p class="font-semibold">Pesan Kesalahan:</p>
                            <p class="mt-0.5">{{ document.latest_run.error_message }}</p>
                        </div>

                        <div
                            v-if="document.latest_run.pdf_error_message && document.latest_run.error_message !== document.latest_run.pdf_error_message"
                            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
                        >
                            <p class="font-semibold">Catatan Ekspor PDF:</p>
                            <p class="mt-0.5">{{ document.latest_run.pdf_error_message }}</p>
                        </div>

                        <div
                            v-if="document.latest_run.metrics && Object.keys(document.latest_run.metrics).length > 0"
                            class="grid grid-cols-2 gap-2 sm:grid-cols-4 text-xs"
                        >
                            <div class="rounded-md border border-slate-200 bg-white p-2.5">
                                <span class="text-slate-500 block">Paragraf Naskah</span>
                                <span class="font-bold text-slate-900">
                                    {{ document.latest_run.metrics.input_non_empty_paragraph_count ?? 0 }}
                                </span>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white p-2.5">
                                <span class="text-slate-500 block">Tabel Soal</span>
                                <span class="font-bold text-slate-900">
                                    {{ document.latest_run.metrics.input_table_count ?? 0 }}
                                </span>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white p-2.5">
                                <span class="text-slate-500 block">Gambar Inline</span>
                                <span class="font-bold text-slate-900">
                                    {{ document.latest_run.metrics.input_image_count ?? 0 }}
                                </span>
                            </div>
                            <div class="rounded-md border border-slate-200 bg-white p-2.5">
                                <span class="text-slate-500 block">Rumus OMML</span>
                                <span class="font-bold text-slate-900">
                                    {{ document.latest_run.metrics.input_equation_count ?? 0 }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-0.5 text-[11px] text-slate-500">
                            <p v-if="document.latest_run.engine_name">
                                Mesin DOCX: {{ document.latest_run.engine_name }} v{{ document.latest_run.engine_version }}
                                (Python {{ document.latest_run.python_version }}, python-docx {{ document.latest_run.docx_library_version }})
                            </p>
                            <p v-if="document.latest_run.libreoffice_version">
                                Konverter PDF: {{ document.latest_run.libreoffice_version }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-600"
                    >
                        Dokumen ini belum pernah diproses. Klik <strong>Proses Standarisasi DOCX & PDF</strong> untuk menghasilkan versi terstandarisasi F4.
                    </div>
                </div>

                <!-- Pratinjau PDF Inline Terotorisasi -->
                <PdfPreviewCard
                    :pdf-file="document.output_pdf_file"
                    :current-status="document.current_status"
                />

                <!-- Pemeriksaan Peringatan & Keputusan Persetujuan / Revisi -->
                <DocumentReviewCard :document="document" />

                <!-- Form Edit Metadata -->
                <form
                    @submit.prevent="submit"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4"
                >
                    <h2 class="text-base font-bold text-slate-900">Metadata Naskah Soal</h2>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Judul Naskah Soal</label>
                        <input
                            v-model="form.document_title"
                            type="text"
                            required
                            :disabled="!document.can_edit"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                        />
                        <p v-if="form.errors.document_title" class="mt-1 text-xs text-rose-600">{{ form.errors.document_title }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Guru Penyusun</label>
                            <input
                                v-model="form.teacher_name"
                                type="text"
                                required
                                :disabled="!document.can_edit"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                            />
                            <p v-if="form.errors.teacher_name" class="mt-1 text-xs text-rose-600">{{ form.errors.teacher_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mata Pelajaran</label>
                            <select
                                v-model="form.subject_id"
                                :disabled="!document.can_edit"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                            >
                                <option value="">Belum ditentukan</option>
                                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Rombongan Kelas</label>
                            <select
                                v-model="form.school_class_id"
                                :disabled="!document.can_edit"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                            >
                                <option value="">Belum ditentukan</option>
                                <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Ujian</label>
                            <input
                                v-model="form.exam_date"
                                type="date"
                                :disabled="!document.can_edit"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Durasi Ujian (Menit)</label>
                            <input
                                v-model="form.duration_minutes"
                                type="number"
                                min="1"
                                max="600"
                                :disabled="!document.can_edit"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Catatan Operator</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            :disabled="!document.can_edit"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm disabled:bg-slate-100"
                        />
                    </div>

                    <div v-if="document.can_edit" class="border-t border-slate-100 pt-4 flex justify-end">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-blue-800 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                        >
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>Simpan Perubahan Metadata</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Kolom Samping Kanan: Arsip File & Riwayat -->
            <div class="space-y-6">
                <!-- Kartu Hasil Cetak PDF -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900">Hasil Cetak PDF</h2>
                        <span
                            v-if="document.output_pdf_file"
                            class="rounded bg-rose-50 border border-rose-200 px-2 py-0.5 text-[11px] font-semibold text-rose-800"
                        >
                            PDF F4 Siap
                        </span>
                        <span
                            v-else-if="document.current_status === 'PDF_FAILED'"
                            class="rounded bg-amber-50 border border-amber-200 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                        >
                            Ekspor Gagal
                        </span>
                    </div>

                    <div v-if="document.output_pdf_file" class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Nama File PDF</dt>
                            <dd class="mt-0.5 font-semibold text-slate-900 break-all">
                                {{ document.output_pdf_file.original_filename }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Ukuran & Waktu Selesai</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{ document.output_pdf_file.human_size }} • {{ document.output_pdf_file.created_at }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Checksum SHA-256 PDF</dt>
                            <dd class="mt-0.5 font-mono text-[11px] text-slate-600 break-all bg-slate-50 p-2 rounded border border-slate-200">
                                {{ document.output_pdf_file.checksum_sha256 }}
                            </dd>
                        </div>
                        <a
                            :href="document.output_pdf_file.download_url"
                            class="block w-full text-center rounded-lg bg-rose-700 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-800 transition"
                        >
                            Unduh Hasil PDF (.pdf)
                        </a>
                        <button
                            v-if="document.can_export_pdf"
                            type="button"
                            :disabled="pdfForm.processing"
                            @click="retryPdfExport"
                            class="block w-full text-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50 transition"
                        >
                            <span v-if="pdfForm.processing">Mengekspor Ulang PDF...</span>
                            <span v-else>Buat Ulang Versi PDF</span>
                        </button>
                    </div>

                    <div v-else class="space-y-3">
                        <p class="text-xs text-slate-500">
                            {{ document.current_status === 'PDF_FAILED'
                                ? 'Konversi PDF gagal pada proses sebelumnya, namun hasil DOCX tetap aman. Anda dapat mengekspor ulang PDF tanpa memproses ulang DOCX.'
                                : 'Belum ada berkas PDF F4. Jalankan standarisasi dokumen untuk menghasilkan versi DOCX dan PDF.' }}
                        </p>
                        <button
                            v-if="document.can_export_pdf"
                            type="button"
                            :disabled="pdfForm.processing"
                            @click="retryPdfExport"
                            class="block w-full text-center rounded-lg bg-blue-800 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-900 disabled:opacity-50 transition"
                        >
                            <span v-if="pdfForm.processing">Mengekspor PDF...</span>
                            <span v-else>Ekspor PDF dari Hasil DOCX</span>
                        </button>
                    </div>
                </div>

                <!-- Kartu Hasil Standarisasi DOCX -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900">Hasil Standarisasi DOCX</h2>
                        <span
                            v-if="document.output_docx_file"
                            class="rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                        >
                            Tersedia
                        </span>
                    </div>

                    <div v-if="document.output_docx_file" class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Nama File Output</dt>
                            <dd class="mt-0.5 font-semibold text-slate-900 break-all">
                                {{ document.output_docx_file.original_filename }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Ukuran & Waktu Selesai</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{ document.output_docx_file.human_size }} • {{ document.output_docx_file.created_at }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Checksum SHA-256 Output</dt>
                            <dd class="mt-0.5 font-mono text-[11px] text-slate-600 break-all bg-slate-50 p-2 rounded border border-slate-200">
                                {{ document.output_docx_file.checksum_sha256 }}
                            </dd>
                        </div>
                        <a
                            :href="document.output_docx_file.download_url"
                            class="block w-full text-center rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 transition"
                        >
                            Unduh Hasil DOCX (.docx)
                        </a>
                    </div>

                    <p v-else class="text-xs text-slate-500">
                        Belum ada file keluaran .docx. Jalankan proses standarisasi untuk membuat file output baru.
                    </p>
                </div>

                <!-- Informasi File Sumber Privat -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                    <h2 class="text-base font-bold text-slate-900">Arsip Sumber Asli</h2>
                    <p class="text-xs text-slate-500">
                        Disimpan pada penyimpanan privat (immutable) dan tidak akan pernah ditimpa saat standarisasi dijalankan.
                    </p>

                    <dl v-if="document.source_file" class="space-y-3 border-t border-slate-100 pt-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Nama File Asli</dt>
                            <dd class="mt-0.5 font-semibold text-slate-900 break-all">
                                {{ document.source_file.original_filename }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Ukuran & Format</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{ document.source_file.human_size }} ({{ document.source_format }})
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Diunggah Oleh</dt>
                            <dd class="mt-0.5 text-slate-800">
                                {{ document.uploader_name }} • {{ document.created_at }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Checksum SHA-256 Asli</dt>
                            <dd class="mt-0.5 font-mono text-[11px] text-slate-600 break-all bg-slate-50 p-2 rounded border border-slate-200">
                                {{ document.source_file.checksum_sha256 }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Riwayat Eksekusi Proses -->
                <div v-if="document.processing_runs.length > 0" class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-3">
                    <h2 class="text-base font-bold text-slate-900">Riwayat Eksekusi</h2>
                    <div class="divide-y divide-slate-100">
                        <div
                            v-for="run in document.processing_runs"
                            :key="run.id"
                            class="py-2.5 first:pt-0 last:pb-0 text-xs space-y-1"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold text-slate-800">
                                    #{{ run.id }} ({{ run.target_type }}) • {{ run.status_label }}
                                </span>
                                <span class="text-slate-500">{{ run.created_at }}</span>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500">
                                <span>Oleh: {{ run.triggered_by_name }}</span>
                                <div class="flex items-center gap-2">
                                    <a
                                        v-if="run.output_docx_url"
                                        :href="run.output_docx_url"
                                        class="font-semibold text-emerald-700 hover:underline"
                                    >
                                        DOCX
                                    </a>
                                    <a
                                        v-if="run.output_pdf_url"
                                        :href="run.output_pdf_url"
                                        class="font-semibold text-rose-700 hover:underline"
                                    >
                                        PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="showDeleteDialog"
            title="Hapus Dokumen Soal & Seluruh Berkasnya?"
            :message="`Dokumen '${document.document_title || ''}' beserta berkas sumber asli (.docx), hasil standarisasi (.docx), hasil cetak PDF, dan riwayat eksekusinya akan dihapus permanen dari penyimpanan privat.`"
            confirm-text="Ya, Hapus Permanen"
            variant="danger"
            :processing="deleteForm.processing"
            @confirm="confirmDelete"
            @close="showDeleteDialog = false"
        />
    </AppLayout>
</template>