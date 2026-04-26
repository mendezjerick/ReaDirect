# AI Service Final Handoff Checklist

- [ ] `ReaDirect-AI-ASR` service starts successfully on the deployment machine.
- [ ] `python scripts/validate_ai_service_startup.py` passes in the AI repo.
- [ ] `GET http://127.0.0.1:8001/health` returns OK.
- [ ] Laravel `.env` contains the `READIRECT_AI_*` variables.
- [ ] `READIRECT_AI_ENABLED=true` only when the AI service is running.
- [ ] Laravel can submit `/analyze-text` and `/analyze-audio` payloads through the server-side AI client.
- [ ] Existing STT fallback remains configured for local testing.
- [ ] Enriched content files have been reviewed before seeding/importing.
- [ ] Speechocean762 and training manifests are not copied into the Laravel repo.
- [ ] Model artifacts remain in `ReaDirect-AI-ASR/model_artifacts/` or a deployment model path, not GitHub.
- [ ] Admin/debug views are restricted to admin/testing users.
- [ ] Student-facing views show only safe feedback.

