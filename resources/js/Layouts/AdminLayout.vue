<template>
    <div class="min-h-screen bg-background text-text md:flex">
        <aside class="border-b border-border bg-surface p-4 md:sticky md:top-0 md:min-h-screen md:w-72 md:border-b-0 md:border-r">
            <a href="/admin/dashboard" class="text-xl font-black text-primary">ReaDirect Admin</a>
            <p class="mt-1 text-xs font-semibold text-muted">System management and QA</p>
            <div class="mt-4 grid gap-2">
                <a href="/" class="rounded-xl bg-primary-light px-3 py-2 text-sm font-black text-primary hover:bg-primary/10">Home</a>
                <a href="/teacher/dashboard" class="rounded-xl border border-border px-3 py-2 text-sm font-black text-muted hover:bg-primary-light hover:text-primary">Teacher dashboard</a>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="rounded-xl border border-border px-3 py-2 text-left text-sm font-black text-muted hover:bg-primary-light hover:text-primary"
                >
                    Logout
                </Link>
            </div>
            <nav class="mt-8 grid gap-1 text-sm font-semibold text-muted">
                <a v-for="item in nav" :key="item.href" class="rounded-xl px-3 py-2 hover:bg-primary-light hover:text-primary" :href="item.href">{{ item.label }}</a>
            </nav>
        </aside>
        <main class="flex-1 p-4 md:p-8">
            <div v-if="$page.props.flash?.success" class="mb-4 rounded-xl border border-primary/20 bg-primary-light px-4 py-3 text-sm font-bold text-primary">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                {{ $page.props.flash.error }}
            </div>
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const nav = [
    { label: 'Dashboard', href: '/admin/dashboard' },
    { label: 'Schools', href: '/admin/schools' },
    { label: 'Teachers', href: '/admin/teachers' },
    { label: 'Learners', href: '/admin/learners' },
    { label: 'Assessment Content', href: '/admin/assessment-content' },
    { label: 'Module Content', href: '/admin/module-content' },
    { label: 'Rules & Thresholds', href: '/admin/rules' },
    { label: 'Agents', href: '/admin/agents' },
    { label: 'Prompt Templates', href: '/admin/prompts' },
    { label: 'Audit Logs', href: '/admin/audit-logs' },
    { label: 'System Monitoring', href: '/admin/system-monitoring' },
    { label: 'Testing / QA Mode', href: '/admin/testing' },
];
</script>
