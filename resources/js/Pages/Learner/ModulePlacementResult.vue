<script setup>
import { Link } from '@inertiajs/vue3';
import LearnerLayout from '../../Layouts/LearnerLayout.vue';
import LessonCard from '../../Components/LessonCard.vue';
import RewardBadge from '../../Components/RewardBadge.vue';
import PrimaryButton from '../../Components/PrimaryButton.vue';
import BottomActionBar from '../../Components/BottomActionBar.vue';

defineProps({ decision: Object });
</script>

<template>
    <LearnerLayout :progress="100">
        <section class="mx-auto grid max-w-2xl gap-6 text-center">
            <RewardBadge title="Path Ready" />
            <h1 class="text-4xl font-black text-text">Your reading path is ready.</h1>
            <LessonCard :title="decision.module_key ? decision.module_key.replace('_', ' ') : 'No module needed'" :description="decision.decision_reason" active />
        </section>
        <BottomActionBar>
            <Link href="/learner/dashboard"><PrimaryButton>Back home</PrimaryButton></Link>
        </BottomActionBar>
    </LearnerLayout>
</template>
