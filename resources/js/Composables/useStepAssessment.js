import { computed, reactive, ref } from 'vue';

export function useStepAssessment(items, options = {}) {
    const idKey = options.idKey ?? 'id';
    const itemList = ref(items);
    const answers = reactive(Object.fromEntries(itemList.value.map((item) => [item[idKey], ''])));
    const normalizedInitialIndex = Number(options.initialIndex ?? 0);
    const currentIndex = ref(Math.min(Math.max(Number.isFinite(normalizedInitialIndex) ? normalizedInitialIndex : 0, 0), Math.max(itemList.value.length - 1, 0)));
    const feedback = ref('');

    const currentItem = computed(() => itemList.value[currentIndex.value]);
    const isFirst = computed(() => currentIndex.value === 0);
    const isLast = computed(() => currentIndex.value === itemList.value.length - 1);
    const isAnswered = (item) => {
        const answer = answers[item?.[idKey]];

        if (typeof options.isAnswered === 'function') {
            return options.isAnswered(item, answer);
        }

        return String(answer ?? '').trim().length > 0;
    };
    const answeredCount = computed(() => itemList.value.filter((item) => isAnswered(item)).length);
    const isCurrentAnswered = computed(() => isAnswered(currentItem.value));
    const isComplete = computed(() => answeredCount.value === itemList.value.length);
    const firstUnansweredIndex = computed(() => itemList.value.findIndex((item) => !isAnswered(item)));
    const progressPercent = computed(() => Math.round(((currentIndex.value + 1) / Math.max(itemList.value.length, 1)) * 100));

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

    const validateComplete = () => {
        if (isComplete.value) {
            feedback.value = '';
            return true;
        }

        if (firstUnansweredIndex.value >= 0) {
            currentIndex.value = firstUnansweredIndex.value;
        }

        feedback.value = options.incompleteMessage ?? options.emptyMessage ?? 'Almost there! Finish all items to continue.';
        return false;
    };

    const payload = (mapItem) => itemList.value.map((item) => mapItem(item, answers[item[idKey]]));

    const reset = (nextItems) => {
        itemList.value = nextItems;
        Object.keys(answers).forEach((key) => delete answers[key]);
        nextItems.forEach((item) => {
            answers[item[idKey]] = '';
        });
        currentIndex.value = 0;
        feedback.value = '';
    };

    return {
        items: itemList,
        answers,
        currentIndex,
        currentItem,
        isFirst,
        isLast,
        answeredCount,
        isCurrentAnswered,
        isComplete,
        firstUnansweredIndex,
        progressPercent,
        feedback,
        validateCurrent,
        validateComplete,
        goNext,
        goBack,
        payload,
        reset,
    };
}
