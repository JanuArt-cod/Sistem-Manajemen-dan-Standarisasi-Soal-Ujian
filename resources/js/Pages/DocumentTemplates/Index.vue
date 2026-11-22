<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    headerModes: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);
const editingTemplate = ref(null);

const form = useForm({
    name: '',
    description: '',
    header_mode: 'STANDARD_SCHOOL',
    first_page_only: true,
    show_school_logo: true,
    show_exam_identity_table: true,
    header_title: '',
    header_subtitle: '',
    instruction_text: '',
    template_file: null,
    is_default: false,
    is_active: true,
});

const openCreateModal = () => {
    editingTemplate.value = null;
    form.reset();
    form.clearErrors();
    form.header_mode = 'STANDARD_SCHOOL';
    form.first_page_only = true;
    form.show_school_logo = true;
    form.show_exam_identity_table = true;
    form.template_file = null;
    form.is_default = false;
    form.is_active = true;
    showModal.value = true;
};

const openEditModal = (template) => {
    editingTemplate.value = template;
    form.clearErrors();
    form.name = template.name;
    form.description = template.description || '';
    form.header_mode = template.header_mode;
    form.first_page_only = true;
    form.show_school_logo = template.show_school_logo;
    form.show_exam_identity_table = template.show_exam_identity_table;
    form.header_title = template.header_title || '';
    form.header_subtitle = template.header_subtitle || '';
    form.instruction_text = template.instruction_text || '';
    form.template_file = null;
    form.is_default = template.is_default;
    form.is_active = template.is_active;
    showModal.value = true;
};

const closeModal = () => {
    if (form.processing) return;
    showModal.value = false;
    editingTemplate.value = null;
};

const onFileChange = (event) => {
    const files = event.target.files;
    form.template_file = files && files.length > 0 ? files[0] : null;
};

