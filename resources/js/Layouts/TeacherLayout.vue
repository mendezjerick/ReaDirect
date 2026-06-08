<template>
    <div class="min-h-screen bg-background text-text">

        <!-- Mobile overlay -->
        <Transition name="overlay">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-20 bg-black/20 backdrop-blur-[2px] lg:hidden"
                @click="sidebarOpen = false"
            />
        </Transition>

        <!-- ── Sidebar ─────────────────────────────────────── -->
        <aside
            class="fixed inset-y-0 left-0 z-30 flex w-[260px] flex-col bg-surface transition-transform duration-300 ease-in-out lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full'"
        >
            <!-- Brand header -->
            <div class="flex h-[72px] shrink-0 items-center gap-3 px-6">
                <Link href="/teacher/dashboard" class="flex items-center gap-3 focus-visible:rounded-xl">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-md shadow-orange-500/30">
                        <GraduationCap :size="18" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[15px] font-extrabold leading-tight text-text">ReaDirect</p>
                    </div>
                </Link>
            </div>

            <!-- Scrollable body -->
            <div class="flex flex-1 flex-col overflow-y-auto px-4 pb-4">

                <!-- Teacher info -->
                <div class="mb-1 px-3 pt-2 pb-3 border-b border-border/60">
                    <p class="text-[13px] font-bold text-text">{{ page.props.auth?.user?.name ?? 'Teacher' }}</p>
                    <p class="text-[11px] text-muted font-medium">Teacher Account</p>
                </div>

                <!-- Main navigation -->
                <div class="mb-1">
                    <p class="mb-2 px-3 pt-4 text-[10px] font-bold uppercase tracking-[0.15em] text-muted">Navigation</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition-all duration-150"
                            :class="isActive(item.href)
                                ? 'bg-orange-500/8 text-orange-600 border-l-[3px] border-orange-500 -ml-[3px]'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-text'"
                            @click="sidebarOpen = false"
                        >
                            <component
                                :is="item.icon"
                                :size="18"
                                class="shrink-0 transition-colors"
                                :class="isActive(item.href) ? 'text-orange-500' : 'text-slate-400'"
                            />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Quick links -->
                <div>
                    <p class="mb-2 px-3 pt-4 text-[10px] font-bold uppercase tracking-[0.15em] text-muted">General</p>
                    <div class="space-y-0.5">
                        <Link
                            v-if="page.props.auth?.roles?.includes('system_admin') || page.props.auth?.roles?.includes('school_admin')"
                            href="/admin/dashboard"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold text-slate-500 hover:bg-slate-50 hover:text-text transition-all duration-150"
                            @click="sidebarOpen = false"
                        >
                            <Shield :size="18" class="shrink-0 text-slate-400" />
                            <span>Admin Dashboard</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-[13px] font-semibold text-slate-500 hover:bg-red-50 hover:text-danger transition-all duration-150"
                        >
                            <LogOut :size="18" class="shrink-0 text-slate-400" />
                            <span>Logout</span>
                        </Link>
                    </div>
                </div>

                <!-- Spacer -->
                <div class="flex-1" />

                <!-- Bottom info card -->
                <div class="mt-4 rounded-2xl bg-gradient-to-br from-orange-50 via-amber-100/50 to-orange-50 p-4">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-orange-500/10">
                        <BookOpen :size="18" class="text-orange-500" />
                    </div>
                    <p class="text-[13px] font-bold text-text">Teacher Portal</p>
                    <p class="mt-0.5 text-[11px] text-muted leading-relaxed">Track learner progress, review assessments, and manage your class.</p>
                </div>

                <!-- Version footer -->
                <div class="mt-3 px-1 pb-1">
                    <p class="text-[10px] font-medium text-muted/60">ReaDirect &copy; {{ new Date().getFullYear() }}</p>
                </div>
            </div>
        </aside>

        <!-- ── Main content area ───────────────────────────── -->
        <div class="flex min-h-screen flex-col lg:pl-[260px]">

            <!-- Top bar -->
            <header class="sticky top-0 z-10 flex h-[64px] shrink-0 items-center justify-between bg-surface/80 backdrop-blur-md px-5 lg:px-8 border-b border-border/60">
                <!-- Left: hamburger (mobile) + search -->
                <div class="flex items-center gap-3">
                    <button
                        @click="sidebarOpen = !sidebarOpen"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-text lg:hidden"
                        :aria-label="sidebarOpen ? 'Close menu' : 'Open menu'"
                    >
                        <X v-if="sidebarOpen" :size="20" />
                        <Menu v-else :size="20" />
                    </button>
                    <div class="hidden lg:flex items-center gap-2 rounded-xl bg-background px-4 py-2">
                        <Search :size="15" class="text-muted" />
                        <span class="text-[13px] text-muted font-medium">Search...</span>
                    </div>
                </div>

                <!-- Right: user avatar dropdown -->
                <div class="flex items-center gap-2">
                    <!-- Avatar + dropdown -->
                    <div class="relative ml-1">
                        <button
                            @click="profileOpen = !profileOpen"
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-amber-600 text-white text-xs font-bold shadow-sm ring-2 ring-transparent transition-all hover:ring-orange-200 focus:ring-orange-200"
                        >
                            {{ (page.props.auth?.user?.name ?? 'T').charAt(0).toUpperCase() }}
                        </button>

                        <!-- Dropdown -->
                        <Transition name="flash">
                            <div
                                v-if="profileOpen"
                                class="absolute right-0 top-12 w-64 rounded-2xl border border-border/60 bg-surface p-3 shadow-xl shadow-black/10"
                            >
                                <!-- Backdrop click-away -->
                                <div class="fixed inset-0 z-[-1]" @click="profileOpen = false" />

                                <!-- User info -->
                                <div class="mb-3 flex items-center gap-3 rounded-xl bg-background p-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-amber-600 text-white text-sm font-bold">
                                        {{ (page.props.auth?.user?.name ?? 'T').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-bold text-text">{{ page.props.auth?.user?.name }}</p>
                                        <p class="truncate text-[11px] text-muted">{{ page.props.auth?.user?.email }}</p>
                                    </div>
                                </div>

                                <!-- Links -->
                                <div class="space-y-0.5">
                                    <Link
                                        href="/teacher/profile"
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-semibold text-slate-500 hover:bg-orange-50 hover:text-orange-600 transition-colors"
                                        @click="profileOpen = false"
                                    >
                                        <UserCircle :size="16" class="shrink-0" />
                                        Account Settings
                                    </Link>
                                    <Link
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-[13px] font-semibold text-slate-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                                    >
                                        <LogOut :size="16" class="shrink-0" />
                                        Sign Out
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            <div class="px-5 lg:px-8">
                <Transition name="flash">
                    <div
                        v-if="$page.props.flash?.success"
                        class="mt-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"
                    >
                        <CheckCircle :size="16" class="shrink-0" />
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </Transition>
                <Transition name="flash">
                    <div
                        v-if="$page.props.flash?.error"
                        class="mt-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
                    >
                        <AlertCircle :size="16" class="shrink-0" />
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </Transition>
            </div>

            <!-- Page content -->
            <main class="flex-1 px-5 py-6 lg:px-8 lg:py-7">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    GraduationCap,
    BookOpen,
    Shield,
    LogOut,
    Menu,
    X,
    Search,
    UserCircle,
    LayoutDashboard,
    Users,
    FileBarChart,
    BarChart3,
    CheckCircle,
    AlertCircle,
} from 'lucide-vue-next';

const sidebarOpen = ref(false);
const profileOpen = ref(false);
const page = usePage();

const isActive = (href) => {
    const url = page.url;
    if (href === '/teacher/dashboard') {
        return url === '/teacher/dashboard' || url.startsWith('/teacher/dashboard?');
    }
    return url.startsWith(href);
};

const navItems = [
    { label: 'Dashboard',  href: '/teacher/dashboard',  icon: LayoutDashboard },
    { label: 'Learners',   href: '/teacher/learners',   icon: Users },
    { label: 'Reports',    href: '/teacher/reports',     icon: FileBarChart },
    { label: 'Analytics',  href: '/teacher/analytics',   icon: BarChart3 },
];
</script>
