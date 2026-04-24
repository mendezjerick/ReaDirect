import { computed, reactive, ref } from 'vue';

export function useStepAssessment(items, options = {}) {
    const idKey = options.idKey ?? 'id';
    const answers = reactive(Object.fromEntries(items.map((item) => [item[idKey], ''])));
    const currentIndex = ref(0);
    const feedback = ref('');

    const currentItem = computed(() => items[currentIndex.value]);
    const isFirst = computed(() => currentIndex.value === 0);
    const isLast = computed(() => currentIndex.value === items.length - 1);
    const answeredCount = computed(() => Object.values(answers).filter((answer) => String(answer ?? '').trim()).length);
    const isCurrentAnswered = computed(() => String(answers[currentItem.value?.[idKey]] ?? '').trim().length > 0);
    const isComplete = computed(() => answeredCount.value === items.length);
    const progressPercent = computed(() => Math.round(((currentIndex.value + 1) / Math.max(items.length, 1)) * 100));

    const validateCurrent = () => {
        if (isCurrentAnswered.value) {
            feedback.value = '';
            return true;
        }

        feedback.value = options.emptyMessage ?? 'Let us answer this first.';
        return false;
    };

    const goNext = () => {
        if (!validateCurrent()) return false;
        if (!isLast.value) currentIndex.value += 1;
        return true;
    };

    const goBack = () => {
        feedback.value = '';
        if (!isFirst.value) currentIndex.value -= 1;
    };

    const payload = (mapItem) => items.map((item) => mapItem(item, answers[item[idKey]]));

    return {
        answers,
        currentIndex,
        currentItem,
        isFirst,
        isLast,
        answeredCount,
        isCurrentAnswered,
        isComplete,
        progressPercent,
        feedback,
        validateCurrent,
        goNext,
        goBack,
        payload,
    };
}
