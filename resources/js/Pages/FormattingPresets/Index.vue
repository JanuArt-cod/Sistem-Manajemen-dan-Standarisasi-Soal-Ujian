<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    presets: {
        type: Array,
        required: true,
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    paperCodes: {
        type: Array,
        required: true,
    },
    orientations: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);
const editingPreset = ref(null);

const form = useForm({
    name: '',
    description: '',
    paper_code: 'F4',
    paper_width_mm: 215,
    paper_height_mm: 330,
    orientation: 'PORTRAIT',
    margin_top_mm: 20,
    margin_bottom_mm: 20,
    margin_left_mm: 25,
    margin_right_mm: 20,
    font_family: 'Times New Roman',
    font_size_pt: 12,
    line_spacing: 1.0,
    paragraph_spacing_before_pt: 0,
    paragraph_spacing_after_pt: 4,
    preserve_original_styles: true,
    apply_to_existing_tables: false,
    is_default: false,
    is_active: true,
});

const handlePaperCodeChange = () => {
    const selected = props.paperCodes.find((p) => p.value === form.paper_code);
    if (selected && form.paper_code !== 'CUSTOM') {
        form.paper_width_mm = selected.dimensions.width_mm;
        form.paper_height_mm = selected.dimensions.height_mm;
    }
};

const openCreateModal = () => {
    editingPreset.value = null;
    form.reset();
    form.clearErrors();
    form.paper_code = 'F4';
    form.paper_width_mm = 215;
    form.paper_height_mm = 330;
    form.orientation = 'PORTRAIT';
    form.margin_top_mm = 20;
    form.margin_bottom_mm = 20;
    form.margin_left_mm = 25;
    form.margin_right_mm = 20;
    form.font_family = 'Times New Roman';
    form.font_size_pt = 12;
    form.line_spacing = 1.0;
    form.paragraph_spacing_before_pt = 0;
    form.paragraph_spacing_after_pt = 4;
    form.preserve_original_styles = true;
    form.apply_to_existing_tables = false;
    form.is_default = false;
    form.is_active = true;
    showModal.value = true;
};

const openEditModal = (preset) => {
    editingPreset.value = preset;
    form.clearErrors();
    form.name = preset.name;
    form.description = preset.description || '';
    form.paper_code = preset.paper_code;
    form.paper_width_mm = preset.paper_width_mm;
    form.paper_height_mm = preset.paper_height_mm;
    form.orientation = preset.orientation;
    form.margin_top_mm = preset.margin_top_mm;
    form.margin_bottom_mm = preset.margin_bottom_mm;
    form.margin_left_mm = preset.margin_left_mm;
    form.margin_right_mm = preset.margin_right_mm;
    form.font_family = preset.font_family;
    form.font_size_pt = preset.font_size_pt;
    form.line_spacing = preset.line_spacing;
    form.paragraph_spacing_before_pt = preset.paragraph_spacing_before_pt;
    form.paragraph_spacing_after_pt = preset.paragraph_spacing_after_pt;
    form.preserve_original_styles = preset.preserve_original_styles;
    form.apply_to_existing_tables = preset.apply_to_existing_tables;
    form.is_default = preset.is_default;
    form.is_active = preset.is_active;
    showModal.value = true;
};

const closeModal = () => {
    if (form.processing) return;
    showModal.value = false;
    editingPreset.value = null;
};

