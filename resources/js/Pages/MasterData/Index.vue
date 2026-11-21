<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

defineProps({
    subjects: {
        type: Array,
        required: true,
    },
    schoolClasses: {
        type: Array,
        required: true,
    },
});

const subjectForm = useForm({
    name: '',
    code: '',
    is_active: true,
});

const classForm = useForm({
    name: '',
    level: '',
    is_active: true,
});

const submitSubject = () => {
    subjectForm.post('/master-data/subjects', {
        preserveScroll: true,
        onSuccess: () => subjectForm.reset('name', 'code'),
    });
};

const submitClass = () => {
    classForm.post('/master-data/classes', {
        preserveScroll: true,
        onSuccess: () => classForm.reset('name', 'level'),
    });
};

const toggleSubjectStatus = (subject) => {
    useForm({
        name: subject.name,
        code: subject.code || '',
        is_active: !subject.is_active,
    }).put(`/master-data/subjects/${subject.id}`, {
        preserveScroll: true,
    });
};

const toggleClassStatus = (schoolClass) => {
    useForm({
        name: schoolClass.name,
        level: schoolClass.level || '',
        is_active: !schoolClass.is_active,
    }).put(`/master-data/classes/${schoolClass.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Data Referensi" />

    <AppLayout title="Data Referensi" breadcrumb="SIMSOAL / Administrasi / Data Referensi">
        <PageHeader
            title="Referensi Mata Pelajaran & Kelas"
            description="Daftar standar mata pelajaran dan rombongan kelas untuk menjaga konsistensi metadata dokumen soal."
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Panel Mata Pelajaran -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900">Mata Pelajaran</h2>
                <p class="mt-0.5 text-xs text-slate-500">Tambahkan nama mata pelajaran yang diujikan.</p>

                <form @submit.prevent="submitSubject" class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="subjectForm.name"
                        type="text"
                        required
                        placeholder="Nama mapel (mis. Bahasa Indonesia)"
                        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <input
                        v-model="subjectForm.code"
                        type="text"
                        placeholder="Kode (Opsional)"
                        class="w-full sm:w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <button
                        type="submit"
                        :disabled="subjectForm.processing"
                        class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                    >
                        Tambah
                    </button>
                </form>
                <p v-if="subjectForm.errors.name" class="mt-1 text-xs text-rose-600">{{ subjectForm.errors.name }}</p>

                <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                    <table v-if="subjects.length > 0" class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                            <tr>
                                <th class="px-4 py-2.5">Mata Pelajaran</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sub in subjects" :key="sub.id">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium text-slate-900">{{ sub.name }}</span>
                                    <span v-if="sub.code" class="ml-1.5 text-xs text-slate-500">({{ sub.code }})</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <StatusBadge :status="sub.is_active ? 'ACTIVE' : 'INACTIVE'" />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button
                                        type="button"
                                        @click="toggleSubjectStatus(sub)"
                                        class="text-xs font-medium text-slate-600 hover:text-slate-900 underline"
                                    >
                                        {{ sub.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-6 text-center text-xs text-slate-500">
                        Belum ada referensi mata pelajaran.
                    </div>
                </div>
            </div>

            <!-- Panel Kelas -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                <h2 class="text-base font-bold text-slate-900">Rombongan Kelas</h2>
                <p class="mt-0.5 text-xs text-slate-500">Tambahkan daftar kelas atau tingkat peserta ujian.</p>

                <form @submit.prevent="submitClass" class="mt-4 flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="classForm.name"
                        type="text"
                        required
                        placeholder="Nama kelas (mis. X TKJ 1 / IX A)"
                        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <input
                        v-model="classForm.level"
                        type="text"
                        placeholder="Tingkat (mis. X)"
                        class="w-full sm:w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <button
                        type="submit"
                        :disabled="classForm.processing"
                        class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                    >
                        Tambah
                    </button>
                </form>
                <p v-if="classForm.errors.name" class="mt-1 text-xs text-rose-600">{{ classForm.errors.name }}</p>

                <div class="mt-5 overflow-hidden rounded-lg border border-slate-200">
                    <table v-if="schoolClasses.length > 0" class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                            <tr>
                                <th class="px-4 py-2.5">Kelas</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="cls in schoolClasses" :key="cls.id">
                                <td class="px-4 py-2.5">
                                    <span class="font-medium text-slate-900">{{ cls.name }}</span>
                                    <span v-if="cls.level" class="ml-1.5 text-xs text-slate-500">• Tingkat {{ cls.level }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <StatusBadge :status="cls.is_active ? 'ACTIVE' : 'INACTIVE'" />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <button
                                        type="button"
                                        @click="toggleClassStatus(cls)"
                                        class="text-xs font-medium text-slate-600 hover:text-slate-900 underline"
                                    >
                                        {{ cls.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-6 text-center text-xs text-slate-500">
                        Belum ada referensi kelas.
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>