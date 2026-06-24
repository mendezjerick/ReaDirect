<script setup>
import PassageReadingAssessment from '../../Components/Learner/PassageReadingAssessment.vue';

defineProps({
    passage: Object,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});
</script>

<template>
    <PassageReadingAssessment
        :passage="passage"
        :assessment-attempt-id="assessmentAttemptId"
        :assessment-mode="assessmentMode"
        submit-url="/learner/diagnostic/passage"
        initial-agent-message="Read the passage aloud. Try your best and speak clearly."
        fallback-diff-for-incorrect-words
    />
</template>
