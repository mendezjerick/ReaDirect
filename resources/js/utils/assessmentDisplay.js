export const RESULT_TONE_CORRECT = 'result-correct';
export const RESULT_TONE_WRONG = 'result-wrong';
export const RESULT_TONE_ITEM = 'item';
export const RESULT_TONE_ASSESSMENT = 'assessment-neutral';

export const RESULT_COLOR_ITEM = '#000000';
export const RESULT_COLOR_CORRECT = '#4c563f';
export const RESULT_COLOR_WRONG = '#692721';
export const RESULT_COLOR_ASSESSMENT = '#2563eb';

const booleanFrom = (value) => {
    if (value === true || value === 1 || value === '1') return true;
    if (value === false || value === 0 || value === '0') return false;

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        if (normalized === 'true' || normalized === 'correct' || normalized === 'accepted') return true;
        if (normalized === 'false' || normalized === 'incorrect' || normalized === 'wrong' || normalized === 'rejected') return false;
    }

    return null;
};

const firstBoolean = (values) => {
    for (const value of values) {
        const normalized = booleanFrom(value);

        if (normalized !== null) {
            return normalized;
        }
    }

    return null;
};

export const acceptedFromAsrResult = (result = null) => {
    if (!result || typeof result !== 'object') {
        return null;
    }

    return firstBoolean([
        result.accepted,
        result.is_correct,
        result.is_accepted,
        result.final_correctness,
        result.final_correctness_result,
        result.scoring?.accepted,
        result.scoring?.is_correct,
        result.ai_response?.accepted,
        result.ai_response?.is_correct,
        result.ai_response?.is_accepted,
        result.expected_centric?.match,
        result.trace?.expected_centric?.match,
    ]);
};

export const latestRetryAttempt = (retryState = null) => {
    const attempts = Array.isArray(retryState?.attempts) ? retryState.attempts : [];

    return attempts.length ? attempts[attempts.length - 1] : null;
};

export const acceptedFromRetryState = (retryState = null) => {
    const latestAttempt = latestRetryAttempt(retryState);

    if (latestAttempt) {
        return booleanFrom(latestAttempt.is_correct);
    }

    if (retryState?.is_resolved !== true && Number(retryState?.attempt_count ?? 0) <= 0) {
        return null;
    }

    return firstBoolean([
        retryState?.is_correct,
    ]);
};

export const acceptedFromSavedResponse = (savedResponse = null) => (
    firstBoolean([savedResponse?.is_correct])
);

export const resultToneForAccepted = (accepted) => (
    accepted === true ? RESULT_TONE_CORRECT : RESULT_TONE_WRONG
);

export const resultColorForTone = (tone) => {
    if (tone === RESULT_TONE_ASSESSMENT) return RESULT_COLOR_ASSESSMENT;
    if (tone === RESULT_TONE_CORRECT) return RESULT_COLOR_CORRECT;
    if (tone === RESULT_TONE_WRONG) return RESULT_COLOR_WRONG;

    return RESULT_COLOR_ITEM;
};

export const letterPairDisplay = (...values) => {
    for (const value of values) {
        const match = String(value ?? '').trim().match(/[A-Za-z]/);

        if (match) {
            const letter = match[0];

            return `${letter.toUpperCase()}${letter.toLowerCase()}`;
        }
    }

    return '';
};
