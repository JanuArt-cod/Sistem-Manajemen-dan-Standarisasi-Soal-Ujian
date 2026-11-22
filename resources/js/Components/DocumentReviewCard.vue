<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
});

const resolutionNotes = reactive({});
const resolvingId = ref(null);
const resolveForm = useForm({
    resolution_note: '',
});

const reviewForm = useForm({
    decision: 'APPROVED',
    review_notes: props.document.review_notes || '',
});

const warnings = computed(() => props.document.latest_run?.warnings || []);

const unresolvedCriticalCount = computed(() => {
    return warnings.value.filter((w) => w.severity === 'REVIEW_REQUIRED' && !w.is_resolved).length;
});

const canApprove = computed(() => {
    return props.document.can_review && unresolvedCriticalCount.value === 0;
});

const markWarningResolved = (warningId) => {
    resolvingId.value = warningId;
    resolveForm.resolution_note = resolutionNotes[warningId] || '';
    resolveForm.patch(`/exam-documents/${props.document.id}/warnings/${warningId}/resolve`, {
        preserveScroll: true,
        onFinish: () => {
            resolvingId.value = null;
        },
    });
};

const submitDecision = (decision) => {
    reviewForm.decision = decision;
    reviewForm.post(`/exam-documents/${props.document.id}/review`, {
        preserveScroll: true,
    });
};

const warningBadgeClass = (severity) => {
    switch (severity) {
        case 'REVIEW_REQUIRED':
            return 'bg-rose-50 text-rose-800 border-rose-200';
        case 'WARNING':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Pemeriksaan Peringatan & Persetujuan Naskah</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Selesaikan pemeriksaan struktur yang ditandai mesin, lalu tetapkan apakah naskah disetujui cetak atau perlu revisi.
                </p>
            </div>
        </div>

        <!-- Banner Status Persetujuan / Revisi Saat Ini -->
        <div
            v-if="document.current_status === 'APPROVED'"
            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-900 space-y-1"
        >
            <p class="font-bold text-sm">Naskah Telah Disetujui (APPROVED)</p>
            <p>
                Disetujui oleh <strong>{{ document.approver_name || document.reviewer_name || 'Operator' }}</strong>
                <template v-if="document.approved_at"> pada {{ document.approved_at }}</template>.
            </p>
            <p v-if="document.review_notes" class="pt-1 text-emerald-800">
                Catatan: {{ document.review_notes }}
            </p>
        </div>

        <div
            v-else-if="document.current_status === 'REVISION_REQUIRED'"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-950 space-y-1"
        >
            <p class="font-bold text-sm">Naskah Ditandai Perlu Revisi (REVISION_REQUIRED)</p>
            <p>
                Diperiksa oleh <strong>{{ document.reviewer_name || 'Operator' }}</strong>
                <template v-if="document.reviewed_at"> pada {{ document.reviewed_at }}</template>.
            </p>
            <p v-if="document.review_notes" class="pt-1 font-medium text-amber-900">
                Catatan Revisi: {{ document.review_notes }}
            </p>
        </div>

        <!-- Daftar Peringatan Struktural yang Dapat Ditandai Selesai -->
        <div v-if="warnings.length > 0" class="space-y-2.5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-800">
                    Daftar Peringatan Eksekusi Terakhir ({{ warnings.length }})
                </p>
                <span
                    v-if="unresolvedCriticalCount > 0"
                    class="rounded bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-800"
                >
                    {{ unresolvedCriticalCount }} peringatan wajib diperiksa
                </span>
            </div>

            <div
                v-for="warn in warnings"
                :key="warn.id"
                class="rounded-lg border p-3.5 text-xs space-y-2"
                :class="warn.is_resolved ? 'border-emerald-200 bg-emerald-50/40' : 'border-slate-200 bg-slate-50'"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="font-mono font-semibold text-slate-800">{{ warn.code }}</span>
                        <span
                            class="rounded border px-2 py-0.5 text-[11px] font-semibold"
                            :class="warningBadgeClass(warn.severity)"
                        >
                            {{ warn.severity_label }}
                        </span>
                    </div>

                    <span
                        v-if="warn.is_resolved"
                        class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                    >
                        Selesai Diperiksa
                    </span>
                </div>

                <p class="text-slate-700">{{ warn.message }}</p>

                <div v-if="warn.is_resolved" class="text-[11px] text-emerald-800">
                    Diverifikasi oleh {{ warn.resolved_by_name || 'Operator' }}
                    <template v-if="warn.resolved_at"> ({{ warn.resolved_at }})</template>
                    <template v-if="warn.resolution_note"> — {{ warn.resolution_note }}</template>
                </div>

                <div
                    v-else-if="document.can_edit"
                    class="flex flex-col gap-2 pt-1 sm:flex-row sm:items-center"
                >
                    <input
                        v-model="resolutionNotes[warn.id]"
                        type="text"
                        placeholder="Catatan hasil pemeriksaan visual (opsional)..."
                        class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs"
                    />
                    <button
                        type="button"
                        :disabled="resolveForm.processing && resolvingId === warn.id"
                        @click="markWarningResolved(warn.id)"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-100 disabled:opacity-50 transition whitespace-nowrap"
                    >
                        <span v-if="resolveForm.processing && resolvingId === warn.id">Menyimpan...</span>
                        <span v-else>Tandai Selesai Diperiksa</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel Keputusan Persetujuan / Revisi -->
        <div v-if="document.can_review" class="border-t border-slate-100 pt-4 space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Catatan Pemeriksaan / Alasan Revisi
                </label>
                <textarea
                    v-model="reviewForm.review_notes"
                    rows="2"
                    placeholder="Opsional untuk persetujuan, wajib diisi jika meminta revisi naskah..."
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs"
                />
                <p v-if="reviewForm.errors.review_notes" class="mt-1 text-xs text-rose-600">
                    {{ reviewForm.errors.review_notes }}
                </p>
            </div>

            <div
                v-if="unresolvedCriticalCount > 0"
                class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800"
            >
                Tombol <strong>Setujui Dokumen</strong> terkunci sampai seluruh peringatan bertingkat <strong>Perlu Tinjauan</strong> ditandai selesai diperiksa.
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    :disabled="reviewForm.processing"
                    @click="submitDecision('REVISION_REQUIRED')"
                    class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-900 hover:bg-amber-100 disabled:opacity-50 transition"
                >
                    Minta Revisi (REVISION_REQUIRED)
                </button>
                <button
                    type="button"
                    :disabled="reviewForm.processing || !canApprove"
                    @click="submitDecision('APPROVED')"
                    class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800 disabled:opacity-50 transition"
                >
                    Setujui Dokumen (APPROVED)
                </button>
            </div>
        </div>

        <div
            v-else-if="!document.output_docx_file"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3.5 text-xs text-slate-600"
        >
            Jalankan standarisasi dokumen terlebih dahulu sebelum melakukan pemeriksaan akhir dan persetujuan.
        </div>
    </div>
</template>