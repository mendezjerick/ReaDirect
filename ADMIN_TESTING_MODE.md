# ReaDirect Admin Testing / QA Mode

Testing / QA Mode is available to `system_admin` users at `/admin/testing`.

It lets developers and authorized admins test learner flows without completing every step in sequence.

## What It Supports

- Select a learner.
- Create sandbox diagnostic, module, or final reassessment attempts.
- Jump directly to learner dashboard, diagnostic tasks, module activities, mastery checks, and final reassessment pages.
- View admin-only debug screens for assessment attempts, module attempts, STT results, and LLM interactions.

## Sandbox Attempts

Sandbox records use:

- `assessment_attempts.is_sandbox = true`
- `module_attempts.is_sandbox = true`

Sandbox attempts are clearly separated from normal learner progress and excluded from teacher reports by default.

## Debug Data

Admin debug views can show raw and normalized STT transcript, transcript source, STT confidence/provider/error, expected answers, accepted answers, prompt snapshots, answer matching, similarity labels, scores, rules, audio metadata, and LLM fallback/commentary metadata.

Students never see this debug data. Query parameters alone cannot enable Testing / QA Mode; the session must belong to an authorized system admin.

## Audit Logging

The system logs learner testing starts, sandbox creation, debug views, and testing mode exit events.
