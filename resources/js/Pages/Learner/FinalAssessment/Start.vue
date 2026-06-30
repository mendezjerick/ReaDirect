<script setup>
import { useForm } from '@inertiajs/vue3';
import { BookOpenCheck, Heart, TrendingUp } from 'lucide-vue-next';
import GuideLayout from '../../../Components/Learner/GuideLayout.vue';

const form = useForm({});
const start = () => form.post('/final-assessment/start');
</script>

<template>
    <GuideLayout
        :progress="10"
        eyebrow="Final Assessment"
        divider-label="Ready?"
        agent-message="This is your final reading check. Do your best, one step at a time."
        agent-line-key="vivian.assessment.final_start"
        primary-label="Start final check"
        :primary-disabled="form.processing"
        @primary="start"
    >
        <template #title>
            Final <span class="guide-title-accent">Reading</span> Check
        </template>

        <div class="guide-traits">
            <div class="guide-trait guide-anim" style="--guide-delay: 200ms">
                <span class="guide-trait-icon"><BookOpenCheck class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Same reading tasks</span>
                    <span class="guide-trait-desc">You will do the same reading tasks as before.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 285ms">
                <span class="guide-trait-icon guide-trait-icon--green"><TrendingUp class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Show your growth</span>
                    <span class="guide-trait-desc">Your teacher will see how your reading improved.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 370ms">
                <span class="guide-trait-icon"><Heart class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Do your best</span>
                    <span class="guide-trait-desc">Take your time and follow Miss Vivian step by step.</span>
                </div>
            </div>
        </div>
    </GuideLayout>
</template>
