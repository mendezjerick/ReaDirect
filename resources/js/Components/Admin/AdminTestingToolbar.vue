<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div v-if="page.props.adminTesting?.enabled" class="z-50 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
            <div class="font-black">Testing Mode</div>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
                <span>Learner ID: {{ page.props.adminTesting.learner_id ?? 'not selected' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox assessment: {{ page.props.adminTesting.assessment_attempt_id ?? '-' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox module: {{ page.props.adminTesting.module_attempt_id ?? '-' }}</span>
            </div>
            <div class="flex gap-2">
                <a class="rounded-lg bg-white px-3 py-1 font-bold text-amber-900" href="/admin/testing/flow-jump">Jump menu</a>
                <form method="post" action="/admin/testing/exit">
                    <input type="hidden" name="_token" :value="page.props.csrf_token">
                    <button class="rounded-lg bg-amber-700 px-3 py-1 font-bold text-white">Exit</button>
                </form>
            </div>
        </div>
    </div>
</template>
