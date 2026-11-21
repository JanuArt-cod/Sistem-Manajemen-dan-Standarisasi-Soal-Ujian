<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
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

const submit = () => {
    form.put(`/exam-documents/${props.document.id}`, {
        preserveScroll: true,
    });
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
                    v-if="document.source_file"
                    :href="document.source_file.download_url"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Unduh Sumber Asli
                </a>
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
            <!-- Form Edit Metadata -->
            <form
                @submit.prevent="submit"
                class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4"
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

            <!-- Informasi File Sumber Privat -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs h-fit space-y-4">
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
                        <dt class="text-xs font-medium text-slate-500">Checksum SHA-256</dt>
                        <dd class="mt-0.5 font-mono text-[11px] text-slate-600 break-all bg-slate-50 p-2 rounded border border-slate-200">
                            {{ document.source_file.checksum_sha256 }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </AppLayout>
</template>