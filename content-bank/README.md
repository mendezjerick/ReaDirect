# ReaDirect Content Bank

This folder is for sharing, reviewing, and importing editable ReaDirect CSV/content-bank files without sharing a live database.

## ZIP Workflow

- `content-bank/export/readirect-content-bank-export.zip` is the Git-tracked export ZIP.
- `content-bank/import/` is a local drop zone for edited ZIPs and is ignored by Git except `.gitkeep` and `README.md`.
- `content-bank/work/` is a temporary extraction/staging folder and is ignored by Git except `.gitkeep`.
- `database/seed-data/readirect/` remains the source used by Laravel seeders.

## Export

Run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/export-content-bank.ps1
```

This refreshes the organized export folders and creates:

```text
content-bank/export/readirect-content-bank-export.zip
```

## Import

Place an edited ZIP here:

```text
content-bank/import/readirect-content-bank-import.zip
```

Preview what would be copied:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -DryRun
```

Copy reviewed files back to seed data:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -Force
```

After import, review and commit changed files in:

```text
database/seed-data/readirect/
```

Do not commit import ZIPs, work-folder files, private learner data, audio, API keys, or database dumps.
