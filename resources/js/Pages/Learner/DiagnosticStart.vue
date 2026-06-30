<script setup>
import { useForm } from '@inertiajs/vue3';
import { Clock, Compass, Heart } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';
import SecondaryButton from '../../Components/SecondaryButton.vue';

const props = defineProps({
    developerRetest: Object,
});

const form = useForm({});
const retestForm = useForm({});
const start = () => form.post('/learner/diagnostic/start');
const startDeveloperRetest = () => retestForm.post('/learner/diagnostic/developer-retest');
</script>

<template>
    <GuideLayout
        :progress="25"
        diagnostic-step="intro"
        eyebrow="Reading Assessment"
        divider-label="What to expect"
        agent-message="Hi, I'm Miss Vivian. I'll guide you through this activity, so listen carefully and take your time."
        agent-line-key="vivian.intro.assessment"
        primary-label="Begin reading check"
        :primary-disabled="form.processing"
        @primary="start"
    >
        <template #title>
            Your <span class="guide-title-accent">Reading</span> Check
        </template>

        <div class="guide-traits">
            <div class="guide-trait guide-anim" style="--guide-delay: 200ms">
                <span class="guide-trait-icon"><Clock class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">About 5 minutes</span>
                    <span class="guide-trait-desc">A short check - won't take long.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 285ms">
                <span class="guide-trait-icon"><Compass class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Guided step by step</span>
                    <span class="guide-trait-desc">Miss Vivian walks you through each part.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 370ms">
                <span class="guide-trait-icon"><Heart class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">No pressure at all</span>
                    <span class="guide-trait-desc">Just try your best - that's all we need.</span>
                </div>
            </div>
        </div>

        <div
            v-if="props.developerRetest?.enabled"
            class="guide-dev-panel guide-anim"
            style="--guide-delay: 420ms"
        >
            <p class="text-[14px] font-black text-amber-700">
                Developer testing only. This starts a new sandbox diagnostic attempt for QA testing and preserves previous attempts.
            </p>
            <SecondaryButton class="mt-3" :disabled="retestForm.processing" @click="startDeveloperRetest">
                Start New Developer Test Attempt
            </SecondaryButton>
        </div>
    </GuideLayout>
</template>
