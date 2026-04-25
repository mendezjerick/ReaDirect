<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const navItems = [
    { label: 'Dashboard', href: '/teacher/dashboard' },
    { label: 'Learners', href: '/teacher/learners' },
    { label: 'Reports', href: '/teacher/reports' },
    { label: 'Analytics', href: '/teacher/analytics' },
];
</script>

<template>
    <div class="min-h-screen bg-background text-text md:flex">
        <aside class="border-b border-border bg-surface p-4 md:min-h-screen md:w-64 md:border-b-0 md:border-r">
            <Link href="/teacher/dashboard" class="text-xl font-black text-primary">ReaDirect</Link>
            <p class="mt-1 text-sm font-semibold text-muted">{{ page.props.auth?.user?.name ?? 'Teacher' }}</p>
            <div class="mt-4 grid gap-2">
                <Link
                    v-if="page.props.auth?.roles?.includes('system_admin') || page.props.auth?.roles?.includes('school_admin')"
                    href="/admin/dashboard"
                    class="rounded-xl bg-primary-light px-3 py-2 text-sm font-black text-primary hover:bg-primary/10"
                >
                    Admin dashboard
                </Link>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="rounded-xl border border-border px-3 py-2 text-left text-sm font-black text-muted hover:bg-primary-light hover:text-primary"
                >
                    Logout
                </Link>
            </div>
            <nav class="mt-8 grid gap-2 text-sm font-semibold text-muted">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary"
                    :class="{ 'bg-primary-light text-primary': page.url.startsWith(item.href) }"
                    :href="item.href"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </aside>
        <main class="flex-1 p-4 md:p-8">
            <slot />
        </main>
    </div>
</template>
