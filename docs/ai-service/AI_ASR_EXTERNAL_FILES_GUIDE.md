# AI ASR External Files Guide

The main ReaDirect Laravel repository does not contain ASR model files, speech datasets, or training artifacts. Laravel calls the separate `ReaDirect-AI-ASR` FastAPI service over HTTP.

The AI ASR runtime is Wav2Vec2-only. Laravel should not store local Wav2Vec2 model paths except as display-only metadata returned by FastAPI.

External artifacts that belong outside Laravel:

| Artifact | Owner | Stored in Laravel | Notes |
| --- | --- | --- | --- |
| Fine-tuned Wav2Vec2 letters-v2 ASR model | AI runtime | No | Reported path is typically `models/wav2vec2-readirect-asr-letters-v2` |
| Previous Wav2Vec2 v1 ASR model | AI runtime | No | Reported base/reference path is typically `models/wav2vec2-readirect-asr` |
| Wav2Vec2 phoneme model | AI runtime | No | Reported path is typically `models/wav2vec2-phoneme` |
| Speech datasets | AI training/evaluation | No | Used outside Laravel |
| Training manifests/evaluation outputs | AI repo | No | Used outside Laravel |

Laravel stores per-attempt ASR response metadata in existing response/audio JSON and AI columns for QA review.
