# Miss Ciel Coach Decision Service

## Purpose

Miss Ciel is ReaDirect's lightweight intelligent reading coach. Her runtime
behavior is deterministic, context-aware, and based only on approved dialogue
templates. She does not use Ollama, OpenAI, Claude, or another LLM.

Vivian and Estelle remain role-based pedagogical agents in this phase:

- Vivian owns diagnostic and final-assessment guidance.
- Estelle owns routing, summaries, placement, recommendations, and results.
- Ciel owns module and mastery practice coaching.

## Repository Ownership

### ReaDirect-IA

ReaDirect-IA is the source of truth for Ciel's definition, guardrails,
policies, approved dialogue, contracts, scenarios, and exact media paths. It
is not a running microservice.

### ReaDirect

Laravel executes `CielCoachDecisionService`. It owns learner records, retry
history, official scoring, module placement, mastery, progression, TTS
requests, and Vue/Inertia delivery.

The service only selects feedback. Every decision returns:

```json
{
  "agent": "ciel",
  "action": "advise",
  "dialogue_key": "ciel.module.close_retry.final_sound",
  "message": "Almost there. Say the ending sound clearly this time.",
  "reason_codes": ["CORRECTIVE_HINT", "FINAL_SOUND_ERROR"],
  "context": "module_practice",
  "source_type": "module",
  "tts_voice": "miss_ciel",
  "should_request_tts": true,
  "official_progression_changed": false
}
```

`official_progression_changed` is always `false`.

### ReaDirect-AI-ASR

ASR and expected-centric algorithms remain the main intelligent speech and
assessment core. For modules, Ciel may consume correctness, error type,
similarity, confidence, uncertainty, retry status, attempts, streaks, skill
signals, and section completion. These select feedback only.

### ReaDirect-TTS

TTS receives only the approved message and Miss Ciel voice selection. It does
not receive raw history, choose dialogue, rewrite text, or make decisions.

## Module Practice Flow

```text
learner attempt
-> ASR and expected-centric evidence
-> official Laravel scoring
-> CielCoachDecisionService
-> agent_cue
-> Vue agent player and TTS
```

Module check endpoints retain existing response fields and additionally
return `agent_cue`. Vue prefers its resolved action and message when present.

Existing media behavior is unchanged: interactions play once, busy triggers
are discarded, no queue exists, playback returns to idle, and PNG remains the
failure fallback.

## Future ReaDirect-Game Contract

The planned game integration is listening-based, not recording-based. The
game is not implemented in this phase.

Example request:

```json
{
  "source_type": "readirect_game",
  "context": "listening_game_practice",
  "activity_type": "listen_word",
  "target_text": "cat",
  "instruction_mode": "model_pronunciation"
}
```

Example response:

```json
{
  "agent": "ciel",
  "action": "talk",
  "dialogue_key": "ciel.game.model_pronunciation",
  "message": "Listen carefully. This is how we say it: cat.",
  "reason_codes": ["PRONUNCIATION_MODEL"],
  "context": "listening_game_practice",
  "source_type": "readirect_game",
  "tts_voice": "miss_ciel",
  "should_request_tts": true,
  "official_progression_changed": false
}
```

This path does not require recording, a transcript, correctness, scoring, ASR
confidence, retry flags, or a call to ReaDirect-AI-ASR. It does not update
official progress.

Supported future modes:

- `model_pronunciation`
- `repeat_after_me`
- `listen_and_choose`
- `sound_focus`
- generic listening guidance

## Configuration

Local development defaults to the sibling specification repository:

```env
REA_CIEL_SPEC_PATH=../ReaDirect-IA
```

Laravel reads `dialogue/ciel.yaml` and uses an equivalent built-in approved
catalog if the file is unavailable.

## LLM Removal

Miss Ciel no longer injects or calls an LLM client, checks Ollama enable
flags, accepts generated feedback, writes runtime Ciel LLM interactions, or
depends on LLM health.

Historical LLM tables and unrelated legacy admin code remain for migration
and audit compatibility. They are not part of Ciel's runtime path.
