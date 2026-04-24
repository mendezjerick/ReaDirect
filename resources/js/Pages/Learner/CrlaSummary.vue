<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import ScoreCard from '../../Components/ScoreCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ attempt: Object });
</script>

<template>
    <LearnerLayout :progress="65">
        <div class="mx-auto grid max-w-3xl gap-6 text-center">
            <RewardBadge title="CRLA Complete" />
            <h1 class="text-4xl font-black text-text">Great effort. The first reading check is done.</h1>
            <div class="grid gap-4 md:grid-cols-4">
                <ScoreCard label="Letters" :value="attempt.task_1_score" />
                <ScoreCard label="Rhymes" :value="attempt.task_2a_score" />
                <ScoreCard label="Words" :value="attempt.task_2b_score" />
                <ScoreCard label="CRLA level" :value="attempt.crla_classification" />
            </div>
        </div>
        <BottomActionBar>
            <Link href="/learner/diagnostic/reading-intro"><PrimaryButton>Continue</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
