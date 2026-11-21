<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FileDropzone from '../../Components/FileDropzone.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    periods: {
        type: Array,
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
    limits: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    exam_period_id: props.periods.length > 0 ? props.periods[0].id : '',
    items: [],
});

const bulkDefaults = ref({
    subject_id: '',
    school_class_id: '',
    teacher_name: '',
    exam_date: '',
    duration_minutes: 90,
});

const handleFilesSelected = (files) => {
    for (const file of files) {
        if (form.items.length >= props.limits.max_batch_files) {
            break;
        }

        const baseTitle = file.name.replace(/\.docx$/i, '');

        form.items.push({
            file,
            file_name: file.name,
            file_size_kb: Math.max(1, Math.round(file.size / 1024)),
            subject_id: bulkDefaults.value.subject_id || '',
            school_class_id: bulkDefaults.value.school_class_id || '',
            teacher_name: bulkDefaults.value.teacher_name || '',
            exam_date: bulkDefaults.value.exam_date || '',
            duration_minutes: bulkDefaults.value.duration_minutes || '',
            document_title: baseTitle,
            notes: '',
        });
    }
};

const applyBulkToAll = () => {
    form.items.forEach((item) => {
        if (bulkDefaults.value.subject_id) item.subject_id = bulkDefaults.value.subject_id;
        if (bulkDefaults.value.school_class_id) item.school_class_id = bulkDefaults.value.school_class_id;
        if (bulkDefaults.value.teacher_name) item.teacher_name = bulkDefaults.value.teacher_name;
        if (bulkDefaults.value.exam_date) item.exam_date = bulkDefaults.value.exam_date;
        if (bulkDefaults.value.duration_minutes) item.duration_minutes = bulkDefaults.value.duration_minutes;
    });
};

const removeItem = (index) => {
    form.items.splice(index, 1);
};

const submit = () => {
    form.post('/exam-documents', {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Upload Dokumen Soal" />

    <AppLayout title="Upload Dokumen Soal" breadcrumb="SIMSOAL / Dokumen Soal / Upload">
        <PageHeader
            title="Unggah Dokumen Soal (.docx)"
            description="File sumber asli disimpan secara privat dan tidak pernah ditimpa oleh sistem."
        >
            <template #actions>
                <Link
                    href="/exam-documents"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Kembali ke Daftar
                </Link>
            </template>
        </PageHeader>

        <div
            v-if="periods.length === 0"
            class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
        >
            <p class="font-semibold">Belum ada periode ujian aktif atau draf</p>
            <p class="mt-1 text-xs text-amber-800">
                Periode yang sudah diarsipkan tidak menerima dokumen baru. Buat periode ujian terlebih dahulu sebelum mengunggah file.
            </p>
            <Link
                href="/exam-periods"
                class="mt-4 inline-flex rounded-lg bg-amber-800 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-900"
            >
                Buat Periode Ujian
            </Link>
        </div>

        <form v-else @submit.prevent="submit" class="space-y-6">
            <!-- Pemilihan Periode & Pengisian Massal -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-800 mb-1">
                        Periode Ujian Tujuan <span class="text-rose-600">*</span>
                    </label>
                    <select
                        v-model="form.exam_period_id"
                        required
                        class="w-full max-w-xl rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option v-for="p in periods" :key="p.id" :value="p.id">
                            {{ p.name }} — TP {{ p.academic_year }} ({{ p.status === 'ACTIVE' ? 'Aktif' : 'Draf' }})
                        </option>
                    </select>
                    <p v-if="form.errors.exam_period_id" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.exam_period_id }}
                    </p>
                </div>

                <!-- Pengisian Massal Opsional -->
                <div class="border-t border-slate-100 pt-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Nilai Bawaan Massal (Opsional)</h2>
                            <p class="text-xs text-slate-500">
                                Isi kolom di bawah jika beberapa file berasal dari mapel, kelas, atau guru yang sama.
                            </p>
                        </div>
                        <button
                            v-if="form.items.length > 0"
                            type="button"
                            @click="applyBulkToAll"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100"
                        >
                            Terapkan ke Semua Baris
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                        <select
                            v-model="bulkDefaults.subject_id"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Mapel (Opsional)</option>
                            <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>

                        <select
                            v-model="bulkDefaults.school_class_id"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="">Kelas (Opsional)</option>
                            <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>

                        <input
                            v-model="bulkDefaults.teacher_name"
                            type="text"
                            placeholder="Nama Guru"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />

                        <input
                            v-model="bulkDefaults.exam_date"
                            type="date"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />

                        <input
                            v-model="bulkDefaults.duration_minutes"
                            type="number"
                            min="1"
                            max="600"
                            placeholder="Durasi (menit)"
                            class="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                    </div>
                </div>
            </div>

            <!-- Dropzone File -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                <FileDropzone
                    :max-upload-mb="limits.max_upload_mb"
                    :max-files="limits.max_batch_files"
                    @files-selected="handleFilesSelected"
                />
                <p v-if="form.errors.items" class="mt-2 text-xs text-rose-600">{{ form.errors.items }}</p>
            </div>

            <!-- Daftar Baris Dokumen untuk Dilengkapi Metadatanya -->
            <div v-if="form.items.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">
                        Daftar Dokumen Siap Unggah ({{ form.items.length }} file)
                    </h2>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                        <div>
                            <span class="text-xs font-bold text-blue-800">#{{ index + 1 }}</span>
                            <span class="ml-2 text-sm font-semibold text-slate-900">{{ item.file_name }}</span>
                            <span class="ml-2 text-xs text-slate-500">({{ item.file_size_kb }} KB)</span>
                        </div>
                        <button
                            type="button"
                            @click="removeItem(index)"
                            class="text-xs font-medium text-rose-700 hover:underline"
                        >
                            Hapus Baris
                        </button>
                    </div>

                    <p v-if="form.errors[`items.${index}.file`]" class="mb-3 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-xs text-rose-700">
                        {{ form.errors[`items.${index}.file`] }}
                    </p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Judul Naskah</label>
                            <input
                                v-model="item.document_title"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Nama Guru <span class="text-rose-600">*</span>
                            </label>
                            <input
                                v-model="item.teacher_name"
                                type="text"
                                required
                                placeholder="Nama lengkap guru"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p v-if="form.errors[`items.${index}.teacher_name`]" class="mt-1 text-xs text-rose-600">
                                {{ form.errors[`items.${index}.teacher_name`] }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Mata Pelajaran</label>
                            <select
                                v-model="item.subject_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Pilih Mapel</option>
                                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Rombongan Kelas</label>
                            <select
                                v-model="item.school_class_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option value="">Pilih Kelas</option>
                                <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Tanggal Ujian</label>
                            <input
                                v-model="item.exam_date"
                                type="date"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Durasi (Menit)</label>
                            <input
                                v-model="item.duration_minutes"
                                type="number"
                                min="1"
                                max="600"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Catatan Operator</label>
                            <input
                                v-model="item.notes"
                                type="text"
                                placeholder="Catatan khusus (opsional)"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link
                        href="/exam-documents"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-800 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50 transition"
                    >
                        <span v-if="form.processing">Mengunggah & Memvalidasi...</span>
                        <span v-else>Simpan & Unggah {{ form.items.length }} Dokumen</span>
                    </button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>