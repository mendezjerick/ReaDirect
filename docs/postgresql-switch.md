# Switching ReaDirect From SQLite to PostgreSQL

Phase 1 uses SQLite for fast local migrations, seeders, and tests. The migrations avoid PostgreSQL-only column types so the same schema can move to PostgreSQL later.

Phase 4 uses PostgreSQL as the active compatibility target. See the root-level `DATABASE_SETUP.md`, `BACKUP_AND_RESTORE.md`, and `DEPLOYMENT_NOTES.md` files for the maintained setup, backup, and deployment notes.

To switch:

1. Create a PostgreSQL database and user.
2. Copy the PostgreSQL settings from `.env.example` into `.env`.
3. Set `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
4. Run `php artisan migrate:fresh --seed` against the PostgreSQL database.

Official scoring and recommendation decisions remain rule-based and audited in the same tables.
