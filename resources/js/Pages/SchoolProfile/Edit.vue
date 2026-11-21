<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    profile: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    _method: 'PUT',
    institution_name: props.profile?.institution_name || '',
    foundation_name: props.profile?.foundation_name || '',
    address: props.profile?.address || '',
    phone: props.profile?.phone || '',
    email: props.profile?.email || '',
    header_lines:
        props.profile?.header_lines && props.profile.header_lines.length > 0
            ? [...props.profile.header_lines]
            : ['PEMERINTAH PROVINSI / YAYASAN', 'DINAS PENDIDIKAN'],
    logo: null,
    remove_logo: false,
});

const addHeaderLine = () => {
    if (form.header_lines.length < 6) {
        form.header_lines.push('');
    }
};

const removeHeaderLine = (index) => {
    form.header_lines.splice(index, 1);
};

const handleLogoChange = (event) => {
    const file = event.target.files?.[0] || null;
    form.logo = file;
    if (file) {
        form.remove_logo = false;
    }
};

const submit = () => {
    form.post('/school-profile', {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Profil Sekolah & Kop" />

    <AppLayout title="Profil Sekolah" breadcrumb="SIMSOAL / Administrasi / Profil Sekolah">
        <PageHeader
            title="Profil Sekolah & Identitas Kop"
            description="Konfigurasi identitas resmi sekolah yang digunakan sebagai sumber kop surat pada halaman pertama dokumen ujian."
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <form
                @submit.prevent="submit"
                class="lg:col-span-3 space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
            >
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Nama Yayasan / Instansi Pembina (Opsional)
                    </label>
                    <input
                        v-model="form.foundation_name"
                        type="text"
                        placeholder="Contoh: YAYASAN PENDIDIKAN ISLAM"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p v-if="form.errors.foundation_name" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.foundation_name }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Nama Satuan Pendidikan / Sekolah <span class="text-rose-600">*</span>
                    </label>
                    <input
                        v-model="form.institution_name"
                        type="text"
                        required
                        placeholder="Contoh: SMK BINA INSAN MANDIRI"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p v-if="form.errors.institution_name" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.institution_name }}
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-slate-700">
                            Baris Teks Tambahan Kop (Maks. 6 Baris)
                        </label>
                        <button
                            v-if="form.header_lines.length < 6"
                            type="button"
                            @click="addHeaderLine"
                            class="text-xs font-semibold text-blue-800 hover:underline"
                        >
                            + Tambah Baris
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(line, idx) in form.header_lines"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="form.header_lines[idx]"
                                type="text"
                                :placeholder="`Baris kop ${idx + 1}`"
                                class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <button
                                type="button"
                                @click="removeHeaderLine(idx)"
                                class="rounded-lg border border-slate-300 px-2.5 py-2 text-xs text-slate-600 hover:bg-slate-100"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Alamat Lengkap Sekolah</label>
                    <textarea
                        v-model="form.address"
                        rows="2"
                        placeholder="Jl. Raya Sekolah No. 1, Kec. ..., Kab./Kota ..."
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p v-if="form.errors.address" class="mt-1 text-xs text-rose-600">{{ form.errors.address }}</p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nomor Telepon</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="(0251) 123456"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email Resmi Sekolah</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="info@sekolah.sch.id"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Logo Sekolah (PNG / JPG, Maks. 2 MB)
                    </label>
                    <input
                        type="file"
                        accept="image/png,image/jpeg"
                        @change="handleLogoChange"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-blue-800 hover:file:bg-blue-100"
                    />
                    <p v-if="form.errors.logo" class="mt-1 text-xs text-rose-600">{{ form.errors.logo }}</p>

                    <div v-if="profile?.has_logo" class="mt-2 flex items-center gap-2">
                        <input
                            id="remove_logo"
                            v-model="form.remove_logo"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-rose-700"
                        />
                        <label for="remove_logo" class="text-xs text-slate-600">
                            Hapus logo sekolah saat ini
                        </label>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4 flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50 transition"
                    >
                        <span v-if="form.processing">Menyimpan...</span>
                        <span v-else>Simpan Profil Sekolah</span>
                    </button>
                </div>
            </form>

            <!-- Pratinjau Kop Surat Halaman Pertama -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
                    <h2 class="text-sm font-bold text-slate-900">Pratinjau Blok Kop (Halaman Pertama)</h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Simulasi susunan teks kop sebelum diterapkan pada preset dokumen F4.
                    </p>

                    <div class="mt-4 rounded-lg border border-slate-300 bg-white p-5 text-center font-serif">
                        <div class="flex items-center justify-center gap-4">
                            <img
                                v-if="profile?.has_logo && !form.remove_logo"
                                :src="profile.logo_url"
                                alt="Logo Sekolah"
                                class="h-14 w-14 object-contain"
                            />
                            <div class="flex-1">
                                <p v-if="form.foundation_name" class="text-xs font-semibold uppercase tracking-wide text-slate-700">
                                    {{ form.foundation_name }}
                                </p>
                                <p
                                    v-for="(line, idx) in form.header_lines.filter((l) => l && l.trim() !== '')"
                                    :key="idx"
                                    class="text-xs uppercase text-slate-700"
                                >
                                    {{ line }}
                                </p>
                                <p class="mt-0.5 text-base font-bold uppercase text-slate-900">
                                    {{ form.institution_name || 'NAMA SEKOLAH' }}
                                </p>
                                <p v-if="form.address" class="mt-0.5 text-[11px] text-slate-600">
                                    {{ form.address }}
                                </p>
                                <p v-if="form.phone || form.email" class="text-[11px] text-slate-600">
                                    <span v-if="form.phone">Telp. {{ form.phone }}</span>
                                    <span v-if="form.phone && form.email"> • </span>
                                    <span v-if="form.email">Email: {{ form.email }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 border-b-2 border-slate-900"></div>
                        <div class="mt-0.5 border-b border-slate-900"></div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>