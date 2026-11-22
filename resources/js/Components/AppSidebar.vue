<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mobileOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => user.value?.role === 'ADMIN');

const navigationItems = computed(() => {
    const items = [
        {
            name: 'Dashboard',
            href: '/dashboard',
            active: page.url === '/dashboard',
        },
        {
            name: 'Periode Ujian',
            href: '/exam-periods',
            active: page.url.startsWith('/exam-periods'),
        },
        {
            name: 'Dokumen Soal',
            href: '/exam-documents',
            active: page.url.startsWith('/exam-documents'),
        },
        {
            name: 'Preset Format',
            href: '/formatting-presets',
            active: page.url.startsWith('/formatting-presets'),
        },
        {
            name: 'Template Kop',
            href: '/document-templates',
            active: page.url.startsWith('/document-templates'),
        },
        {
            name: 'Data Referensi',
            href: '/master-data',
            active: page.url.startsWith('/master-data'),
        },
    ];

    if (isAdmin.value) {
        items.push(
            {
                name: 'Profil Sekolah',
                href: '/school-profile',
                active: page.url.startsWith('/school-profile'),
            },
            {
                name: 'Pengguna',
                href: '/users',
                active: page.url.startsWith('/users'),
            },
            {
                name: 'Pemeliharaan Sistem',
                href: '/system-maintenance',
                active: page.url.startsWith('/system-maintenance'),
            }
        );
    }

    return items;
});
</script>

<template>
    <div
        v-if="mobileOpen"
        class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
        @click="emit('close')"
    />

    <aside
        class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform lg:static lg:h-screen lg:translate-x-0"
        :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-6">
            <div>
                <span class="text-base font-bold tracking-tight text-blue-900">SIMSOAL</span>
                <p class="text-[11px] font-medium text-slate-500">Standarisasi Naskah Ujian</p>
            </div>
            <button
                type="button"
                @click="emit('close')"
                class="rounded-md p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                aria-label="Tutup menu"
            >
                ✕
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto space-y-1 px-3 py-4" aria-label="Navigasi Utama">
            <Link
                v-for="item in navigationItems"
                :key="item.href"
                :href="item.href"
                @click="emit('close')"
                class="flex items-center rounded-lg px-3.5 py-2.5 text-sm font-medium transition"
                :class="
                    item.active
                        ? 'bg-blue-50 text-blue-900 font-semibold border border-blue-100'
                        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900'
                "
            >
                {{ item.name }}
            </Link>
        </nav>

        <div class="shrink-0 border-t border-slate-200 p-4">
            <div class="rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                <p class="font-semibold text-slate-800">Standar Kertas Aktif</p>
                <p class="mt-0.5">F4 (215 × 330 mm) • TNR 12 pt</p>
            </div>
        </div>
    </aside>
</template>