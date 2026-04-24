# ReaDirect Backup And Restore

Use PostgreSQL tools for database backups. Do not include private learner data in public backups. Encrypt backups for real deployments.

## Export Database

```powershell
pg_dump -U postgres -d readirect -f readirect_backup.sql
```

For a custom-format backup:

```powershell
pg_dump -U postgres -d readirect -Fc -f readirect_backup.dump
```

## Restore Database

For a plain SQL backup:

```powershell
psql -U postgres -d readirect -f readirect_backup.sql
```

For a custom-format backup:

```powershell
pg_restore -U postgres -d readirect --clean --if-exists readirect_backup.dump
```

## Audio And File Storage

Database backups do not include audio files if audio is stored separately. Back up the configured private storage location too, such as:

```text
storage/app/private
```

Later deployment environments may use cloud/private object storage. Back up that storage separately from PostgreSQL.

## Safety Notes

- Never commit `.sql`, `.dump`, or `.backup` files.
- Do not share backups containing learner data through public channels.
- Encrypt backups for government or client deployment.
- Keep restore testing separate from the production database.
