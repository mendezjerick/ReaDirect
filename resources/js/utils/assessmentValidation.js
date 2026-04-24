export const hasAllRequiredAnswers = (answers) =>
    Object.values(answers).every((answer) => String(answer ?? '').trim().length > 0);

export const getMissingAnswerIndexes = (items, answers, idKey = 'id') =>
    items
        .map((item, index) => (String(answers[item[idKey]] ?? '').trim().length > 0 ? null : index))
        .filter((index) => index !== null);

export const validateRequiredAnswersBeforeSubmit = (items, answers, idKey = 'id') => {
    const missing = getMissingAnswerIndexes(items, answers, idKey);

    return {
        isComplete: missing.length === 0,
        missing,
        message: missing.length === 0 ? '' : 'Almost there! Finish all items to continue.',
    };
};

export const focusFirstMissingAnswer = (missingIndexes) => {
    if (!missingIndexes.length) return;

    requestAnimationFrame(() => {
        const element = document.querySelector(`[data-answer-index="${missingIndexes[0]}"] input`);
        element?.focus();
        element?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
};
