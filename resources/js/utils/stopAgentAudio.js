export function stopAllAgentAudio() {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent('readirect:stop-agent-audio'));
    window.dispatchEvent(new CustomEvent('readirect:stop-agent-speech'));
}

export async function stopAllAgentAudioBeforeRecording() {
    stopAllAgentAudio();

    await new Promise((resolve) => window.setTimeout(resolve, 80));
}
