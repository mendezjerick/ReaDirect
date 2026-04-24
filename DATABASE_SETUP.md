# ReaDirect Database Setup

ReaDirect targets PostgreSQL for Phase 4 and later. SQLite was used for early local development only.

## Confirm PostgreSQL Tools

In PowerShell:

```powershell
psql --version
```

If `psql` is not recognized, add the PostgreSQL `bin` directory to your Windows PATH. A common path is:

```text
C:\Program Files\PostgreSQL\18\bin
```

Close and reopen PowerShell or VS Code after changing PATH.

## Create The Local Database

Create the main local database:

```powershell
createdb -U postgres readirect
```

If you prefer the interactive shell:

```powershell
psql -U postgres
```

```sql
CREATE DATABASE readirect;
\q
```

For tests, create a separate database so `RefreshDatabase` does not wipe development data:

```powershell
createdb -U postgres readirect_test
```

## Configure Laravel

Set these values in your private `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=readirect
DB_USERNAME=postgres
DB_PASSWORD=your_local_password
```

Never commit `.env`.

Clear cached config after changing database settings:

```powershell
php artisan config:clear
php artisan cache:clear
```

## Run Migrations And Seeders

This creates the full schema and development seed content:

```powershell
php artisan migrate:fresh --seed
```

## Run Tests

`phpunit.xml` targets `readirect_test` on PostgreSQL. It still reads private credentials from your local environment.

```powershell
php artisan test
```

## Build Frontend Assets

```powershell
npm run build
```

## Troubleshooting

### `psql` is not recognized

Add the PostgreSQL `bin` directory to PATH, then restart PowerShell or VS Code.

### Password authentication failed

Check `DB_USERNAME` and `DB_PASSWORD` in `.env`. Then run:

```powershell
php artisan config:clear
```

### Database does not exist

Create it:

```powershell
createdb -U postgres readirect
```

For tests:

```powershell
createdb -U postgres readirect_test
```

### Could not connect to server

Confirm PostgreSQL is running and listening on port `5432`. Also check:

```env
DB_HOST=127.0.0.1
DB_PORT=5432
```

### Migration foreign key error

Run a fresh migration during local setup:

```powershell
php artisan migrate:fresh --seed
```

If this is a shared or production database, do not use `migrate:fresh`.

### Config cache issue

Clear Laravel config:

```powershell
php artisan config:clear
php artisan cache:clear
```
