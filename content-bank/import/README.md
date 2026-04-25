# Content Bank Import Workspace

This folder is for edited or enriched content files before they are imported back into ReaDirect.

Place edited CSVs here temporarily during review. Files placed here are intentionally ignored by Git so local working files are not committed by accident.

Only these files are committed:

- `.gitkeep`
- `README.md`

Do not place private learner data, real audio, API keys, database dumps, `.env` files, or sensitive data here.

After edited CSVs are validated, copy them to `database/seed-data/readirect/` manually or use:

```powershell
.\scripts\import-content-bank.ps1
```
