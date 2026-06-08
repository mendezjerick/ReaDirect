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
                <a href="/admin/dashboard" class="flex items-center gap-3 focus-visible:rounded-xl">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary text-white shadow-md shadow-primary/30">
                        <BookOpen :size="18" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[15px] font-extrabold leading-tight text-text">ReaDirect</p>
                    </div>
                </a>
            </div>

            <!-- Scrollable body -->
            <div class="flex flex-1 flex-col overflow-y-auto px-4 pb-4">

                <!-- Main menu section -->
                <div class="mb-1">
                    <p class="mb-2 px-3 pt-3 text-[10px] font-bold uppercase tracking-[0.15em] text-muted">Main Menu</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in mainNav"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition-all duration-150"
                            :class="isActive(item.href)
                                ? 'bg-primary/8 text-primary border-l-[3px] border-primary -ml-[3px]'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-text'"
                            @click="sidebarOpen = false"
                        >
                            <component
                                :is="item.icon"
                                :size="18"
                                class="shrink-0 transition-colors"
                                :class="isActive(item.href) ? 'text-primary' : 'text-slate-400'"
                            />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Admin tools section -->
                <div class="mb-1">
                    <p class="mb-2 px-3 pt-4 text-[10px] font-bold uppercase tracking-[0.15em] text-muted">Admin Tools</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in adminNav"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition-all duration-150"
                            :class="isActive(item.href)
                                ? 'bg-primary/8 text-primary border-l-[3px] border-primary -ml-[3px]'
                                : 'text-slate-500 hover:bg-slate-50 hover:text-text'"
                            @click="sidebarOpen = false"
                        >
                            <component
                                :is="item.icon"
                                :size="18"
                                class="shrink-0 transition-colors"
                                :class="isActive(item.href) ? 'text-primary' : 'text-slate-400'"
                            />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- General section -->
                <div>
                    <p class="mb-2 px-3 pt-4 text-[10px] font-bold uppercase tracking-[0.15em] text-muted">General</p>
                    <div class="space-y-0.5">
                        <Link
                            href="/"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold text-slate-500 hover:bg-slate-50 hover:text-text transition-all duration-150"
                        >
                            <Home :size="18" class="shrink-0 text-slate-400" />
                            <span>Home</span>
                        </Link>
                        <Link
                            href="/teacher/dashboard"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold text-slate-500 hover:bg-slate-50 hover:text-text transition-all duration-150"
                        >
                            <Monitor :size="18" class="shrink-0 text-slate-400" />
                            <span>Teacher Dashboard</span>
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

                <!-- Bottom CTA card -->
                <div class="mt-4 rounded-2xl bg-gradient-to-br from-blue-50 via-blue-100/50 to-indigo-50 p-4">
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                        <BookOpen :size="18" class="text-primary" />
                    </div>
                    <p class="text-[13px] font-bold text-text">ReaDirect Admin</p>
                    <p class="mt-0.5 text-[11px] text-muted leading-relaxed">Manage schools, learners, and assessments all in one place.</p>
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
                        aria-controls="admin-sidebar"
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
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-primary to-blue-600 text-white text-xs font-bold shadow-sm ring-2 ring-transparent transition-all hover:ring-blue-200 focus:ring-blue-200"
                        >
                            {{ (page.props.auth?.user?.name ?? 'A').charAt(0).toUpperCase() }}
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
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary to-blue-600 text-white text-sm font-bold">
                                        {{ (page.props.auth?.user?.name ?? 'A').charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-[13px] font-bold text-text">{{ page.props.auth?.user?.name }}</p>
                                        <p class="truncate text-[11px] text-muted">{{ page.props.auth?.user?.email }}</p>
                                    </div>
                                </div>

                                <!-- Links -->
                                <div class="space-y-0.5">
                                    <Link
                                        href="/admin/profile"
                                        class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-[13px] font-semibold text-slate-500 hover:bg-primary-light hover:text-primary transition-colors"
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

            <!-- Page content -->
            <main class="flex-1 px-5 py-6 lg:px-8 lg:py-7">

                <!-- Flash: success -->
                <Transition name="flash">
                    <div
                        v-if="$page.props.flash?.success"
                        class="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700"
                    >
                        <CheckCircle :size="16" class="shrink-0" />
                        <span>{{ $page.props.flash.success }}</span>
                    </div>
                </Transition>

                <!-- Flash: error -->
                <Transition name="flash">
                    <div
                        v-if="$page.props.flash?.error"
                        class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
                    >
                        <AlertCircle :size="16" class="shrink-0" />
                        <span>{{ $page.props.flash.error }}</span>
                    </div>
                </Transition>

                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Home,
    Monitor,
    LogOut,
    Menu,
    X,
    Search,
    UserCircle,
    LayoutDashboard,
    School,
    Users,
    GraduationCap,
    FileText,
    Book,
    Sliders,
    Bot,
    MessageSquare,
    ClipboardList,
    Activity,
    Settings,
    FileSearch,
    FlaskConical,
    CheckCircle,
    AlertCircle,
} from 'lucide-vue-next';

const sidebarOpen = ref(false);
const profileOpen = ref(false);
const page = usePage();
const currentUrl = computed(() => page.url);

const isActive = (href) => {
    const url = currentUrl.value;
    if (href === '/admin/dashboard') {
        return url === '/admin/dashboard' || url.startsWith('/admin/dashboard?');
    }
    if (href === '/admin/testing') {
        return url === '/admin/testing'
            || url.startsWith('/admin/testing?')
            || (url.startsWith('/admin/testing/')
                && !url.startsWith('/admin/testing/true-sandbox')
                && !url.startsWith('/admin/testing/module-mastery-simulator'));
    }
    return url.startsWith(href);
};

/* Split nav into sectioned groups like Sociafy reference */
const mainNav = [
    { label: 'Dashboard',            href: '/admin/dashboard',          icon: LayoutDashboard },
    { label: 'Schools',              href: '/admin/schools',             icon: School },
    { label: 'Teachers',             href: '/admin/teachers',            icon: Users },
    { label: 'Learners',             href: '/admin/learners',            icon: GraduationCap },
    { label: 'Assessment Content',   href: '/admin/assessment-content',  icon: FileText },
    { label: 'Module Content',       href: '/admin/module-content',      icon: Book },
];

const adminNav = [
    { label: 'Rules & Thresholds',   href: '/admin/rules',               icon: Sliders },
    { label: 'Agents',               href: '/admin/agents',              icon: Bot },
    { label: 'Prompt Templates',     href: '/admin/prompts',             icon: MessageSquare },
    { label: 'Audit Logs',           href: '/admin/audit-logs',          icon: ClipboardList },
    { label: 'System Monitoring',    href: '/admin/system-monitoring',   icon: Activity },
    { label: 'Testing / QA Mode',    href: '/admin/testing',             icon: Settings },
    { label: 'True Sandbox',         href: '/admin/testing/true-sandbox', icon: FileSearch },
    { label: 'Module Mastery Simulator', href: '/admin/testing/module-mastery-simulator', icon: FlaskConical },
];
</script>
