export const normalizeAsrResponse = (response = {}) => {
    const scoringTranscript = String(
        response.corrected_transcript
        ?? response.transcript
        ?? response.raw_transcript
        ?? ''
    ).trim();
    const displayTranscript = String(
        response.displayed_transcript
        ?? response.corrected_transcript
        ?? response.transcript
        ?? response.raw_transcript
        ?? ''
    ).trim();

    return {
        scoringTranscript,
        displayTranscript,
        debugTranscript: String(response.raw_transcript ?? '').trim(),
        canSubmit: response.can_submit !== false
            && response.retry_required !== true
            && response.uncertain !== true
            && displayTranscript !== '',
        retryRequired: response.retry_required === true,
        uncertain: response.uncertain === true,
        message: response.learner_retry_message
            ?? response.transcription_message
            ?? response.message
            ?? 'We could not hear your answer clearly. Please try recording again.',
    };
};

export const appendAudioMetadata = (payload, file) => {
    const metadata = file?.audioMetadata;

    if (!metadata) return;

    Object.entries(metadata).forEach(([key, value]) => {
        if (value !== null && value !== undefined) {
            payload.append(`audio_metadata[${key}]`, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
        }
    });
};
