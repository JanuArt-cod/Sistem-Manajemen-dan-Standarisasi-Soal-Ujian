<script setup>
import { ref } from 'vue';

defineProps({
    pdfFile: {
        type: Object,
        default: null,
    },
    currentStatus: {
        type: String,
        required: true,
    },
});

const isExpanded = ref(false);
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-slate-900">Pratinjau Hasil Cetak PDF (F4)</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Periksa tata letak kop halaman pertama, margin, tabel, gambar, dan nomor halaman sebelum menyetujui naskah.
                </p>
            </div>

            <div v-if="pdfFile" class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="isExpanded = !isExpanded"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    {{ isExpanded ? 'Tinggi Standar' : 'Perbesar Tampilan' }}
                </button>
                <a
                    :href="pdfFile.preview_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                >
                    Buka Tab Baru
                </a>
                <a
                    :href="pdfFile.download_url"
                    class="rounded-lg bg-rose-700 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-rose-800 transition"
                >
                    Unduh PDF
                </a>
            </div>
        </div>

        <div v-if="pdfFile" class="overflow-hidden rounded-lg border border-slate-300 bg-slate-100">
            <iframe
                :src="pdfFile.preview_url"
                :title="`Pratinjau ${pdfFile.original_filename}`"
                class="w-full transition-all duration-200"
                :class="isExpanded ? 'h-[860px]' : 'h-[620px]'"
            />
        </div>

        <div
            v-else
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-xs text-slate-600"
        >
            <span v-if="currentStatus === 'PDF_FAILED'">
                Pratinjau PDF belum tersedia karena konversi PDF gagal pada eksekusi terakhir. Gunakan tombol <strong>Ekspor PDF dari Hasil DOCX</strong> di panel kanan untuk mencoba ulang.
            </span>
            <span v-else>
                Pratinjau PDF F4 akan tampil di sini secara otomatis setelah proses standarisasi dokumen selesai dijalankan.
            </span>
        </div>
    </div>
</template>