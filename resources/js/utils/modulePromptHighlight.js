const textValue = (value) => String(value ?? '').trim();

const isSingleLetter = (value) => /^[A-Za-z]$/.test(textValue(value));

const isWordLike = (value) => /^[A-Za-z0-9'-]+$/.test(textValue(value));

export const highlightTargetsForModuleItem = (item) => {
    const payload = item?.payload ?? {};
    const prompt = textValue(item?.display_prompt ?? item?.prompt ?? payload.prompt_text);
    const displayFormat = textValue(payload.display_format);
    const highlightTarget = payload.highlight_target === true || payload.highlight_target === 1 || payload.highlight_target === '1';
    const highlightedLetter = textValue(payload.highlighted_letter);
    const highlightedWord = textValue(payload.highlighted_word);
    const targetWord = textValue(payload.target_word);

    if (!prompt || !highlightTarget) {
        return [];
    }

    if (highlightedLetter && isSingleLetter(highlightedLetter)) {
        return [{
            text: highlightedLetter,
            matchCase: false,
            wholeWord: false,
        }];
    }

    if (highlightedWord || targetWord) {
        const word = highlightedWord || targetWord;

        return [{
            text: word,
            matchCase: false,
            wholeWord: isWordLike(word),
        }];
    }

    if (displayFormat.includes('highlight')) {
        const fallback = textValue(payload.expected_answer);

        if (fallback) {
            return [{
                text: fallback,
                matchCase: false,
                wholeWord: fallback.length < prompt.length && isWordLike(fallback),
            }];
        }
    }

    return [];
};
