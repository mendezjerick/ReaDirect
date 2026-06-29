<script setup>
import Task1LetterAssessment from '../../../Components/Learner/Task1LetterAssessment.vue';

const props = defineProps({
    items: Array,
    initialIndex: Number,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});
</script>

<template>
    <Task1LetterAssessment
        v-bind="props"
        submit-url="/final-assessment/task-1/submit"
        initial-agent-message="Listen carefully first, then say the sound out loud. When you're ready, use a clear voice."
        :continue-messages="[`Thank you. Let's continue to the next item when you are ready.`]"
        submit-error-message="We could not check these letters yet. Please review them and try again."
    />
</template>
