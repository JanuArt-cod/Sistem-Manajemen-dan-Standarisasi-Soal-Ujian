<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const filterForm = ref({
    search: props.filters.search || '',
    role: props.filters.role || '',
    status: props.filters.status || '',
});

const applyFilters = () => {
    router.get(
        '/users',
        {
            search: filterForm.value.search || undefined,
            role: filterForm.value.role || undefined,
            status: filterForm.value.status || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    filterForm.value = { search: '', role: '', status: '' };
    applyFilters();
};

const showUserModal = ref(false);
const editingUser = ref(null);

const userForm = useForm({
    name: '',
    email: '',
    role: 'OPERATOR',
    password: '',
    password_confirmation: '',
    is_active: true,
});

const openCreateModal = () => {
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.role = 'OPERATOR';
    userForm.is_active = true;
    showUserModal.value = true;
};

const openEditModal = (user) => {
    editingUser.value = user;
    userForm.clearErrors();
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.role = user.role;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.is_active = user.is_active;
    showUserModal.value = true;
};

const closeUserModal = () => {
    if (userForm.processing) return;
    showUserModal.value = false;
    editingUser.value = null;
};

const submitUserForm = () => {
    if (editingUser.value) {
        userForm.put(`/users/${editingUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showUserModal.value = false;
                editingUser.value = null;
            },
        });
    } else {
        userForm.post('/users', {
            preserveScroll: true,
            onSuccess: () => {
                showUserModal.value = false;
                userForm.reset();
            },
        });
    }
};

const toggleTarget = ref(null);
const toggling = ref(false);

const confirmToggleStatus = () => {
    if (!toggleTarget.value) return;
    toggling.value = true;
    router.patch(
        `/users/${toggleTarget.value.id}/toggle-active`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                toggling.value = false;
                toggleTarget.value = null;
            },
        }
    );
};
</script>

<template>
    <Head title="Manajemen Pengguna" />

    <AppLayout title="Manajemen Pengguna" breadcrumb="SIMSOAL / Administrasi / Pengguna">
        <PageHeader
            title="Pengguna Sistem"
            description="Kelola akun Administrator dan Operator sekolah yang memiliki akses ke SIMSOAL."
        >
            <template #actions>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 transition"
                >
                    Tambah Pengguna
                </button>
            </template>
        </PageHeader>

        <!-- Filter Bar -->
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <input
                    v-model="filterForm.search"
                    type="text"
                    placeholder="Cari nama atau email..."
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                />

                <select
                    v-model="filterForm.role"
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Peran</option>
                    <option v-for="r in roles" :key="r.value" :value="r.value">
                        {{ r.label }}
                    </option>
                </select>

                <select
                    v-model="filterForm.status"
                    class="rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                >
                    <option value="">Semua Status</option>
                    <option value="active">Aktif</option>
                    <option value="inactive">Nonaktif</option>
                </select>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 transition"
                    >
                        Terapkan
                    </button>
                    <button
                        type="button"
                        @click="resetFilters"
                        class="rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabel Pengguna -->
        <div v-if="users.data.length > 0" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="px-5 py-3.5">Nama</th>
                            <th class="px-5 py-3.5">Email</th>
                            <th class="px-5 py-3.5">Peran</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Terdaftar</th>
                            <th class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in users.data" :key="item.id" class="hover:bg-slate-50/70">
                            <td class="px-5 py-3.5 font-medium text-slate-900">
                                {{ item.name }}
                                <span
                                    v-if="item.id === currentUserId"
                                    class="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600"
                                >
                                    Anda
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">{{ item.email }}</td>
                            <td class="px-5 py-3.5">
                                <StatusBadge :status="item.role" :label="item.role_label" />
                            </td>
                            <td class="px-5 py-3.5">
                                <StatusBadge :status="item.is_active ? 'ACTIVE' : 'INACTIVE'" />
                            </td>
                            <td class="px-5 py-3.5 text-slate-500">{{ item.created_at }}</td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(item)"
                                        class="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                    >
                                        Ubah
                                    </button>
                                    <button
                                        v-if="item.id !== currentUserId"
                                        type="button"
                                        @click="toggleTarget = item"
                                        class="rounded-md border px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            item.is_active
                                                ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                        "
                                    >
                                        {{ item.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                v-if="users.links && users.links.length > 3"
                class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-600"
            >
                <span>Menampilkan {{ users.from }}–{{ users.to }} dari {{ users.total }} pengguna</span>
                <div class="flex items-center gap-1">
                    <template v-for="(link, idx) in users.links" :key="idx">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            class="rounded-md border px-2.5 py-1 font-medium"
                            :class="
                                link.active
                                    ? 'border-blue-800 bg-blue-800 text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                            "
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1 text-slate-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            title="Tidak ada pengguna ditemukan"
            description="Tidak ada akun yang cocok dengan kriteria pencarian saat ini. Ubah filter atau tambahkan pengguna baru."
        >
            <template #action>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900"
                >
                    Tambah Pengguna Baru
                </button>
            </template>
        </EmptyState>

        <!-- Modal Tambah / Ubah Pengguna -->
        <div
            v-if="showUserModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-xl">
                <h2 class="text-base font-bold text-slate-900">
                    {{ editingUser ? 'Ubah Data Pengguna' : 'Tambah Pengguna Baru' }}
                </h2>
                <p class="mt-1 text-xs text-slate-500">
                    Guru tidak memiliki akun login pada versi MVP; akun hanya dibuat untuk Operator dan Admin.
                </p>

                <form @submit.prevent="submitUserForm" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                        <input
                            v-model="userForm.name"
                            type="text"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="userForm.errors.name" class="mt-1 text-xs text-rose-600">{{ userForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Alamat Email</label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="userForm.errors.email" class="mt-1 text-xs text-rose-600">{{ userForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Peran</label>
                        <select
                            v-model="userForm.role"
                            class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        >
                            <option v-for="r in roles" :key="r.value" :value="r.value">
                                {{ r.label }}
                            </option>
                        </select>
                        <p v-if="userForm.errors.role" class="mt-1 text-xs text-rose-600">{{ userForm.errors.role }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">
                                {{ editingUser ? 'Kata Sandi Baru (Opsional)' : 'Kata Sandi' }}
                            </label>
                            <input
                                v-model="userForm.password"
                                type="password"
                                :required="!editingUser"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p v-if="userForm.errors.password" class="mt-1 text-xs text-rose-600">{{ userForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Konfirmasi Kata Sandi</label>
                            <input
                                v-model="userForm.password_confirmation"
                                type="password"
                                :required="!editingUser || Boolean(userForm.password)"
                                class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input
                            id="user_is_active"
                            v-model="userForm.is_active"
                            type="checkbox"
                            :disabled="editingUser && editingUser.id === currentUserId"
                            class="h-4 w-4 rounded border-slate-300 text-blue-800 focus:ring-blue-700"
                        />
                        <label for="user_is_active" class="ml-2 text-sm text-slate-700">
                            Akun aktif dan diizinkan masuk ke sistem
                        </label>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            :disabled="userForm.processing"
                            @click="closeUserModal"
                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="userForm.processing"
                            class="rounded-lg bg-blue-800 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-900 disabled:opacity-50"
                        >
                            <span v-if="userForm.processing">Menyimpan...</span>
                            <span v-else>Simpan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dialog Konfirmasi Aktif / Nonaktifkan -->
        <ConfirmDialog
            :show="Boolean(toggleTarget)"
            :title="toggleTarget?.is_active ? 'Nonaktifkan Akun Pengguna' : 'Aktifkan Akun Pengguna'"
            :message="
                toggleTarget?.is_active
                    ? `Akun ${toggleTarget?.name} (${toggleTarget?.email}) tidak akan dapat masuk ke SIMSOAL sampai diaktifkan kembali.`
                    : `Akun ${toggleTarget?.name} (${toggleTarget?.email}) akan diaktifkan kembali dan dapat masuk ke sistem.`
            "
            :confirm-text="toggleTarget?.is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan'"
            :variant="toggleTarget?.is_active ? 'danger' : 'primary'"
            :processing="toggling"
            @close="toggleTarget = null"
            @confirm="confirmToggleStatus"
        />
    </AppLayout>
</template>