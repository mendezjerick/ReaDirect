<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import AgentSpeakerPanel from '../../Components/Learner/AgentSpeakerPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({
    attempt: Object,
});
</script>

<template>
    <LearnerLayout :progress="48" diagnostic-step="task-2a">
        <template #agent>
            <AgentSpeakerPanel
                agent-type="evaluator"
                state="encouraging"
                message="Task 2A is saved. Check your score, then continue to the word-in-sentence task."
            />
        </template>

        <section class="mx-auto grid max-w-3xl gap-6 text-center">
            <RewardBadge title="Task 2A Complete" />
            <h1 class="text-4xl font-black text-text">Rhyming word score</h1>
            <p class="mx-auto max-w-2xl text-base font-bold text-muted">
                This score is saved on your diagnostic record. Next, Task 2B checks how you read the target word inside a sentence.
            </p>

            <div class="grid gap-4 md:grid-cols-2">
                <ScoreCard label="Task 1 letters" :value="attempt.task_1_score" />
                <ScoreCard label="Task 2A rhymes" :value="attempt.task_2a_score" />
            </div>

            <div class="rounded-2xl border border-border bg-surface px-5 py-4 text-left shadow-sm">
                <p class="text-sm font-black uppercase tracking-wide text-muted">What happens next</p>
                <p class="mt-2 text-base font-bold text-text">
                    Task 2B will add the sentence-word score, then the CRLA summary will show Task 1, Task 2A, Task 2B, and your CRLA level together.
                </p>
            </div>
        </section>

        <BottomActionBar>
            <Link href="/learner/diagnostic/task-2b">
                <PrimaryButton>Continue to Task 2B</PrimaryButton>
            </Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
