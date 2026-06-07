<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BookOpen, Mic, Trophy } from 'lucide-vue-next';
import AppLayout from '../Layouts/AppLayout.vue';
import ReaDirectBookLoader from '../Components/Loading/ReaDirectBookLoader.vue';
import RewardBadge from '../Components/RewardBadge.vue';
import { preloadAgentMedia } from '../agents/agentMediaPreloader';

const page = usePage();
const roles = computed(() => page.props.auth?.roles ?? []);
const isLoggedIn = computed(() => Boolean(page.props.auth?.user));
const isPreparing = ref(false);
const dashboardPreloadsAgents = computed(() => (
    roles.value.includes('system_admin')
    || roles.value.includes('school_admin')
    || roles.value.includes('teacher')
));
const dashboardLink = computed(() => {
    if (roles.value.includes('system_admin') || roles.value.includes('school_admin')) {
        return { href: '/admin/dashboard', label: 'Admin dashboard' };
    }

    if (roles.value.includes('teacher')) {
        return { href: '/teacher/dashboard', label: 'Teacher dashboard' };
    }

    return { href: '/learner/dashboard', label: 'Learner dashboard' };
});

const wait = (milliseconds) => new Promise((resolve) => {
    window.setTimeout(resolve, milliseconds);
});

const prepareAndNavigate = async (href) => {
    if (isPreparing.value) {
        return;
    }

    isPreparing.value = true;

    await Promise.allSettled([
        preloadAgentMedia(),
        wait(2_000),
    ]);

    router.visit(href, {
        onFinish: () => {
            isPreparing.value = false;
        },
    });
};
</script>

<template>
    <AppLayout :aria-busy="isPreparing">
        <template #nav>
            <div class="flex items-center gap-2">
                <Link
                    v-if="!isLoggedIn"
                    href="/login"
                    class="rounded-xl border border-primary px-4 py-2 text-sm font-black text-primary hover:bg-primary-light"
                >
                    Login
                </Link>
                <template v-else>
                    <a
                        v-if="dashboardPreloadsAgents"
                        :href="dashboardLink.href"
                        class="rounded-xl border border-primary px-4 py-2 text-sm font-black text-primary hover:bg-primary-light"
                        @click.prevent="prepareAndNavigate(dashboardLink.href)"
                    >
                        {{ dashboardLink.label }}
                    </a>
                    <Link
                        v-else
                        :href="dashboardLink.href"
                        class="rounded-xl border border-primary px-4 py-2 text-sm font-black text-primary hover:bg-primary-light"
                    >
                        {{ dashboardLink.label }}
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded-xl bg-primary px-4 py-2 text-sm font-black text-white hover:bg-primary-dark"
                    >
                        Logout
                    </Link>
                </template>
            </div>
        </template>

        <section class="grid min-h-[calc(100vh-120px)] items-center gap-8 py-6 md:grid-cols-[1.1fr_0.9fr]">
            <div>
                <RewardBadge title="Grade 1 Reading Practice" />
                <h1 class="mt-5 max-w-2xl text-5xl font-black leading-tight text-text md:text-6xl">ReaDirect</h1>
                <p class="mt-4 max-w-xl text-xl font-bold leading-relaxed text-muted">
                    Friendly oral reading assessment and guided practice for young readers.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="/learner/access"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-primary px-6 text-lg font-black text-white shadow-lg shadow-primary/20 hover:bg-primary-dark"
                        @click.prevent="prepareAndNavigate('/learner/access')"
                    >
                        Start reading
                    </a>
                    <Link v-if="!isLoggedIn" href="/login" class="inline-flex min-h-14 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-6 text-lg font-black text-primary hover:bg-primary-light">
                        Login
                    </Link>
                    <a
                        v-else-if="dashboardPreloadsAgents"
                        :href="dashboardLink.href"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-6 text-lg font-black text-primary hover:bg-primary-light"
                        @click.prevent="prepareAndNavigate(dashboardLink.href)"
                    >
                        {{ dashboardLink.label }}
                    </a>
                    <Link
                        v-else
                        :href="dashboardLink.href"
                        class="inline-flex min-h-14 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-6 text-lg font-black text-primary hover:bg-primary-light"
                    >
                        {{ dashboardLink.label }}
                    </Link>
                </div>
            </div>
            <div class="rounded-[36px] border border-border bg-surface p-6 shadow-2xl shadow-primary/10">
                <div class="grid gap-4">
                    <div class="flex items-center gap-4 rounded-[24px] bg-primary-light p-5">
                        <BookOpen class="size-10 text-primary" />
                        <p class="text-2xl font-black">Read a short task</p>
                    </div>
                    <div class="flex items-center gap-4 rounded-[24px] bg-surface p-5 shadow-md shadow-primary/10">
                        <Mic class="size-10 text-primary" />
                        <p class="text-2xl font-black">Record your voice</p>
                    </div>
                    <div class="flex items-center gap-4 rounded-[24px] bg-accent p-5">
                        <Trophy class="size-10 text-text" />
                        <p class="text-2xl font-black">See your next step</p>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
    <ReaDirectBookLoader v-if="isPreparing" message="Loading learning agents..." />
</template>
