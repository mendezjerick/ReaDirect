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

## Private Audio Storage

- Learner recordings are stored outside the public web root on Laravel's private `local` disk.
- Back up `storage/app/private` or the configured private audio disk separately from PostgreSQL.
- PostgreSQL backups include audio metadata only, not the actual audio files.
- Do not expose direct public storage URLs for learner voice recordings.
- Production servers should protect private storage with filesystem permissions and encrypted backups.

## OpenAI / LLM Configuration

- Production deployments must use a client-owned or government-owned OpenAI API key.
- Store the key only in the production server `.env`.
- Never commit API keys, expose them to frontend code, or store them in the database.
- The Coach + Feedback Agent may use OpenAI for feedback wording only.
- Assessment scoring, reading classification, module placement, and mastery decisions remain rule-based.
- If OpenAI is disabled or unavailable, ReaDirect falls back to template feedback.

## Security Notes

- Keep PostgreSQL credentials private.
- Run migrations on the target server.
- Store learner audio in private storage.
- Do not expose raw database backups.
- Encrypt production backups.

## Admin And Testing Mode

- Phase 11 adds `/admin/*` routes for system administration and QA.
- Only authorized users should receive `system_admin` or `school_admin` roles.
- Testing / QA Mode creates sandbox attempts and debug views for authorized admins only.
- Sandbox attempts are excluded from teacher-facing reports by default.
