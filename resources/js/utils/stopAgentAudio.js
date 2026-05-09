export async function stopAllAgentAudioBeforeRecording() {
    if (typeof window === 'undefined') {
        return;
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
    }

    window.dispatchEvent(new CustomEvent('readirect:stop-agent-audio'));
    window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));

    await new Promise((resolve) => window.setTimeout(resolve, 80));
}