const submit = () => {
    if (editingPreset.value) {
        form.put(`/formatting-presets/${editingPreset.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
                editingPreset.value = null;
            },
        });
    } else {
        form.post('/formatting-presets', {
            preserveScroll: true,
            onSuccess: () => {
                showModal.value = false;
            },
        });
    }
};

const setDefault = (preset) => {
    router.patch(`/formatting-presets/${preset.id}/default`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Preset Format Halaman & Font" />

    <AppLayout title="Preset Format" breadcrumb="SIMSOAL / Standarisasi / Preset Format">
        <PageHeader
            title="Preset Format Halaman & Tipografi"
            description="Standar ukuran kertas (default F4 215 × 330 mm), margin halaman, dan tipografi tanpa mengubah isi soal."
        >
            <template #actions>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openCreateModal"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Buat Preset Baru
                </button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div
                v-for="preset in presets"
                :key="preset.id"
                class="rounded-xl border bg-white p-6 shadow-xs flex flex-col justify-between"
                :class="preset.is_default ? 'border-blue-300 ring-1 ring-blue-100' : 'border-slate-200'"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-bold text-slate-900">{{ preset.name }}</h2>
                                <span
                                    v-if="preset.is_default"
                                    class="rounded-md bg-blue-800 px-2 py-0.5 text-[11px] font-semibold text-white"
                                >
                                    Default Sistem
                                </span>
                            </div>
                            <p v-if="preset.description" class="mt-1 text-xs text-slate-500">
                                {{ preset.description }}
                            </p>
                        </div>
                        <StatusBadge :status="preset.is_active ? 'ACTIVE' : 'INACTIVE'" />
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs">
                        <div>
                            <p class="font-medium text-slate-500">Ukuran & Orientasi</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                {{ preset.paper_code }} ({{ preset.paper_width_mm }} × {{ preset.paper_height_mm }} mm)
                            </p>
                            <p class="text-slate-600">{{ preset.orientation_label }}</p>
                        </div>

                        <div>
                            <p class="font-medium text-slate-500">Tipografi & Spasi</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                {{ preset.font_family }} {{ preset.font_size_pt }} pt
                            </p>
                            <p class="text-slate-600">Spasi baris {{ preset.line_spacing }}</p>
                        </div>

                        <div>
                            <p class="font-medium text-slate-500">Margin Halaman (mm)</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                Atas {{ preset.margin_top_mm }} • Bawah {{ preset.margin_bottom_mm }}
                            </p>
                            <p class="text-slate-600">
                                Kiri {{ preset.margin_left_mm }} • Kanan {{ preset.margin_right_mm }}
                            </p>
                        </div>

                        <div>
                            <p class="font-medium text-slate-500">Perlindungan Konten</p>
                            <p class="mt-0.5 font-semibold text-emerald-800">
                                {{ preset.preserve_original_styles ? 'Gaya Inline Dipertahankan' : 'Normalisasi Penuh' }}
                            </p>
                            <p class="text-slate-600">
                                {{ preset.apply_to_existing_tables ? 'Sesuaikan font tabel' : 'Tabel eksisting utuh' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="canManage"
                    class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4"
                >
                    <button
                        v-if="!preset.is_default"
                        type="button"
                        @click="setDefault(preset)"
                        class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-800 hover:bg-blue-100"
                    >
                        Jadikan Default
                    </button>
                    <button
                        type="button"
                        @click="openEditModal(preset)"
                        class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Ubah Preset
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Buat / Ubah Preset -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4 py-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-2xl rounded-xl border border-slate-200 bg-white p-6 shadow-xl my-auto">
                <h2 class="text-base font-bold text-slate-900">
                    {{ editingPreset ? 'Ubah Preset Format' : 'Buat Preset Format Baru' }}
                </h2>

                <form @submit.prevent="submit" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Preset</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Contoh: Standar Ujian F4 (215 × 330 mm)"
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

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Kode Kertas</label>
                            <select
                                v-model="form.paper_code"
                                @change="handlePaperCodeChange"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            >
                                <option v-for="code in paperCodes" :key="code.value" :value="code.value">
                                    {{ code.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Lebar (mm)</label>
                            <input
                                v-model="form.paper_width_mm"
                                type="number"
                                step="0.1"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Tinggi (mm)</label>
                            <input
                                v-model="form.paper_height_mm"
                                type="number"
                                step="0.1"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Margin Atas (mm)</label>
                            <input
                                v-model="form.margin_top_mm"
                                type="number"
                                step="0.5"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Margin Bawah (mm)</label>
                            <input
                                v-model="form.margin_bottom_mm"
                                type="number"
                                step="0.5"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Margin Kiri (mm)</label>
                            <input
                                v-model="form.margin_left_mm"
                                type="number"
                                step="0.5"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Margin Kanan (mm)</label>
                            <input
                                v-model="form.margin_right_mm"
                                type="number"
                                step="0.5"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Jenis Font</label>
                            <input
                                v-model="form.font_family"
                                type="text"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Ukuran (pt)</label>
                            <input
                                v-model="form.font_size_pt"
                                type="number"
                                step="0.5"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Spasi Baris</label>
                            <input
                                v-model="form.line_spacing"
                                type="number"
                                step="0.05"
                                required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                        </div>
                    </div>

                    <div class="space-y-2 border-t border-slate-100 pt-3">
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input v-model="form.preserve_original_styles" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                            <span>Pertahankan penekanan teks asli guru (tebal, miring, garis bawah, pangkat/indeks, rumus)</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                            <span>Tetapkan sebagai preset default sistem</span>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-blue-800" />
                            <span>Preset aktif dan dapat dipilih pada dokumen</span>
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
                            <span v-else>Simpan Preset</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>