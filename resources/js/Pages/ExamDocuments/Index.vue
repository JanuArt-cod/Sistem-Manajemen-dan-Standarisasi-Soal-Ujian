<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
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

const filterForm = ref({
    search: props.filters.search || '',
    period_id: props.filters.period_id || '',
    subject_id: props.filters.subject_id || '',
    class_id: props.filters.class_id || '',
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get(
        '/exam-documents',
        {
            search: filterForm.value.search || undefined,
            period_id: filterForm.value.period_id || undefined,
            subject_id: filterForm.value.subject_id || undefined,
            class_id: filterForm.value.class_id || undefined,
            status: filterForm.value.status || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    filterForm.value = {
        search: '',
        period_id: '',
        subject_id: '',
        class_id: '',
        status: '',
    };
    applyFilters();
};
</script>

<template>
    <Head title="Dokumen Soal" />

    <AppLayout title="Dokumen Soal" breadcrumb="SIMSOAL / Administrasi / Dokumen Soal">
        <PageHeader
            title="Dokumen Soal Ujian"
            description="Daftar naskah soal yang dikumpulkan dari guru dan tersimpan secara privat di sistem."
        >
            <template #actions>
                <Link
                    href="/exam-documents/create"
                    class="inline-flex items-center rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Upload Dokumen Soal
                </Link>
            </template>
        </PageHeader>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Cari judul, guru, atau nama file..."
                    class="sm:col-span-2 rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                />

                <select
                    v-model="filterForm.period_id"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Periode</option>
                    <option v-for="p in periods" :key="p.id" :value="p.id">
                        {{ p.name }} ({{ p.academic_year }})
                    </option>
                </select>

                <select
                    v-model="filterForm.subject_id"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Mapel</option>
                    <option v-for="s in subjects" :key="s.id" :value="s.id">
                        {{ s.name }}
                    </option>
                </select>

                <select
                    v-model="filterForm.status"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Status</option>
                    <option v-for="st in statuses" :key="st.value" :value="st.value">
                        {{ st.label }}
                    </option>
                </select>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-lg bg-slate-800 px-3 py-2 text-sm font-medium text-white hover:bg-slate-900"
                    >
                        Filter
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Dokumen -->
        <div v-if="documents.data.length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-5 py-3.5">Naskah & File Sumber</th>
                            <th class="px-5 py-3.5">Mapel & Kelas</th>
                            <th class="px-5 py-3.5">Guru</th>
                            <th class="px-5 py-3.5">Periode</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="doc in documents.data" :key="doc.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="`/exam-documents/${doc.id}`"
                                    class="font-semibold text-slate-900 hover:text-blue-800"
                                >
                                    {{ doc.document_title }}
                                </Link>
                                <p v-if="doc.source_file" class="mt-0.5 text-xs text-slate-500">
                                    {{ doc.source_file.original_filename }} ({{ doc.source_file.human_size }})
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-800">{{ doc.subject_name }}</p>
                                <p class="text-xs text-slate-500">{{ doc.class_name }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">{{ doc.teacher_name }}</td>
                            <td class="px-5 py-3.5">
                                <p class="text-slate-800">{{ doc.period_name }}</p>
                                <p class="text-xs text-slate-500">TP {{ doc.academic_year }}</p>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusBadge :status="doc.current_status" :label="doc.status_label" />
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <Link
                                        :href="`/exam-documents/${doc.id}`"
                                        class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                    >
                                        Detail
                                    </Link>
                                    <a
                                        v-if="doc.source_file"
                                        :href="doc.source_file.download_url"
                                        class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800 hover:bg-blue-100"
                                    >
                                        Unduh Sumber
                                    </a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="documents.links && documents.links.length > 3"
                class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-600"
            >
                <span>Menampilkan {{ documents.from }}–{{ documents.to }} dari {{ documents.total }} dokumen</span>
                <div class="flex items-center gap-1">
                    <template v-for="(link, idx) in documents.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            class="rounded-md border px-2.5 py-1 font-medium"
                            :class="
                                link.active
                                    ? 'border-blue-800 bg-blue-800 text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            title="Belum ada dokumen soal"
            description="Unggah naskah soal berformat .docx untuk periode ujian aktif agar siap diproses standarisasi."
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
    </AppLayout>
</template>