<script setup>
import { ref } from 'vue';
import AppHeader from '../Components/AppHeader.vue';
import AppSidebar from '../Components/AppSidebar.vue';
import Toast from '../Components/Toast.vue';

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

const mobileSidebarOpen = ref(false);
</script>

<template>
    <div class="min-h-screen lg:h-screen lg:overflow-hidden flex bg-slate-50 text-slate-900">
        <AppSidebar
            :mobile-open="mobileSidebarOpen"
            @close="mobileSidebarOpen = false"
        />

        <div class="flex flex-1 flex-col min-w-0 lg:h-screen lg:overflow-hidden">
            <AppHeader
                :title="title"
                :breadcrumb="breadcrumb"
                @toggle-sidebar="mobileSidebarOpen = !mobileSidebarOpen"
            />

            <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                <div class="mx-auto max-w-6xl">
                    <slot />
                </div>
            </main>
        </div>

        <Toast />
    </div>
</template>