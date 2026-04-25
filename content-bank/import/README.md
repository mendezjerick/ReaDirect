# Content Bank Import Workspace

This folder is for edited or enriched content ZIP files before they are imported back into ReaDirect.

Place edited ZIPs here temporarily during review. Files placed here are intentionally ignored by Git so local working files are not committed by accident.

Only these files are committed:

- `.gitkeep`
- `README.md`

Do not place private learner data, real audio, API keys, database dumps, `.env` files, or sensitive data here.

Recommended ZIP name:

```text
readirect-content-bank-import.zip
```

Preview an import:

```powershell
.\scripts\import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -DryRun
```

After edited files are validated, copy them to `database/seed-data/readirect/` with:

```powershell
.\scripts\import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -Force
```
