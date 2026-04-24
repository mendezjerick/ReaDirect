<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import AgentPanel from '../../Components/AgentPanel.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ attempt: Object });
</script>

<template>
    <LearnerLayout :progress="94">
        <div class="mx-auto grid max-w-3xl gap-6">
            <h1 class="text-center text-4xl font-black text-text">Reading check complete.</h1>
            <div class="grid gap-4 md:grid-cols-2">
                <ScoreCard label="Accuracy" :value="attempt.reading_accuracy" suffix="%" />
                <ScoreCard label="Comprehension" :value="attempt.comprehension_percentage" suffix="%" />
                <ScoreCard label="Final reading score" :value="attempt.final_reading_score" />
                <ScoreCard label="Reading level" :value="attempt.reading_classification" />
            </div>
            <AgentPanel title="Evaluator / Recommendation Agent">Reading level is based only on the final reading score.</AgentPanel>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/module-placement"><PrimaryButton>See my path</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
