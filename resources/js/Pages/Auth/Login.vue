<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Masuk Sistem" />

    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-xl shadow-sm p-8">
            <div class="mb-6">
                <div class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-800 border border-blue-200">
                    SIMSOAL — Internal Sekolah
                </div>
                <h1 class="mt-3 text-xl font-bold text-slate-900">
                    Masuk ke Aplikasi
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Sistem Manajemen dan Standarisasi Soal Ujian
                </p>
            </div>

            <div
                v-if="flashSuccess"
                class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
            >
                {{ flashSuccess }}
            </div>

            <div
                v-if="flashError"
                class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"
            >
                {{ flashError }}
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">
                        Email Pengguna
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        placeholder="operator@sekolah.sch.id"
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs text-rose-600">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1">
                        Kata Sandi
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-600">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div class="flex items-center">
                    <input
                        id="remember"
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-800 focus:ring-blue-700"
                    />
                    <label for="remember" class="ml-2 block text-sm text-slate-600">
                        Ingat sesi masuk di komputer ini
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full inline-flex justify-center items-center rounded-lg bg-blue-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed transition"
                >
                    <span v-if="form.processing">Memverifikasi...</span>
                    <span v-else>Masuk</span>
                </button>
            </form>
        </div>
    </div>
</template>