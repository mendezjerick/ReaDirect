<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import DashboardCard from '../../Components/DashboardCard.vue';
import {
    User,
    Mail,
    Lock,
    Save,
    Shield,
} from 'lucide-vue-next';

const props = defineProps({ user: Object });
const page = usePage();
const roles = page.props.auth?.roles ?? [];

const profileForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updateProfile = () => {
    profileForm.put('/admin/profile', { preserveScroll: true });
};

const updatePassword = () => {
    passwordForm.put('/admin/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <AdminLayout>
        <!-- ── Page header ─────────────────────────────────── -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-extrabold text-text">Account Settings</h1>
                <p class="mt-1 text-sm font-medium text-muted">Manage your personal information and security</p>
            </div>
        </div>

        <!-- ── Profile card ────────────────────────────────── -->
        <DashboardCard class="mb-6 prof-card-in">
            <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                    <User class="size-4" />
                </div>
                <h2 class="text-[15px] font-bold text-text">Profile Information</h2>
            </div>

            <!-- Avatar preview -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex h-[72px] w-[72px] items-center justify-center rounded-2xl bg-gradient-to-br from-primary to-blue-600 text-white text-3xl font-extrabold shadow-lg shadow-primary/20 ring-4 ring-primary/5">
                    {{ (profileForm.name ?? 'U').charAt(0).toUpperCase() }}
                </div>
                <div>
                    <p class="text-base font-bold text-text">{{ profileForm.name }}</p>
                    <p class="text-[13px] font-medium text-muted mt-0.5">{{ profileForm.email }}</p>
                    <div class="mt-2 flex gap-1.5">
                        <span
                            v-for="role in roles"
                            :key="role"
                            class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-1 text-[11px] font-bold capitalize tracking-wide text-primary ring-1 ring-primary/20"
                        >
                            <Shield class="size-3" />
                            {{ role.replace('_', ' ') }}
                        </span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="updateProfile" class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="profile-name" class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Full Name</label>
                        <div class="relative">
                            <User class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                id="profile-name"
                                v-model="profileForm.name"
                                type="text"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40"
                                placeholder="Your full name"
                            />
                        </div>
                        <p v-if="profileForm.errors.name" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label for="profile-email" class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Email Address</label>
                        <div class="relative">
                            <Mail class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                id="profile-email"
                                v-model="profileForm.email"
                                type="email"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all duration-200 hover:border-primary/40"
                                placeholder="your@email.com"
                            />
                        </div>
                        <p v-if="profileForm.errors.email" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ profileForm.errors.email }}</p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-primary-dark hover:shadow-md hover:shadow-primary/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <Save class="size-4" />
                        {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </DashboardCard>

        <!-- ── Password card ───────────────────────────────── -->
        <DashboardCard class="prof-card-in" style="--card-delay: 100ms">
            <div class="mb-5 flex items-center gap-3 border-b border-border/60 pb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-500">
                    <Lock class="size-4" />
                </div>
                <h2 class="text-[15px] font-bold text-text">Change Password</h2>
            </div>

            <form @submit.prevent="updatePassword" class="space-y-5">
                <div class="max-w-xl space-y-5">
                    <div>
                        <label for="current-password" class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Current Password</label>
                        <div class="relative">
                            <Lock class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                id="current-password"
                                v-model="passwordForm.current_password"
                                type="password"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 transition-all duration-200 hover:border-emerald-500/40"
                                placeholder="••••••••"
                            />
                        </div>
                        <p v-if="passwordForm.errors.current_password" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div>
                        <label for="new-password" class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">New Password</label>
                        <div class="relative">
                            <Lock class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                id="new-password"
                                v-model="passwordForm.password"
                                type="password"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 transition-all duration-200 hover:border-emerald-500/40"
                                placeholder="••••••••"
                            />
                        </div>
                        <p v-if="passwordForm.errors.password" class="mt-1.5 text-[12px] font-medium text-rose-500">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label for="confirm-password" class="block text-[11px] font-bold uppercase tracking-wider text-muted mb-1.5">Confirm New Password</label>
                        <div class="relative">
                            <Lock class="size-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                            <input
                                id="confirm-password"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="w-full rounded-xl border border-border/60 bg-white py-2.5 pl-10 pr-4 text-[13px] font-medium text-text placeholder:text-muted focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 transition-all duration-200 hover:border-emerald-500/40"
                                placeholder="••••••••"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-white transition-all duration-200 hover:bg-emerald-600 hover:shadow-md hover:shadow-emerald-500/20 active:scale-[0.97] disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <Lock class="size-4" />
                        {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                    </button>
                </div>
            </form>
        </DashboardCard>
    </AdminLayout>
</template>

<style scoped>
.prof-card-in { animation: prof-entrance 400ms cubic-bezier(0.16, 1, 0.3, 1) both; animation-delay: var(--card-delay, 0ms); }
@keyframes prof-entrance { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>
