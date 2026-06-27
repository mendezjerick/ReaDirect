<script setup>
import { usePage } from '@inertiajs/vue3';
import { BookOpen, LogOut, Map } from 'lucide-vue-next';

const page = usePage();
</script>

<template>
    <div v-if="page.props.adminTesting?.enabled" class="rd-admin-testing-shell relative z-50 flex-none px-4 text-xs text-text">
        <div class="rd-admin-testing-bar learner-frame flex min-h-8 items-center justify-between gap-3 px-3 py-1">
            <div class="inline-flex shrink-0 items-center gap-2 font-black text-primary">
                <BookOpen class="size-4" />
                Testing Mode
            </div>
            <div class="flex min-w-0 flex-wrap items-center justify-center gap-x-3 gap-y-0.5 font-semibold">
                <span>Learner ID: {{ page.props.adminTesting.learner_id ?? 'not selected' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox assessment: {{ page.props.adminTesting.assessment_attempt_id ?? '-' }}</span>
                <span class="hidden sm:inline">|</span>
                <span>Sandbox module: {{ page.props.adminTesting.module_attempt_id ?? '-' }}</span>
            </div>
            <div class="flex shrink-0 gap-1.5">
                <a class="rd-admin-testing-button" href="/admin/testing/flow-jump">
                    <Map class="size-3.5" />
                    Jump menu
                </a>
                <form method="post" action="/admin/testing/exit">
                    <input type="hidden" name="_token" :value="page.props.csrf_token">
                    <button class="rd-admin-testing-button rd-admin-testing-button--exit">
                        <LogOut class="size-3.5" />
                        Exit
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rd-admin-testing-shell {
    background: rgba(255, 253, 248, 0.9);
    border-bottom: 1px solid rgba(224, 207, 166, 0.55);
}

.rd-admin-testing-bar {
    color: var(--rd-text-main);
}

.rd-admin-testing-button {
    display: inline-flex;
    min-height: 1.55rem;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border: 1px solid rgba(224, 207, 166, 0.75);
    border-radius: 999px;
    background: rgba(255, 253, 248, 0.88);
    padding: 0.18rem 0.7rem;
    color: var(--rd-text-main);
    font-weight: 900;
    box-shadow: none;
}

.rd-admin-testing-button--exit {
    border-color: #D9652F;
    background: #F58549;
    color: white;
}
</style>
