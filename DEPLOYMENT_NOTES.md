# ReaDirect Deployment Notes

PostgreSQL is the production database target for ReaDirect. SQLite was used only for early local development.

## Database Handoff

Government or client deployment should run migrations and seeders on the target server:

```powershell
php artisan migrate --force
php artisan db:seed --force
```

For a fresh non-production environment:

```powershell
php artisan migrate:fresh --seed
```

Do not deliver a local SQLite database as the main deployment method.

## Environment Configuration

Use private server `.env` values:

```env
DB_CONNECTION=pgsql
DB_HOST=your-database-host
DB_PORT=5432
DB_DATABASE=readirect
DB_USERNAME=your-server-user
DB_PASSWORD=your-server-password
```

Never commit `.env`.

## Seed Data

Development seed data lives in `database/seed-data/readirect`. Official ARAL-aligned content can replace those CSVs later. Production deployments should confirm which seeders are appropriate before running them.

## Backup Strategy

Use migrations and seeders for repeatable setup. Use `pg_dump` only for data handoff or backups where preserving current records matters.

Database backups do not include separately stored audio files. Back up private file storage independently.

## Security Notes

- Keep PostgreSQL credentials private.
- Run migrations on the target server.
- Store learner audio in private storage.
- Do not expose raw database backups.
- Encrypt production backups.
