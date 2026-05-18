export const diagnosticStepKeys = {
    INTRO: 'intro',
    WARM_UP: 'warm-up',
    TASK_1: 'task-1',
    TASK_2A: 'task-2a',
    TASK_2B: 'task-2b',
    SENTENCE_READING: 'sentence-reading',
};

const diagnosticStepDefinitions = [
    { key: diagnosticStepKeys.INTRO, label: 'Intro' },
    { key: diagnosticStepKeys.WARM_UP, label: 'Warm-Up' },
    { key: diagnosticStepKeys.TASK_1, label: 'Task 1' },
    { key: diagnosticStepKeys.TASK_2A, label: 'Task 2A' },
    { key: diagnosticStepKeys.TASK_2B, label: 'Task 2B' },
    { key: diagnosticStepKeys.SENTENCE_READING, label: 'Sentence Reading' },
];

export const diagnosticStepsFor = (currentKey) => {
    const currentIndex = Math.max(0, diagnosticStepDefinitions.findIndex((step) => step.key === currentKey));

    return diagnosticStepDefinitions.map((step, index) => ({
        label: step.label,
        status: index < currentIndex ? 'complete' : (index === currentIndex ? 'current' : 'pending'),
    }));
};
