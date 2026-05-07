# Laravel AI Integration Contract

Laravel calls the FastAPI ASR service and does not run ASR inference locally.

## Request Fields

ASR requests should include audio plus available scoring context:

- `expected_text`
- `prompt_type`
- `activity_type`
- `module_type`
- `assessment_type`
- `item_id`
- `learner_id`
- `attempt_id`
- `current_scoring_context`
- `content_metadata`

## Response Fields

Laravel consumes Wav2Vec2-only ASR responses with these fields when present:

- `transcript`
- `raw_transcript`
- `wav2vec2_transcript`
- `corrected_transcript`
- `displayed_transcript`
- `expected_text`
- `prompt_type`
- `asr_route`
- `model_family`
- `model_used`
- `accepted`
- `raw_wer`
- `corrected_wer`
- `raw_cer`
- `corrected_cer`
- `phonetic_similarity_score`
- `composite_score`
- `threshold_used`
- `normalization_reason`
- `correction_strategy_used`
- `audio_quality`
- `pause_metrics`
- `retry_required`
- `uncertain`
- `uncertainty_reasons`
- `debug_metadata`

Missing optional fields are safe.

## Transcript Selection

Scoring uses `corrected_transcript`, then `transcript`, then `raw_transcript`.

Learner UI uses `displayed_transcript`, then `corrected_transcript`, then `transcript`, then `raw_transcript`.

Admin/debug views preserve raw and corrected/displayed transcript metadata.
