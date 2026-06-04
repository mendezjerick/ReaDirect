const textValue = (value) => String(value ?? '').trim();

const isSingleLetter = (value) => /^[A-Za-z]$/.test(textValue(value));

const isWordLike = (value) => /^[A-Za-z0-9'-]+$/.test(textValue(value));

export const highlightTargetsForModuleItem = (item) => {
    const payload = item?.payload ?? {};
    const prompt = textValue(item?.prompt ?? payload.prompt_text);
    const expectedAnswer = textValue(payload.expected_answer);
    const targetWord = textValue(payload.target_word);

    if (!prompt) {
        return [];
    }

    if (isSingleLetter(expectedAnswer)) {
        return [{
            text: expectedAnswer,
            matchCase: prompt.includes(expectedAnswer),
            wholeWord: true,
        }];
    }

    if (targetWord && targetWord.length < prompt.length) {
        return [{
            text: targetWord,
            matchCase: false,
            wholeWord: isWordLike(targetWord),
        }];
    }

    if (expectedAnswer) {
        return [{
            text: expectedAnswer,
            matchCase: false,
            wholeWord: expectedAnswer.length < prompt.length && isWordLike(expectedAnswer),
        }];
    }

    return [];
};
