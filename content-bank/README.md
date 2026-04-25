# ReaDirect Content Bank

This folder gives collaborators a clean place to review and enrich editable ReaDirect content outside the app.

## Folders

- `content-bank/export/` contains Git-tracked copies of editable seed content from `database/seed-data/readirect/`.
- `content-bank/import/` is a local working folder for edited CSVs before they are reviewed and copied back into the app seed data.
- `database/seed-data/readirect/` remains the current source used by Laravel seeders.

## Workflow

1. Review or edit files in `content-bank/export/` on a feature branch.
2. Keep CSV headers unchanged unless the corresponding importer/seeder is updated.
3. Open a pull request for reviewed content changes.
4. For local collaborator handoff, place edited files in `content-bank/import/`.
5. After validation, copy approved files back to `database/seed-data/readirect/` manually or with `scripts/import-content-bank.ps1`.

## Helper Scripts

PowerShell helpers are available:

```powershell
.\scripts\export-content-bank.ps1
.\scripts\import-content-bank.ps1
```

The export script copies known seed files into `content-bank/export/`.

The import script copies files from `content-bank/import/` back to `database/seed-data/readirect/` after confirmation. It does not delete files, and it skips hidden files plus `README.md`.

## Git Safety

- `content-bank/export/**` is intended to be committed.
- `content-bank/import/README.md` and `content-bank/import/.gitkeep` are committed.
- Other files placed in `content-bank/import/` are ignored by Git.

Do not commit private learner data, real audio, API keys, database dumps, `.env` files, or other sensitive data.
