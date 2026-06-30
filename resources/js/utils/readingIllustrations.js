/**
 * Reading Illustrations - maps words and passages to image assets.
 *
 * Word images: /images/reading/words/{word}.svg
 * Passage images: /ia-graphics/{filename}
 */

const WORD_IMAGE_BASE = '/images/reading/words';
const PASSAGE_IMAGE_BASE = '/ia-graphics';

/** Words that have a placeholder illustration. */
const ILLUSTRATED_WORDS = new Set([
    'ball', 'bat', 'bed', 'bee', 'bit', 'book', 'box', 'bug',
    'car', 'cat', 'cup', 'dog', 'duck', 'fish', 'hand', 'hat',
    'hop', 'lip', 'man', 'moon', 'pan', 'pen', 'pet', 'pin',
    'red', 'rock', 'run', 'sit', 'sun', 'tap', 'top', 'tree',
]);

/** Passage IDs for the active assessment stories. */
const PASSAGE_IMAGE_OVERRIDES = {
    'pass-001': 'story1.png',
    'pass-002': 'story2.png',
};

/**
 * Get the image URL for a word prompt.
 *
 * @param {string} word - the word (case-insensitive, auto-trimmed)
 * @returns {string|null} image path or null
 */
export function getWordImage(word) {
    const key = String(word ?? '').trim().toLowerCase();

    if (!key || !ILLUSTRATED_WORDS.has(key)) return null;

    return `${WORD_IMAGE_BASE}/${key}.svg`;
}

/**
 * Get the image URL for a reading passage.
 *
 * @param {string} sourceCsvId - the passage ID
 * @param {number|string|null} storyNumber - optional story number from the passage payload
 * @returns {string} image path
 */
export function getPassageImage(sourceCsvId, storyNumber = null) {
    const number = Number(storyNumber);

    if (Number.isInteger(number) && number >= 1 && number <= 2) {
        return `${PASSAGE_IMAGE_BASE}/story${number}.png`;
    }

    if (!sourceCsvId) return `${PASSAGE_IMAGE_BASE}/passage-default.svg`;

    const slug = String(sourceCsvId).toLowerCase().trim();
    const override = PASSAGE_IMAGE_OVERRIDES[slug];

    if (override) {
        return `${PASSAGE_IMAGE_BASE}/${override}`;
    }

    return `${PASSAGE_IMAGE_BASE}/${slug}.png`;
}