const submit = () => {
    if (editingTemplate.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(`/document-templates/${editingTemplate.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                editingTemplate.value = null;
            },
        });
    } else {
        form.post('/document-templates', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const setDefault = (template) => {
    router.patch(`/document-templates/${template.id}/default`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Template Kop Halaman Pertama" />

    <AppLayout title="Template Kop" breadcrumb="SIMSOAL / Standarisasi / Template Kop">
        <PageHeader
            title="Template Kop Ujian (Halaman Pertama)"
            description="Pengaturan kop naskah ujian yang dikunci hanya tampil pada halaman pertama dokumen (Keputusan D-016)."
        >
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreateModal"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Buat Template Kop
                </button>
            </template>
        </PageHeader>

        <div class="mb-6 rounded-xl border border-blue-200 bg-blue-50/70 px-4 py-3 text-xs text-blue-950">
            <span class="font-semibold">Kebijakan Tata Letak (D-016):</span>
            Seluruh template kop otomatis dibatasi hanya muncul pada <strong>halaman pertama</strong> naskah soal, sehingga halaman kedua dan seterusnya memiliki ruang soal yang penuh dan rapi.
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div
                v-for="template in templates"
                :key="template.id"
                class="rounded-xl border bg-white p-6 shadow-xs flex flex-col justify-between"
                :class="template.is_default ? 'border-blue-300 ring-1 ring-blue-100' : 'border-slate-200'"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-bold text-slate-900">{{ template.name }}</h2>
                                <span
                                    v-if="template.is_default"
                                    class="rounded-md bg-blue-800 px-2 py-0.5 text-[11px] font-semibold text-white"
                                >
                                    Default Sistem
                                </span>
                            </div>
                            <p v-if="template.description" class="mt-1 text-xs text-slate-500">
                                {{ template.description }}
                            </p>
                        </div>
                        <StatusBadge :status="template.is_active ? 'ACTIVE' : 'INACTIVE'" />
                    </div>

                    <div class="mt-5 space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Mode Kop</span>
                            <span class="font-semibold text-slate-900">{{ template.header_mode_label }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Cakupan Halaman</span>
                            <span class="rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-900">
                                Hanya Halaman Pertama (D-016)
                            </span>
                        </div>

                        <template v-if="template.header_mode === 'STANDARD_SCHOOL'">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Komponen Kop</span>
                                <span class="font-medium text-slate-800">
                                    {{ template.show_school_logo ? 'Logo Sekolah + ' : '' }}
                                    {{ template.show_exam_identity_table ? 'Tabel Identitas Mapel' : 'Judul Ujian' }}
                                </span>
                            </div>
                            <div v-if="template.header_title" class="border-t border-slate-200 pt-2">
                                <p class="text-slate-500">Judul Kop Kustom:</p>
                                <p class="font-semibold text-slate-800">{{ template.header_title }}</p>
                            </div>
                            <div v-if="template.instruction_text" class="border-t border-slate-200 pt-2">
                                <p class="text-slate-500">Petunjuk Umum:</p>
                                <p class="text-slate-700">{{ template.instruction_text }}</p>
                            </div>
                        </template>

                        <template v-if="template.header_mode === 'CUSTOM_DOCX'">
                            <div class="border-t border-slate-200 pt-2">
                                <p class="text-slate-500">File Template Privat (.docx):</p>
                                <div class="mt-1 flex items-center justify-between gap-2">
                                    <span class="truncate font-mono text-[11px] font-semibold text-slate-800">
                                        {{ template.template_original_name || 'Terunggah' }}
                                    </span>
                                    <a
                                        v-if="template.has_custom_docx"
                                        :href="`/document-templates/${template.id}/download`"
                                        class="shrink-0 font-semibold text-blue-800 hover:underline"
                                    >
                                        Unduh .docx
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="canManage"
                    class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4"
                >
                    <button
                        v-if="!template.is_default"
                        type="button"
                        @click="setDefault(template)"
                        class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100"
                    >
                        Jadikan Default
                    </button>
                    <button
                        type="button"
                        @click="openEditModal(template)"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Ubah Template
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Buat / Ubah Template Kop -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl my-auto">
                <h2 class="text-base font-bold text-slate-900">
                    {{ editingTemplate ? 'Ubah Template Kop' : 'Buat Template Kop Baru' }}
                </h2>

                <form @submit.prevent="submit" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Template</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Kop Resmi Sumatif Tengah Semester"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi (Opsional)</label>
                        <input
                            v-model="form.description"
                            type="text"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Mode Penyusunan Kop</label>
                        <select
                            v-model="form.header_mode"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm"
                        >
                            <option v-for="mode in headerModes" :key="mode.value" :value="mode.value">
                                {{ mode.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.header_mode" class="mt-1 text-xs text-rose-600">{{ form.errors.header_mode }}</p>
                    </div>

                    <div
                        v-if="form.header_mode === 'STANDARD_SCHOOL'"
                        class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
                    >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Judul Judul Ujian (Opsional)</label>
                                <input
                                    v-model="form.header_title"
                                    type="text"
                                    placeholder="Kosongkan untuk memakai nama Periode Ujian"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Subjudul Kop (Opsional)</label>
                                <input
                                    v-model="form.header_subtitle"
                                    type="text"
                                    placeholder="Contoh: TAHUN PELAJARAN 2026/2027"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Petunjuk Umum Naskah (Opsional)</label>
                            <textarea
                                v-model="form.instruction_text"
                                rows="2"
                                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                        </div>

                        <div class="flex flex-wrap gap-4 pt-1">
                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                <input v-model="form.show_school_logo" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                                <span>Tampilkan logo sekolah dari Profil Sekolah</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs text-slate-700">
                                <input v-model="form.show_exam_identity_table" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                                <span>Sertakan tabel identitas mata pelajaran, kelas, hari/tanggal, & alokasi waktu</span>
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="form.header_mode === 'CUSTOM_DOCX'"
                        class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4"
                    >
                        <label class="block text-xs font-semibold text-slate-800">
                            File Template Kop Word (.docx)
                        </label>
                        <p class="text-xs text-slate-500">
                            Unggah dokumen .docx berisi kop surat resmi sekolah (maksimal 10 MB). Konten kop akan disisipkan hanya pada halaman pertama.
                        </p>
                        <input
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            @change="onFileChange"
                            class="block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-800 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-900"
                        />
                        <p v-if="editingTemplate?.template_original_name" class="text-[11px] text-slate-600">
                            File tersimpan saat ini: <span class="font-mono font-semibold">{{ editingTemplate.template_original_name }}</span>
                        </p>
                        <p v-if="form.errors.template_file" class="text-xs text-rose-600">{{ form.errors.template_file }}</p>
                    </div>

                    <div class="space-y-2 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2 text-xs text-emerald-900 font-medium">
                            <span>✓ Terkunci: Kop hanya tampil pada halaman pertama (Keputusan D-016)</span>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                            <span>Tetapkan sebagai template kop default sistem</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                            <span>Template aktif dan dapat dipilih pada dokumen</span>
                        </label>
                        <p v-if="form.errors.is_active" class="text-xs text-rose-600">{{ form.errors.is_active }}</p>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            :disabled="form.processing"
                            @click="closeModal"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                        >
                            <span v-if="form.processing">Menyimpan...</span>
                            <span v-else>Simpan Template</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>