# AI/ASR External Files Guide

The main ReaDirect Laravel repository does not contain the AI model, external speech datasets, or Whisper training files. Laravel calls the separate `ReaDirect-AI-ASR` FastAPI service over HTTP.

## Main Laravel Repository

Do not download or copy these files into this repository:

- Speechocean762
- external training datasets
- Whisper training manifests or JSONL files
- model checkpoints or model artifacts
- raw learner audio outside normal application uploads

Only copy or import:

1. AI integration documentation
2. Laravel `.env` variables
3. enriched content ZIP/CSVs if included
4. API examples

## ReaDirect-AI-ASR Repository

Files that may need to be placed manually in the AI repository:

| File/Folder | Needed By | GitHub? | Where to Place |
|---|---|---|---|
| Speechocean762 archive | AI training/evaluation only | No | `ReaDirect-AI-ASR/external_datasets/speechocean762/raw/` |
| Fine-tuned Whisper model | AI runtime | No | `ReaDirect-AI-ASR/model_artifacts/readirect-whisper-base-en-v1-hf/` |
| CMUdict | AI phoneme analysis | Maybe/Yes if allowed | `ReaDirect-AI-ASR/external_datasets/cmudict/` |
| Enriched content ZIP | Laravel content import | Maybe/exported | `ReaDirect/content-bank/import/` |
| AI integration ZIP | Laravel integration docs/import | Temporary | `ReaDirect/import/` |

Speechocean762 is only needed for reproducing training/evaluation. The fine-tuned model is needed for runtime when the AI service uses `ASR_PROVIDER=hf_whisper_local`.

Collaborators who only work on Laravel do not need Speechocean762 or the model. Collaborators who run the AI service need the model artifact. Collaborators who reproduce training need Speechocean762.

