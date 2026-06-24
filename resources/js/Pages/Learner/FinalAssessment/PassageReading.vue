<script setup>
import PassageReadingAssessment from '../../../Components/Learner/PassageReadingAssessment.vue';

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
        submit-url="/final-assessment/passage/submit"
        initial-agent-message="This is your final reading check. Read the passage aloud and try your best."
    />
</template>
