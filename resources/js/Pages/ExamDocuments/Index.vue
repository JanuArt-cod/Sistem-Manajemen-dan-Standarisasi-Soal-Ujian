<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    documents: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
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
    statuses: {
        type: Array,
        required: true,
    },
});

const filterForm = reactive({
    search: props.filters.search || '',
    period_id: props.filters.period_id || '',
    subject_id: props.filters.subject_id || '',
    class_id: props.filters.class_id || '',
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get('/exam-documents', filterForm, {
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    filterForm.search = '';
    filterForm.period_id = '';
    filterForm.subject_id = '';
    filterForm.class_id = '';
    filterForm.status = '';
    applyFilters();
};
</script>

<template>
    <Head title="Daftar Dokumen Soal" />

    <AppLayout title="Dokumen Soal" breadcrumb="SIMSOAL / Dokumen Soal">
        <PageHeader
            title="Bank Naskah Soal Ujian"
            description="Kelola dokumen sumber guru yang tersimpan secara privat dan pantau status standarisasi."
        >
            <template #actions>
                <Link
                    href="/exam-documents/create"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    + Unggah Soal DOCX
                </Link>
            </template>
        </PageHeader>

        <!-- Bar Filter -->
        <form
            @submit.prevent="applyFilters"
            class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-xs grid grid-cols-1 gap-3 md:grid-cols-6"
        >
            <input
                v-model="filterForm.search"
                type="text"
                placeholder="Cari judul, guru, atau nama file..."
                class="md:col-span-2 rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            <select v-model="filterForm.period_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Periode</option>
                <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="filterForm.subject_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Mapel</option>
                <option v-for="s in subjects" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="filterForm.class_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Kelas</option>
                <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filterForm.status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua Status</option>
                <option v-for="st in statuses" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>

            <div class="md:col-span-6 flex items-center justify-end gap-2 pt-1">
                <button
                    type="button"
                    @click="resetFilters"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                >
                    Reset Filter
                </button>
                <button
                    type="submit"
                    class="rounded-lg bg-slate-800 px-4 py-1.5 text-xs font-semibold text-white hover:bg-slate-900"
                >
                    Terapkan Filter
                </button>
            </div>
        </form>

        <EmptyState
            v-if="documents.data.length === 0"
            title="Belum ada dokumen soal ditemukan"
            description="Unggah naskah soal berformat DOCX dari guru atau ubah kriteria filter pencarian."
        >
            <Link
                href="/exam-documents/create"
                class="inline-flex rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900"
            >
                Unggah Soal Pertama
            </Link>
        </EmptyState>

        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                    <tr>
                        <th class="px-5 py-3.5">Naskah & File Sumber</th>
                        <th class="px-5 py-3.5">Mapel & Kelas</th>
                        <th class="px-5 py-3.5">Guru Penyusun</th>
                        <th class="px-5 py-3.5">Periode</th>
                        <th class="px-5 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <Link
                                :href="`/exam-documents/${doc.id}`"
                                class="font-semibold text-slate-900 hover:text-blue-700"
                            >
                                {{ doc.document_title }}
                            </Link>
                            <p v-if="doc.source_file" class="mt-0.5 text-xs text-slate-500">
                                {{ doc.source_file.original_filename }} ({{ doc.source_file.human_size }})
                            </p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-700">
                            <p class="font-medium">{{ doc.subject_name }}</p>
                            <p class="text-xs text-slate-500">Kelas {{ doc.class_name }}</p>
                        </td>
                        <td class="px-5 py-3.5 text-slate-700">{{ doc.teacher_name }}</td>
                        <td class="px-5 py-3.5 text-xs text-slate-600">
                            <p class="font-medium text-slate-800">{{ doc.period_name }}</p>
                            <p>TP {{ doc.academic_year }}</p>
                        </td>
                        <td class="px-5 py-3.5">
                            <StatusBadge :status="doc.current_status" :label="doc.status_label" />
                        </td>
                        <td class="px-5 py-3.5 text-right space-x-2 whitespace-nowrap">
                            <a
                                v-if="doc.output_pdf_file"
                                :href="doc.output_pdf_file.download_url"
                                class="rounded-lg bg-rose-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-800"
                            >
                                PDF
                            </a>
                            <a
                                v-if="doc.output_docx_file"
                                :href="doc.output_docx_file.download_url"
                                class="rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-800"
                            >
                                DOCX
                            </a>
                            <a
                                v-if="doc.source_file"
                                :href="doc.source_file.download_url"
                                class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            >
                                Sumber
                            </a>
                            <Link
                                :href="`/exam-documents/${doc.id}`"
                                class="rounded-lg border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100"
                            >
                                Detail
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="documents.links && documents.links.length > 3" class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-600">
                <span>Menampilkan {{ documents.from }}–{{ documents.to }} dari {{ documents.total }} dokumen</span>
                <div class="flex gap-1">
                    <template v-for="(link, idx) in documents.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            class="rounded px-2.5 py-1 border"
                            :class="link.active ? 'bg-blue-800 text-white border-blue-800 font-semibold' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-100'"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>