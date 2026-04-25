<script setup>
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div v-if="page.props.adminTesting?.enabled" class="sticky top-0 z-50 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
            <div class="font-black">Testing Mode</div>
            <div class="text-xs font-semibold">
                Learner ID: {{ page.props.adminTesting.learner_id ?? 'not selected' }}
                <span class="mx-2">|</span>
                Sandbox assessment: {{ page.props.adminTesting.assessment_attempt_id ?? '-' }}
                <span class="mx-2">|</span>
                Sandbox module: {{ page.props.adminTesting.module_attempt_id ?? '-' }}
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
