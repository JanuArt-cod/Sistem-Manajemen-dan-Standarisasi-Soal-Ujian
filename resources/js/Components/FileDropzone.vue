<script setup>
import { ref } from 'vue';

const props = defineProps({
    maxUploadMb: {
        type: Number,
        default: 30,
    },
    maxFiles: {
        type: Number,
        default: 20,
    },
});

const emit = defineEmits(['filesSelected']);

const isDragging = ref(false);
const fileInput = ref(null);
const localWarnings = ref([]);

const processFiles = (fileList) => {
    localWarnings.value = [];
    const incoming = Array.from(fileList || []);
    const accepted = [];

    for (const file of incoming) {
        const lowerName = file.name.toLowerCase();
        if (!lowerName.endsWith('.docx')) {
            localWarnings.value.push(
                `"${file.name}" ditolak: versi MVP hanya menerima file .docx.`
            );
            continue;
        }

        if (file.size > props.maxUploadMb * 1024 * 1024) {
            localWarnings.value.push(
                `"${file.name}" ditolak: ukuran melebihi batas ${props.maxUploadMb} MB.`
            );
            continue;
        }

        accepted.push(file);
    }

    if (accepted.length > props.maxFiles) {
        localWarnings.value.push(
            `Maksimal ${props.maxFiles} file dalam satu batch. File selebihnya diabaikan.`
        );
    }

    const limited = accepted.slice(0, props.maxFiles);
    if (limited.length > 0) {
        emit('filesSelected', limited);
    }
};

const onDrop = (event) => {
    isDragging.value = false;
    processFiles(event.dataTransfer?.files);
};

const onInputChange = (event) => {
    processFiles(event.target?.files);
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};
</script>

<template>
    <div>
        <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="onDrop"
            class="rounded-xl border-2 border-dashed p-8 text-center transition"
            :class="
                isDragging
                    ? 'border-blue-700 bg-blue-50/70'
                    : 'border-slate-300 bg-slate-50/60 hover:border-slate-400'
            "
        >
            <p class="text-sm font-semibold text-slate-900">
                Tarik dan lepas file soal (.docx) ke area ini
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Mendukung unggah banyak file sekaligus • Format MVP: DOCX • Maksimal {{ maxUploadMb }} MB per file
            </p>

            <input
                ref="fileInput"
                type="file"
                accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                multiple
                class="hidden"
                @change="onInputChange"
            />

            <button
                type="button"
                @click="fileInput?.click()"
                class="mt-4 inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-2xs hover:bg-slate-100 transition"
            >
                Pilih File dari Komputer
            </button>
        </div>

        <div
            v-if="localWarnings.length > 0"
            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 space-y-1"
        >
            <p class="font-semibold">Perhatian Validasi File:</p>
            <ul class="list-disc pl-4 space-y-0.5">
                <li v-for="(warn, idx) in localWarnings" :key="idx">{{ warn }}</li>
            </ul>
        </div>
    </div>
</template>