/**
 * Reading Illustrations — maps words and passages to placeholder images.
 *
 * Word images:   /ia-assets/images/reading/words/{word}.svg
 * Passage images: /ia-assets/images/reading/passages/{slug}.svg
 *
 * To replace a placeholder, drop a new file with the same name into the
 * corresponding ReaDirect-IA directory.  No code changes are needed.
 */

const WORD_IMAGE_BASE = '/ia-assets/images/reading/words';
const PASSAGE_IMAGE_BASE = '/ia-assets/images/reading/passages';

/** Words that have a placeholder illustration. */
const ILLUSTRATED_WORDS = new Set([
    'ball', 'bat', 'bed', 'bee', 'bit', 'book', 'box', 'bug',
    'car', 'cat', 'cup', 'dog', 'duck', 'fish', 'hand', 'hat',
    'hop', 'lip', 'man', 'moon', 'pan', 'pen', 'pet', 'pin',
    'red', 'rock', 'run', 'sit', 'sun', 'tap', 'top', 'tree',
]);

/** Passage title → slug mapping. */
const PASSAGE_SLUGS = {
    'ana and ben at the park': 'passage-park',
    'leo and the kite': 'passage-kite',
};

/**
 * Get the image URL for a word prompt.
 *
 * @param {string} word – the word (case-insensitive, auto-trimmed)
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
 * @param {string} sourceCsvId – the passage ID
 * @returns {string} image path
 */
export function getPassageImage(sourceCsvId) {
    if (!sourceCsvId) return `${PASSAGE_IMAGE_BASE}/passage-default.svg`;

    const slug = String(sourceCsvId).toLowerCase().trim();

    return `${PASSAGE_IMAGE_BASE}/${slug}.png`;
}
