<script setup>
import Task2BWordAssessment from '../../Components/Learner/Task2BWordAssessment.vue';

defineProps({
    items: Array,
    initialIndex: Number,
    assessmentAttemptId: Number,
    assessmentMode: Object,
});
</script>

<template>
    <Task2BWordAssessment
        :items="items"
        :initial-index="initialIndex"
        :assessment-attempt-id="assessmentAttemptId"
        :assessment-mode="assessmentMode"
        submit-url="/learner/diagnostic/task-2b"
        submit-error-message="We could not check these sentences yet. Please review them and try again."
    />
</template>
