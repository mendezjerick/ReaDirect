<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import TeacherLayout from '../../Layouts/TeacherLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
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
    profileForm.put('/teacher/profile', { preserveScroll: true });
};

const updatePassword = () => {
    passwordForm.put('/teacher/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
};
</script>

<template>
    <TeacherLayout>
        <PageHeader title="Account Settings" subtitle="Manage your personal information and security" />

        <!-- Profile card -->
        <DashboardCard class="mb-6">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <User :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Profile Information</h2>
            </div>

            <!-- Avatar preview -->
            <div class="mb-6 flex items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white text-2xl font-bold shadow-md shadow-orange-500/20">
                    {{ (profileForm.name ?? 'U').charAt(0).toUpperCase() }}
                </div>
                <div>
                    <p class="text-[15px] font-bold text-text">{{ profileForm.name }}</p>
                    <p class="text-[12px] text-muted">{{ profileForm.email }}</p>
                    <div class="mt-1 flex gap-1.5">
                        <span
                            v-for="role in roles"
                            :key="role"
                            class="inline-flex items-center rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold capitalize text-orange-500"
                        >{{ role.replace('_', ' ') }}</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="updateProfile" class="space-y-4">
                <div>
                    <label for="profile-name" class="block text-[12px] font-bold uppercase tracking-wider text-muted mb-1.5">Full Name</label>
                    <div class="relative">
                        <User :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="profile-name"
                            v-model="profileForm.name"
                            type="text"
                            class="w-full rounded-xl border border-border/60 bg-background py-2.5 pl-10 pr-4 text-[13px] text-text placeholder:text-muted focus:border-orange-500 focus:bg-surface focus:ring-3 focus:ring-orange-500/10 focus:outline-none transition-colors"
                            placeholder="Your full name"
                        />
                    </div>
                    <p v-if="profileForm.errors.name" class="mt-1 text-[12px] font-medium text-red-500">{{ profileForm.errors.name }}</p>
                </div>

                <div>
                    <label for="profile-email" class="block text-[12px] font-bold uppercase tracking-wider text-muted mb-1.5">Email Address</label>
                    <div class="relative">
                        <Mail :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="profile-email"
                            v-model="profileForm.email"
                            type="email"
                            class="w-full rounded-xl border border-border/60 bg-background py-2.5 pl-10 pr-4 text-[13px] text-text placeholder:text-muted focus:border-orange-500 focus:bg-surface focus:ring-3 focus:ring-orange-500/10 focus:outline-none transition-colors"
                            placeholder="your@email.com"
                        />
                    </div>
                    <p v-if="profileForm.errors.email" class="mt-1 text-[12px] font-medium text-red-500">{{ profileForm.errors.email }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-[13px] font-bold text-white transition-colors hover:bg-orange-600 disabled:opacity-50"
                    >
                        <Save :size="14" />
                        {{ profileForm.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </DashboardCard>

        <!-- Password card -->
        <DashboardCard>
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50 text-orange-500">
                    <Lock :size="15" />
                </div>
                <h2 class="text-sm font-bold text-text">Change Password</h2>
            </div>

            <form @submit.prevent="updatePassword" class="space-y-4">
                <div>
                    <label for="current-password" class="block text-[12px] font-bold uppercase tracking-wider text-muted mb-1.5">Current Password</label>
                    <div class="relative">
                        <Lock :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="current-password"
                            v-model="passwordForm.current_password"
                            type="password"
                            class="w-full rounded-xl border border-border/60 bg-background py-2.5 pl-10 pr-4 text-[13px] text-text placeholder:text-muted focus:border-orange-500 focus:bg-surface focus:ring-3 focus:ring-orange-500/10 focus:outline-none transition-colors"
                            placeholder="••••••••"
                        />
                    </div>
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-[12px] font-medium text-red-500">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label for="new-password" class="block text-[12px] font-bold uppercase tracking-wider text-muted mb-1.5">New Password</label>
                    <div class="relative">
                        <Lock :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="new-password"
                            v-model="passwordForm.password"
                            type="password"
                            class="w-full rounded-xl border border-border/60 bg-background py-2.5 pl-10 pr-4 text-[13px] text-text placeholder:text-muted focus:border-orange-500 focus:bg-surface focus:ring-3 focus:ring-orange-500/10 focus:outline-none transition-colors"
                            placeholder="••••••••"
                        />
                    </div>
                    <p v-if="passwordForm.errors.password" class="mt-1 text-[12px] font-medium text-red-500">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label for="confirm-password" class="block text-[12px] font-bold uppercase tracking-wider text-muted mb-1.5">Confirm New Password</label>
                    <div class="relative">
                        <Lock :size="15" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
                        <input
                            id="confirm-password"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="w-full rounded-xl border border-border/60 bg-background py-2.5 pl-10 pr-4 text-[13px] text-text placeholder:text-muted focus:border-orange-500 focus:bg-surface focus:ring-3 focus:ring-orange-500/10 focus:outline-none transition-colors"
                            placeholder="••••••••"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-[13px] font-bold text-white transition-colors hover:bg-orange-600 disabled:opacity-50"
                    >
                        <Lock :size="14" />
                        {{ passwordForm.processing ? 'Updating...' : 'Update Password' }}
                    </button>
                </div>
            </form>
        </DashboardCard>
    </TeacherLayout>
</template>
