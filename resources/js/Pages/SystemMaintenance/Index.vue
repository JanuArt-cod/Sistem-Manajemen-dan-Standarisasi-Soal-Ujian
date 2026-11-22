<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatCard from '../../Components/StatCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    defaultsInfo: {
        type: Object,
        required: true,
    },
});

const resetForm = useForm({
    remove_custom: false,
});
const showResetDialog = ref(false);

const purgeDocsForm = useForm({
    confirmation: '',
});

const purgeMasterForm = useForm({
    confirmation: '',
});

const triggerResetDefaults = () => {
    resetForm.post('/system-maintenance/reset-defaults', {
        preserveScroll: true,
        onFinish: () => {
            showResetDialog.value = false;
        },
    });
};

const submitPurgeDocuments = () => {
    purgeDocsForm.post('/system-maintenance/purge-documents', {
        preserveScroll: true,
        onSuccess: () => {
            purgeDocsForm.reset();
        },
    });
};

const submitPurgeMaster = () => {
    purgeMasterForm.post('/system-maintenance/purge-master-data', {
        preserveScroll: true,
        onSuccess: () => {
            purgeMasterForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Pemeliharaan & Reset Sistem" />

    <AppLayout title="Pemeliharaan Sistem" breadcrumb="SIMSOAL / Administrasi / Pemeliharaan Sistem">
        <PageHeader
            title="Pemeliharaan Sistem & Kesiapan Production"
            description="Pulihkan pengaturan standar default SIMSOAL atau bersihkan seluruh data serta file uji coba sebelum digunakan secara resmi."
        />

        <!-- Ringkasan Data & Penyimpanan -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Dokumen Soal"
                :value="summary.documents_count"
                :hint="`${summary.runs_count} riwayat eksekusi`"
            />
            <StatCard
                label="Berkas Privat Tersimpan"
                :value="summary.files_count"
                :hint="`Total ukuran: ${summary.storage_human_size}`"
            />
            <StatCard
                label="Data Referensi Aktif"
                :value="`${summary.periods_count} Periode`"
                :hint="`${summary.subjects_count} Mapel • ${summary.classes_count} Kelas`"
            />
            <StatCard
                label="Pengaturan & Akun"
                :value="`${summary.presets_count} Preset / ${summary.templates_count} Kop`"
                :hint="`${summary.users_count} akun pengguna terdaftar`"
            />
        </div>

        <div class="space-y-6">
            <!-- Bagian 1: Reset Pengaturan Default Preset & Template Kop -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">1. Reset Pengaturan Default Preset & Template Kop</h2>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        Mengembalikan <strong>{{ defaultsInfo.preset_name }}</strong> (F4 215 × 330 mm, margin 20/20/25/20 mm, Times New Roman 12 pt) dan
                        <strong>{{ defaultsInfo.template_name }}</strong> (Halaman Pertama D-016) ke pengaturan standar baku dan menetapkannya sebagai default aktif.
                    </p>
                </div>

                <label class="flex items-start gap-2.5 rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700">
                    <input
                        v-model="resetForm.remove_custom"
                        type="checkbox"
                        class="mt-0.5 rounded border-slate-300 text-blue-800"
                    />
                    <span>
                        <strong class="text-slate-900">Bersihkan juga preset & template kop tambahan (kustom)</strong> yang tidak sedang terikat pada dokumen soal.
                    </span>
                </label>

                <div class="flex justify-end">
                    <button
                        type="button"
                        @click="showResetDialog = true"
                        class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                    >
                        Reset ke Pengaturan Default
                    </button>
                </div>
            </div>

            <!-- Bagian 2: Pembersihan Dokumen Soal & File Fisik Uji Coba -->
            <form
                @submit.prevent="submitPurgeDocuments"
                class="rounded-xl border border-rose-200 bg-white p-6 shadow-xs space-y-4"
            >
                <div>
                    <span class="inline-block rounded bg-rose-50 border border-rose-200 px-2 py-0.5 text-[11px] font-semibold text-rose-800">
                        Zona Pembersihan Uji Coba
                    </span>
                    <h2 class="mt-2 text-base font-bold text-slate-900">2. Hapus Seluruh Dokumen Soal & Berkas Fisik Uji Coba</h2>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        Menghapus permanen seluruh naskah soal uji coba beserta seluruh file sumber asli (<code>.docx</code>), hasil standarisasi (<code>.docx</code>), hasil cetak (<code>.pdf</code>), dan riwayat proses di disk privat. Akun pengguna dan Profil Sekolah tidak terpengaruh.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 items-end">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Ketik <strong class="font-mono text-rose-700">HAPUS DOKUMEN</strong> untuk mengonfirmasi:
                        </label>
                        <input
                            v-model="purgeDocsForm.confirmation"
                            type="text"
                            placeholder="HAPUS DOKUMEN"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-mono uppercase"
                        />
                        <p v-if="purgeDocsForm.errors.confirmation" class="mt-1 text-xs text-rose-600">
                            {{ purgeDocsForm.errors.confirmation }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="purgeDocsForm.processing || purgeDocsForm.confirmation !== 'HAPUS DOKUMEN'"
                        class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 disabled:opacity-50 transition"
                    >
                        <span v-if="purgeDocsForm.processing">Membersihkan...</span>
                        <span v-else>Bersihkan Semua Dokumen</span>
                    </button>
                </div>
            </form>

            <!-- Bagian 3: Pembersihan Data Master Uji Coba (Periode, Mapel, Kelas) -->
            <form
                @submit.prevent="submitPurgeMaster"
                class="rounded-xl border border-amber-200 bg-white p-6 shadow-xs space-y-4"
            >
                <div>
                    <h2 class="text-base font-bold text-slate-900">3. Bersihkan Data Periode Ujian, Mapel, & Kelas Uji Coba</h2>
                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                        Menghapus seluruh data Periode Ujian, Mata Pelajaran, dan Rombongan Kelas sisa pengujian agar siap diisi data resmi sekolah. Pastikan seluruh Dokumen Soal pada langkah 2 sudah dikosongkan terlebih dahulu.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 items-end">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">
                            Ketik <strong class="font-mono text-amber-800">HAPUS MASTER</strong> untuk mengonfirmasi:
                        </label>
                        <input
                            v-model="purgeMasterForm.confirmation"
                            type="text"
                            placeholder="HAPUS MASTER"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm font-mono uppercase"
                        />
                        <p v-if="purgeMasterForm.errors.confirmation" class="mt-1 text-xs text-rose-600">
                            {{ purgeMasterForm.errors.confirmation }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="purgeMasterForm.processing || purgeMasterForm.confirmation !== 'HAPUS MASTER' || summary.documents_count > 0"
                        class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800 disabled:opacity-50 transition"
                    >
                        <span v-if="purgeMasterForm.processing">Membersihkan...</span>
                        <span v-else>Bersihkan Data Master</span>
                    </button>
                </div>
                <p v-if="summary.documents_count > 0" class="text-[11px] text-amber-800">
                    * Tombol ini aktif setelah jumlah Dokumen Soal menjadi 0.
                </p>
            </form>
        </div>

        <ConfirmDialog
            :show="showResetDialog"
            title="Reset Pengaturan Default SIMSOAL?"
            :message="
                resetForm.remove_custom
                    ? 'Preset Format F4 dan Template Kop Halaman Pertama akan dikembalikan ke standar baku SIMSOAL, dan seluruh preset/template kustom yang tidak terpakai akan dihapus.'
                    : 'Preset Format F4 dan Template Kop Halaman Pertama akan dipulihkan ke nilai standar baku SIMSOAL dan dijadikan default aktif.'
            "
            confirm-text="Ya, Pulihkan Default"
            variant="primary"
            :processing="resetForm.processing"
            @confirm="triggerResetDefaults"
            @close="showResetDialog = false"
        />
    </AppLayout>
</template>