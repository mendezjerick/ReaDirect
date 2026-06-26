<script setup>
import { usePage } from '@inertiajs/vue3';
import { Leaf } from 'lucide-vue-next';

const page = usePage();
</script>

<template>
    <div v-if="page.props.adminTesting?.enabled" class="z-50 border-b border-[rgba(238,193,112,0.45)] bg-[rgba(255,253,247,0.94)] px-4 py-2 text-sm text-text">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
            <div class="inline-flex items-center gap-2 font-black text-primary">
                <Leaf class="size-5 fill-primary/20" />
                Testing Mode
            </div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
                <span>Learner ID: {{ page.props.adminTesting.learner_id ?? 'not selected' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox assessment: {{ page.props.adminTesting.assessment_attempt_id ?? '-' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox module: {{ page.props.adminTesting.module_attempt_id ?? '-' }}</span>
            </div>
            <div class="flex gap-2">
                <a class="rounded-xl border border-[rgba(54,83,101,0.16)] bg-surface px-4 py-1.5 font-black text-text shadow-sm" href="/admin/testing/flow-jump">Jump menu</a>
                <form method="post" action="/admin/testing/exit">
                    <input type="hidden" name="_token" :value="page.props.csrf_token">
                    <button class="rounded-xl bg-primary px-4 py-1.5 font-black text-white shadow-sm shadow-primary/20">Exit</button>
                </form>
            </div>
        </div>
    </div>
</template>
