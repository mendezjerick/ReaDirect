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
    taskTwoBReview: Object,
});

const accuracyTone = (percentage) => {
    if (percentage >= 90) return 'text-success';
    if (percentage >= 75) return 'text-primary';
    if (percentage >= 60) return 'text-warning';
    return 'text-danger';
};
</script>

<template>
    <LearnerLayout :progress="65">
        <template #agent>
            <AgentSpeakerPanel agent-type="evaluator" state="celebrating" message="The first reading check is complete. Now we will read a short passage." />
        </template>
        <div class="mx-auto grid max-w-3xl gap-6 text-center">
            <RewardBadge title="CRLA Complete" />
            <h1 class="text-4xl font-black text-text">Great effort. The first reading check is done.</h1>
            <div class="grid gap-4 md:grid-cols-4">
                <ScoreCard label="Letters" :value="attempt.task_1_score" />
                <ScoreCard label="Rhymes" :value="attempt.task_2a_score" />
                <ScoreCard label="Words" :value="attempt.task_2b_score" />
                <ScoreCard label="CRLA level" :value="attempt.crla_classification" />
            </div>
            <div v-if="taskTwoBReview" class="rounded-3xl border border-border bg-surface px-6 py-5 text-left shadow-lg shadow-primary/10">
                <p class="text-sm font-black uppercase tracking-wide text-muted">Sentence reading check</p>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-black text-muted">Average accuracy</p>
                        <p class="text-3xl font-black text-primary">{{ taskTwoBReview.average_accuracy_percentage }}%</p>
                    </div>
                    <div>
                        <p class="text-sm font-black text-muted">What we noticed</p>
                        <p class="text-xl font-black text-text capitalize">{{ taskTwoBReview.feedback_label }}</p>
                    </div>
                </div>
                <div v-if="taskTwoBReview.items?.length" class="mt-5 grid gap-3">
                    <div
                        v-for="item in taskTwoBReview.items"
                        :key="item.item_number"
                        class="rounded-2xl border border-border bg-background px-4 py-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-black uppercase tracking-wide text-muted">Sentence {{ item.item_number }}</p>
                                <p class="mt-1 text-base font-black text-text">{{ item.prompt }}</p>
                                <p class="mt-2 text-sm font-black text-muted capitalize">{{ item.feedback_label }}</p>
                                <p class="mt-1 text-xs font-bold text-muted">
                                    {{ item.matched_words }}/{{ item.total_words }} words matched
                                    <span v-if="item.missing_words > 0">, {{ item.missing_words }} missing</span>
                                </p>
                                <p v-if="item.phoneme_similarity_percentage !== null" class="mt-1 text-xs font-bold text-muted">
                                    Pronunciation match {{ item.phoneme_similarity_percentage }}%
                                </p>
                                <p v-if="item.target_word_phoneme_similarity_percentage !== null" class="mt-1 text-xs font-bold text-muted">
                                    Target word <span class="capitalize">{{ item.target_word }}</span> pronunciation {{ item.target_word_phoneme_similarity_percentage }}%
                                    <span v-if="item.actual_target_word && item.actual_target_word !== item.target_word">
                                        , heard as {{ item.actual_target_word }}
                                    </span>
                                </p>
                                <p v-else-if="!item.pronunciation_verified" class="mt-1 text-xs font-bold text-warning">
                                    Pronunciation was not verified for this item yet.
                                </p>
                            </div>
                            <p class="shrink-0 text-2xl font-black" :class="accuracyTone(item.accuracy_percentage)">
                                {{ item.accuracy_percentage }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/reading-intro"><PrimaryButton>Continue</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
