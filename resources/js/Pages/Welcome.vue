<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Mic, Trophy } from 'lucide-vue-next';
import AppLayout from '../Layouts/AppLayout.vue';
import RewardBadge from '../Components/RewardBadge.vue';

const page = usePage();
</script>

<template>
    <AppLayout>
        <section class="grid min-h-[calc(100vh-120px)] items-center gap-8 py-6 md:grid-cols-[1.1fr_0.9fr]">
            <div>
                <RewardBadge title="Grade 1 Reading Practice" />
                <h1 class="mt-5 max-w-2xl text-5xl font-black leading-tight text-text md:text-6xl">ReaDirect</h1>
                <p class="mt-4 max-w-xl text-xl font-bold leading-relaxed text-muted">
                    Friendly oral reading assessment and guided practice for young readers.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link href="/learner/access" class="inline-flex min-h-14 items-center justify-center rounded-2xl bg-primary px-6 text-lg font-black text-white shadow-lg shadow-primary/20 hover:bg-primary-dark">
                        Start reading
                    </Link>
                    <a v-if="!page.props.auth?.user" href="/login" class="inline-flex min-h-14 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-6 text-lg font-black text-primary hover:bg-primary-light">
                        Teacher login
                    </a>
                    <a v-else href="/teacher/dashboard" class="inline-flex min-h-14 items-center justify-center rounded-2xl border-2 border-primary bg-surface px-6 text-lg font-black text-primary hover:bg-primary-light">
                        Teacher dashboard
                    </a>
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
</template>
