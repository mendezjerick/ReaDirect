<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import LessonCard from '../../Components/LessonCard.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

const props = defineProps({ attempt: Object, decision: Object, module: Object });

const moduleTitle = computed(() => props.module?.title ?? 'Reading at Grade Level');

const evaluatorMessage = computed(() => {
    if (props.module) {
        return `Great job! Your reading path is ${moduleTitle.value}. Tap continue to see it on your dashboard.`;
    }
    return "Wonderful! You're reading at grade level. Tap continue to head to your dashboard.";
});
</script>

<template>
    <LearnerLayout :progress="100">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="celebrating"
                :message="evaluatorMessage"
            />
        </template>

        <section class="mx-auto grid max-w-4xl gap-6 text-center">
            <RewardBadge title="Path Ready" />
            <h1 class="text-4xl font-black text-text">Your reading path is ready.</h1>
            <LessonCard :title="moduleTitle" :description="decision.decision_reason" active />

            <div class="grid gap-4 md:grid-cols-4">
                <ScoreCard label="Task 1 letters" :value="attempt?.task_1_score ?? '-'" />
                <ScoreCard label="Task 2A rhymes" :value="attempt?.task_2a_score ?? '-'" />
                <ScoreCard label="Task 2B words" :value="attempt?.task_2b_score ?? '-'" />
                <ScoreCard label="CRLA total" :value="attempt?.crla_total_score ?? '-'" />
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <ScoreCard label="Passage accuracy" :value="attempt?.reading_accuracy ?? '-'" suffix="%" />
                <ScoreCard label="Comprehension" :value="attempt?.comprehension_percentage ?? '-'" suffix="%" />
                <ScoreCard label="Reading score" :value="attempt?.final_reading_score ?? '-'" suffix="%" />
            </div>

            <div class="grid gap-4 text-left md:grid-cols-2">
                <div class="rounded-2xl border border-border bg-surface px-5 py-4 shadow-sm">
                    <p class="text-sm font-black uppercase tracking-wide text-muted">CRLA level</p>
                    <p class="mt-2 text-2xl font-black text-text">{{ attempt?.crla_classification }}</p>
                    <p class="mt-2 text-base font-bold text-muted">{{ decision.crla_meaning }}</p>
                </div>
                <div class="rounded-2xl border border-border bg-surface px-5 py-4 shadow-sm">
                    <p class="text-sm font-black uppercase tracking-wide text-muted">Reading level</p>
                    <p class="mt-2 text-2xl font-black text-text">{{ attempt?.reading_classification }}</p>
                    <p class="mt-2 text-base font-bold text-muted">{{ decision.reading_meaning }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-primary/30 bg-primary-light px-5 py-4 text-left">
                <p class="text-sm font-black uppercase tracking-wide text-primary">Why this path</p>
                <p class="mt-2 text-base font-bold text-text">{{ decision.placement_explanation }}</p>
                <p class="mt-3 text-sm font-bold text-muted">Rule applied: {{ decision.rule_applied }}</p>
            </div>
        </section>

        <BottomActionBar>
            <Link href="/learner/dashboard">
                <PrimaryButton>Continue to my Dashboard</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
