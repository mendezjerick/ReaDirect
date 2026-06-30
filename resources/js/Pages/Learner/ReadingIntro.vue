<script setup>
import { ArrowRight, BookOpen, HelpCircle, Mic } from 'lucide-vue-next';
import GuideLayout from '../../Components/Learner/GuideLayout.vue';
</script>

<template>
    <GuideLayout
        :progress="70"
        diagnostic-step="sentence-reading"
        eyebrow="Reading Passage"
        divider-label="Reading assignment"
        agent-message="Choose one story for your final reading passage."
        agent-line-key="vivian.assessment.story_choice"
        primary-label="Choose a story"
        primary-href="/learner/diagnostic/story-selection"
    >
        <template #primary-icon>
            <ArrowRight class="size-5" />
        </template>

        <template #title>
            Your reading <span class="guide-title-accent">turn.</span>
        </template>

        <div class="guide-traits">
            <div class="guide-trait guide-anim" style="--guide-delay: 200ms">
                <span class="guide-trait-icon"><BookOpen class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Pick a story</span>
                    <span class="guide-trait-desc">Choose the one you want to read.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 285ms">
                <span class="guide-trait-icon guide-trait-icon--teal"><Mic class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Read it aloud</span>
                    <span class="guide-trait-desc">Speak clearly into the microphone.</span>
                </div>
            </div>
            <div class="guide-trait guide-anim" style="--guide-delay: 370ms">
                <span class="guide-trait-icon guide-trait-icon--violet"><HelpCircle class="size-5" /></span>
                <div class="guide-trait-body">
                    <span class="guide-trait-label">Answer 5 questions</span>
                    <span class="guide-trait-desc">Tell me what you understood.</span>
                </div>
            </div>
        </div>
    </GuideLayout>
</template>
