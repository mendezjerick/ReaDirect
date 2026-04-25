# ReaDirect Admin Dashboard

Phase 11 adds a professional admin area at `/admin/dashboard`.

To create the first admin user, see `ADMIN_USER_SETUP.md`.

## Roles

- `system_admin`: full admin access, including Testing / QA Mode.
- `school_admin`: limited admin visibility for operational review.
- `teacher`: teacher dashboard only.
- `student`: learner flows only.

The admin area is protected server-side. Frontend navigation is not used as an authorization boundary.

## Sections

- Dashboard: system metrics, recent assessment/module activity, STT failures, LLM fallback events.
- Schools: create, edit, deactivate, reactivate, and review school counts.
- Teachers: create teacher accounts, assign classes, deactivate/reactivate accounts.
- Learners: manage learner records and jump into admin testing for a selected learner.
- Assessment Content: manage diagnostic item-bank records. Used content is deactivated, not deleted.
- Module Content: manage module activity banks and mastery content.
- Rules & Thresholds: view classification rules and edit module mastery thresholds with audit logs.
- Agents: manage fixed agent display details, sprite paths, and Web Speech API voice settings.
- Prompt Templates: view/create/edit prompt templates and prompt history.
- Audit Logs: inspect admin, testing, content, rule, and debug actions.
- System Monitoring: inspect database, queue, storage, STT, LLM, runtime, and deployment notes.

Historical learner records depend on locked `prompt_snapshot` data. Editing content-bank records changes future selections only.

Sandbox attempts are excluded from teacher reports and teacher dashboard analytics by default.
