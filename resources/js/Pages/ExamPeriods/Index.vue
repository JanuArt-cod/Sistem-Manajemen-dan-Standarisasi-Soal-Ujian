<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    periods: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    academicYears: {
        type: Array,
        required: true,
    },
    semesters: {
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
    status: props.filters.status || '',
    academic_year: props.filters.academic_year || '',
});

const applyFilters = () => {
    router.get(
        '/exam-periods',
        {
            search: filterForm.value.search || undefined,
            status: filterForm.value.status || undefined,
            academic_year: filterForm.value.academic_year || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    filterForm.value = { search: '', status: '', academic_year: '' };
    applyFilters();
};

const showModal = ref(false);
const editingPeriod = ref(null);

const form = useForm({
    name: '',
    academic_year: '2026/2027',
    semester: 'ODD',
    start_date: '',
    end_date: '',
    status: 'ACTIVE',
});

const openCreateModal = () => {
    editingPeriod.value = null;
    form.reset();
    form.clearErrors();
    form.academic_year = '2026/2027';
    form.semester = 'ODD';
    form.status = 'ACTIVE';
    showModal.value = true;
};

const openEditModal = (period) => {
    editingPeriod.value = period;
    form.clearErrors();
    form.name = period.name;
    form.academic_year = period.academic_year;
    form.semester = period.semester;
    form.start_date = period.start_date || '';
    form.end_date = period.end_date || '';
    form.status = period.status;
    showModal.value = true;
};

const closeModal = () => {
    if (form.processing) return;
    showModal.value = false;
    editingPeriod.value = null;
};

const submitForm = () => {
    if (editingPeriod.value) {
        form.put(`/exam-periods/${editingPeriod.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                editingPeriod.value = null;
            },
        });
    } else {
        form.post('/exam-periods', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                form.reset();
            },
        });
    }
};

const actionTarget = ref(null);
const actionType = ref('archive'); // archive | reopen
const actionProcessing = ref(false);

const promptAction = (period, type) => {
    actionTarget.value = period;
    actionType.value = type;
};

const executeAction = () => {
    if (!actionTarget.value) return;
    actionProcessing.value = true;
    const url =
        actionType.value === 'archive'
            ? `/exam-periods/${actionTarget.value.id}/archive`
            : `/exam-periods/${actionTarget.value.id}/reopen`;

    router.patch(
        url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                actionProcessing.value = false;
                actionTarget.value = null;
            },
        }
    );
};
</script>

<template>
    <Head title="Periode Ujian" />

    <AppLayout title="Periode Ujian" breadcrumb="SIMSOAL / Administrasi / Periode Ujian">
        <PageHeader
            title="Periode Ujian"
            description="Kelola periode pelaksanaan ujian sekolah sebagai pengelompokan naskah soal dan arsip."
        >
            <template #actions>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Buat Periode Ujian
                </button>
            </template>
        </PageHeader>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Cari nama periode atau tahun..."
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                />

                <select
                    v-model="filterForm.academic_year"
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Tahun Pelajaran</option>
                    <option v-for="year in academicYears" :key="year" :value="year">
                        TP {{ year }}
                    </option>
                </select>

                <select
                    v-model="filterForm.status"
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Status</option>
                    <option v-for="st in statuses" :key="st.value" :value="st.value">
                        {{ st.label }}
                    </option>
                </select>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900"
                    >
                        Terapkan
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Periode -->
        <div v-if="periods.data.length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-5 py-3.5">Nama Periode</th>
                            <th class="px-5 py-3.5">Tahun Pelajaran</th>
                            <th class="px-5 py-3.5">Semester</th>
                            <th class="px-5 py-3.5">Jadwal</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in periods.data" :key="item.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <p class="font-semibold text-slate-900">{{ item.name }}</p>
                                <p class="text-xs text-slate-500">Dibuat oleh {{ item.creator_name }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-slate-700">{{ item.academic_year }}</td>
                            <td class="px-5 py-3.5 text-slate-700">{{ item.semester_label }}</td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <span v-if="item.start_date_formatted || item.end_date_formatted">
                                    {{ item.start_date_formatted || '?' }} – {{ item.end_date_formatted || '?' }}
                                </span>
                                <span v-else class="text-slate-400">Belum dijadwalkan</span>
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusBadge :status="item.status" :label="item.status_label" />
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        v-if="item.can_edit"
                                        type="button"
                                        @click="openEditModal(item)"
                                        class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        v-if="item.can_archive"
                                        type="button"
                                        @click="promptAction(item, 'archive')"
                                        class="rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100"
                                    >
                                        Arsipkan
                                    </button>
                                    <button
                                        v-if="item.can_reopen"
                                        type="button"
                                        @click="promptAction(item, 'reopen')"
                                        class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800 hover:bg-blue-100"
                                    >
                                        Buka Kembali
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="periods.links && periods.links.length > 3"
                class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-600"
            >
                <span>Menampilkan {{ periods.from }}–{{ periods.to }} dari {{ periods.total }} periode</span>
                <div class="flex items-center gap-1">
                    <template v-for="(link, idx) in periods.links" :key="idx">
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
            title="Belum ada periode ujian"
            description="Buat periode ujian pertama Anda untuk mulai mengelompokkan dokumen soal dari para guru."
        >
            <template #action>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900"
                >
                    Buat Periode Ujian
                </button>
            </template>
        </EmptyState>

        <!-- Modal Buat / Ubah Periode -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                <h2 class="text-base font-bold text-slate-900">
                    {{ editingPeriod ? 'Ubah Periode Ujian' : 'Buat Periode Ujian Baru' }}
                </h2>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Periode Ujian</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Penilaian Akhir Semester (PAS) Ganjil"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tahun Pelajaran</label>
                            <input
                                v-model="form.academic_year"
                                type="text"
                                required
                                placeholder="2026/2027"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p v-if="form.errors.academic_year" class="mt-1 text-xs text-rose-600">{{ form.errors.academic_year }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Semester</label>
                            <select
                                v-model="form.semester"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            >
                                <option v-for="s in semesters" :key="s.value" :value="s.value">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Mulai (Opsional)</label>
                            <input
                                v-model="form.start_date"
                                type="date"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p v-if="form.errors.start_date" class="mt-1 text-xs text-rose-600">{{ form.errors.start_date }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Selesai (Opsional)</label>
                            <input
                                v-model="form.end_date"
                                type="date"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p v-if="form.errors.end_date" class="mt-1 text-xs text-rose-600">{{ form.errors.end_date }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status Periode</label>
                        <select
                            v-model="form.status"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        >
                            <option value="DRAFT">Draf</option>
                            <option value="ACTIVE">Aktif</option>
                            <option v-if="editingPeriod" value="ARCHIVED">Diarsipkan</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
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
                            <span v-else>Simpan Periode</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dialog Konfirmasi Arsip / Buka Kembali -->
        <ConfirmDialog
            :show="Boolean(actionTarget)"
            :title="actionType === 'archive' ? 'Arsipkan Periode Ujian' : 'Buka Kembali Periode Ujian'"
            :message="
                actionType === 'archive'
                    ? `Periode '${actionTarget?.name}' akan dikunci sebagai arsip dan tidak dapat menerima unggahan dokumen baru kecuali dibuka kembali oleh Administrator.`
                    : `Periode '${actionTarget?.name}' akan diaktifkan kembali sehingga dapat menerima dokumen soal baru.`
            "
            :confirm-text="actionType === 'archive' ? 'Ya, Arsipkan' : 'Ya, Aktifkan'"
            :variant="actionType === 'archive' ? 'danger' : 'primary'"
            :processing="actionProcessing"
            @close="actionTarget = null"
            @confirm="executeAction"
        />
    </AppLayout>
</template>