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
    placementPreview: Object,
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
            <AgentSpeakerPanel agent-type="evaluator" state="celebrating" message="The CRLA tasks are complete. Review each score, then we will read a short passage to finish placement." />
        </template>
        <div class="mx-auto grid max-w-3xl gap-6 text-center">
            <RewardBadge title="CRLA Complete" />
            <h1 class="text-4xl font-black text-text">Your CRLA score is ready.</h1>
            <div class="grid gap-4 md:grid-cols-3">
                <ScoreCard label="Task 1 letters" :value="attempt.task_1_score" />
                <ScoreCard label="Task 2A rhymes" :value="attempt.task_2a_score" />
                <ScoreCard label="Task 2B words" :value="attempt.task_2b_score" />
            </div>
            <div class="rounded-2xl border border-border bg-surface px-6 py-5 text-left shadow-lg shadow-primary/10">
                <p class="text-sm font-black uppercase tracking-wide text-muted">CRLA total</p>
                <div class="mt-3 grid gap-4 md:grid-cols-[160px_1fr]">
                    <div>
                        <p class="text-4xl font-black text-primary">{{ attempt.crla_total_score }}/30</p>
                        <p class="mt-1 text-base font-black text-text">{{ attempt.crla_classification }}</p>
                    </div>
                    <div>
                        <p class="text-base font-bold text-muted">{{ placementPreview?.crla_meaning }}</p>
                        <p class="mt-3 text-base font-bold text-text">{{ placementPreview?.decision_reason }}</p>
                    </div>
                </div>
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
                                    {{ item.correct_words ?? item.matched_words }}/{{ item.total_words }} words correct
                                    <span v-if="item.wer !== null">, WER {{ Math.round(item.wer * 100) }}%</span>
                                </p>
                                <p v-if="item.wcpm !== null || item.wpm !== null || item.fluency_label" class="mt-1 text-xs font-bold text-muted">
                                    <span v-if="item.wcpm !== null">{{ item.wcpm }} WCPM</span>
                                    <span v-if="item.wcpm !== null && item.wpm !== null">, </span>
                                    <span v-if="item.wpm !== null">{{ item.wpm }} WPM</span>
                                    <span v-if="item.fluency_label" class="capitalize">
                                        <span v-if="item.wcpm !== null || item.wpm !== null">, </span>{{ item.fluency_label.replace('_', ' ') }}
                                    </span>
                                </p>
                                <p v-if="item.long_pause_warning" class="mt-1 text-xs font-bold text-warning">
                                    {{ item.long_pause_warning }}
                                </p>
                                <p v-if="item.retry_required && item.learner_retry_message" class="mt-1 text-xs font-bold text-warning">
                                    {{ item.learner_retry_message }}
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
            <Link href="/learner/diagnostic/reading-intro"><PrimaryButton>Continue to Passage Reading</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
