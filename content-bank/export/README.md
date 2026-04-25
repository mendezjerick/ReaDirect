# Content Bank Export

This folder contains the Git-tracked ReaDirect content export.

Primary file:

```text
readirect-content-bank-export.zip
```

The ZIP contains organized editable seed content copied from:

```text
database/seed-data/readirect/
```

The expanded folders in this directory are also tracked for easy Git diffs and direct review:

- `assessment/`
- `modules/`
- `agents/`
- `rules/`
- `feedback/`
- `prompts/`
- `docs/`

Keep CSV headers unchanged unless the importer/seeder is also updated. Coordinate large CSV edits on branches to avoid merge conflicts.

Refresh this export with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/export-content-bank.ps1
```
