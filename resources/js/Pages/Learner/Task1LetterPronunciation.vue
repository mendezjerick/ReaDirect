<script setup>
import Task1LetterAssessment from '../../Components/Learner/Task1LetterAssessment.vue';

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
        submit-url="/learner/diagnostic/task-1"
    />
</template>
