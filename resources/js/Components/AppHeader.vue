<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDialog from './ConfirmDialog.vue';
import StatusBadge from './StatusBadge.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
    breadcrumb: {
        type: String,
        default: 'SIMSOAL',
    },
});

const emit = defineEmits(['toggleSidebar']);

const page = usePage();
const user = computed(() => page.props.auth?.user);
const showLogoutConfirm = ref(false);
const loggingOut = ref(false);

const confirmLogout = () => {
    loggingOut.value = true;
    router.post('/logout', {}, {
        onFinish: () => {
            loggingOut.value = false;
            showLogoutConfirm.value = false;
        },
    });
};
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 lg:px-8">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="emit('toggleSidebar')"
                class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-sm text-slate-700 hover:bg-slate-100 lg:hidden"
                aria-label="Buka menu"
            >
                Menu
            </button>
            <div>
                <p class="text-xs text-slate-500">{{ breadcrumb }}</p>
                <p class="text-sm font-semibold text-slate-900">{{ title }}</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <div class="hidden text-right sm:block">
                <p class="text-sm font-medium text-slate-900">{{ user?.name }}</p>
                <p class="text-xs text-slate-500">{{ user?.email }}</p>
            </div>

            <StatusBadge v-if="user?.role" :status="user.role" />

            <button
                type="button"
                @click="showLogoutConfirm = true"
                class="rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
            >
                Keluar
            </button>
        </div>

        <ConfirmDialog
            :show="showLogoutConfirm"
            title="Keluar dari SIMSOAL"
            message="Apakah Anda yakin ingin mengakhiri sesi kerja saat ini?"
            confirm-text="Ya, Keluar"
            :processing="loggingOut"
            @close="showLogoutConfirm = false"
            @confirm="confirmLogout"
        />
    </header>
</template>