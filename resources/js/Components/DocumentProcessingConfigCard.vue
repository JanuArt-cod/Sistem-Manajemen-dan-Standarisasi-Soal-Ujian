<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    examDocumentId: {
        type: Number,
        required: true,
    },
    currentConfig: {
        type: Object,
        default: null,
    },
    presets: {
        type: Array,
        required: true,
    },
    templates: {
        type: Array,
        required: true,
    },
    canConfigure: {
        type: Boolean,
        default: true,
    },
});

const defaultPreset = props.presets.find((p) => p.is_default) || props.presets[0];
const defaultTemplate = props.templates.find((t) => t.is_default) || props.templates[0];

const form = useForm({
    formatting_preset_id: props.currentConfig?.formatting_preset_id ?? defaultPreset?.id ?? '',
    document_template_id: props.currentConfig?.document_template_id ?? defaultTemplate?.id ?? '',
});

const submit = () => {
    form.put(`/exam-documents/${props.examDocumentId}/processing-config`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-slate-900">Konfigurasi Standarisasi Format & Kop</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Pilih preset kertas F4 dan template kop halaman pertama untuk dikunci sebagai snapshot sebelum proses standarisasi.
                </p>
            </div>
            <span
                v-if="currentConfig"
                class="rounded-md bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-[11px] font-semibold text-emerald-800"
            >
                Snapshot Terkunci
            </span>
        </div>

        <form @submit.prevent="submit" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Preset Format Halaman & Font</label>
                    <select
                        v-model="form.formatting_preset_id"
                        :disabled="!canConfigure || form.processing"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                    >
                        <option v-for="preset in presets" :key="preset.id" :value="preset.id">
                            {{ preset.name }} ({{ preset.paper_width_mm }}×{{ preset.paper_height_mm }} mm)
                        </option>
                    </select>
                    <p v-if="form.errors.formatting_preset_id" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.formatting_preset_id }}
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Template Kop (Halaman Pertama)</label>
                    <select
                        v-model="form.document_template_id"
                        :disabled="!canConfigure || form.processing"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                    >
                        <option v-for="template in templates" :key="template.id" :value="template.id">
                            {{ template.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.document_template_id" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.document_template_id }}
                    </p>
                </div>
            </div>

            <div
                v-if="currentConfig?.snapshot_payload"
                class="rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-700 space-y-1.5"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-slate-900">Ringkasan Snapshot Aktif:</span>
                    <span class="font-mono text-[11px] text-slate-500">
                        SHA-256: {{ currentConfig.snapshot_checksum.slice(0, 16) }}…
                    </span>
                </div>
                <p>
                    Kertas: <strong>{{ currentConfig.snapshot_payload.preset?.paper_code }}</strong>
                    ({{ currentConfig.snapshot_payload.preset?.paper_width_mm }} × {{ currentConfig.snapshot_payload.preset?.paper_height_mm }} mm),
                    Margin: {{ currentConfig.snapshot_payload.preset?.margin_top_mm }}/{{ currentConfig.snapshot_payload.preset?.margin_bottom_mm }}/{{ currentConfig.snapshot_payload.preset?.margin_left_mm }}/{{ currentConfig.snapshot_payload.preset?.margin_right_mm }} mm,
                    Font: {{ currentConfig.snapshot_payload.preset?.font_family }} {{ currentConfig.snapshot_payload.preset?.font_size_pt }} pt.
                </p>
                <p>
                    Kop: <strong>{{ currentConfig.snapshot_payload.template?.name }}</strong>
                    (Halaman pertama saja: Ya).
                </p>
            </div>

            <div v-if="canConfigure" class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                >
                    <span v-if="form.processing">Mengunci Snapshot...</span>
                    <span v-else>{{ currentConfig ? 'Perbarui & Kunci Ulang Snapshot' : 'Kunci Konfigurasi Dokumen' }}</span>
                </button>
            </div>
        </form>
    </div>
</template>