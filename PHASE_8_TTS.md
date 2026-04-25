# Phase 8 TTS

Phase 8 adds browser text-to-speech for the three fixed ReaDirect agents. It uses the Web Speech API in the learner browser and does not change scoring, placement, mastery, transcript handling, or LLM feedback generation.

## Agents and Voices

The reusable component is `resources/js/Components/Agents/AgentSpeakerTTS.vue`.

Voice selection depends on voices installed in the learner browser. ReaDirect first looks for these preferred American English voices, then falls back to any available English voice:

| Agent | Preferred voice style | Preferred voice names | Default tuning |
| --- | --- | --- | --- |
| Assessment Agent | Calm, soft female | Samantha, Susan, Zira, Joanna, Aria | rate 0.95, pitch 1.0 |
| Coach + Feedback Agent | Mature, supportive female | Victoria, Alice, Salli, Ava, Jenny | rate 1.0, pitch 1.05 |
| Evaluator / Recommendation Agent | Cheerful, energetic female | Jenny, Kendra, Aria, Samantha | rate 1.05, pitch 1.1 |

## How Speaking Is Triggered

`resources/js/Components/Learner/AgentSpeakerPanel.vue` now includes the TTS component. When the panel message changes, the current message is spoken automatically unless muted.

The panel emits no scoring or answer changes. It only updates the visible agent state to `speaking` while speech is active and returns to the page-provided state after speech ends.

## Controls

Each agent panel includes:

- Mute/unmute button
- Replay current message button
- Visual speaking state

The mute preference is stored in browser `localStorage` under `readirect-agent-tts-muted`.

## Fallback

If the Web Speech API is unavailable, the app keeps showing the dialogue bubble and logs:

```text
Web Speech API not supported.
```

The learner flow continues normally. TTS failures never block scoring, navigation, feedback, or assessment progress.

## Adjusting Audio

`AgentSpeakerPanel` accepts these optional props and passes them to the TTS component:

- `volume`: `0` to `1`
- `rate`: `0.8` to `1.2`
- `pitch`: `0.8` to `1.2`
- `ttsEnabled`: disable TTS for a specific panel
- `defaultMuted`: start muted if no browser preference exists

The agent-specific rate and pitch are multiplied by the provided values.

## Accessibility

- Messages remain visible in text, so TTS is optional.
- Mute and replay are keyboard-accessible buttons.
- Only one message is spoken at a time; new speech cancels previous speech.
- The panel includes an `aria-live` text node for assistive technologies.

## Future Server-Side TTS

If client deployment later requires consistent voices across browsers, a server-side TTS endpoint can be added without changing scoring:

```text
POST /agent-tts
body: agentType, text
response: audio blob
```

That endpoint should use the same safety rules as Phase 7 and should never expose API keys to frontend code.

## Manual Verification

1. Open a diagnostic task and submit or advance an answer. The Assessment Agent should speak in the configured browser voice.
2. Open a module activity and submit an answer. The Coach + Feedback Agent should speak the feedback/commentary.
3. Open a summary or placement page. The Evaluator / Recommendation Agent should speak the result message.
4. Toggle mute. Current speech should stop, and future messages should stay silent.
5. Click replay. The current message should speak again.
6. Test in a browser without Web Speech API support or with speech disabled. The panel should still display the message and the app should not crash.
