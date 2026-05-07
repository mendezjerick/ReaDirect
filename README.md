# ReaDirect

ReaDirect is a Laravel + Vue/Inertia Progressive Web Application for Grade 1 oral reading assessment and guided reading practice. It includes learner diagnostic assessment, reading comprehension, rule-based module placement, learning modules, module mastery decisions, browser audio recording, transcript/STT support, OpenAI-backed coach feedback with template fallback, Web Speech API TTS agent voices, teacher dashboards and reports, final reassessment, and admin/QA testing tools.

## Tech Stack

- Backend: Laravel 13, PHP 8.3+
- Frontend: Vue 3 + Inertia.js
- Styling: Tailwind CSS 4
- Build tool: Vite
- Database: PostgreSQL
- Roles and permissions: Spatie Laravel Permission
- Auth/API support: Laravel Sanctum
- Audio recording: browser MediaRecorder API
- TTS: browser Web Speech API
- STT: `mock` provider by default, optional local `whisper_cpp` provider through `config/stt.php`
- LLM feedback: OpenAI API integration through server-side Laravel HTTP calls, with template fallback
- Package managers: Composer and npm

## Required Software

Windows + VS Code is the main local development target, but the commands are standard Laravel commands.

- Git
- PHP 8.3 or newer
- Composer
- Node.js 20+ recommended
- npm
- PostgreSQL
- pgAdmin, optional
- PostgreSQL command-line tools / `psql`, recommended
- VS Code

## PHP Setup Requirements

Laravel and ReaDirect need the usual PHP extensions. Confirm these are enabled:

- `openssl`
- `pdo`
- `pdo_pgsql`
- `pgsql`
- `mbstring`
- `tokenizer`
- `xml`
- `ctype`
- `json`
- `bcmath`
- `fileinfo`
- `zip`
- `curl`

### Required php.ini Changes

On Windows, `php.ini` is inside the installed PHP folder. Enable missing extensions by removing the semicolon at the start of the line:

```ini
extension=pdo_pgsql
extension=pgsql
extension=fileinfo
extension=zip
extension=curl
```

After editing `php.ini`, restart PowerShell and VS Code.

Verify:

```powershell
php -v
php -m
composer -V
```

## PostgreSQL Setup

PostgreSQL is required for local development. Each collaborator should create their own local database. Do not share a live database through GitHub. GitHub contains migrations, seeders, CSV seed data, and the content-bank export ZIP.

### Option A: pgAdmin

1. Open pgAdmin.
2. Create a database named `readirect`.
3. Use `postgres` or your own PostgreSQL user.
4. Remember the password for `.env`.

### Option B: PowerShell / psql

Make sure the PostgreSQL `bin` folder is in PATH.

```powershell
createdb -U postgres readirect
```

Or:

```powershell
psql -U postgres
CREATE DATABASE readirect;
```

Common PostgreSQL issues:

- `psql is not recognized`: add a path like `C:\Program Files\PostgreSQL\18\bin` to PATH, then restart PowerShell.
- `database does not exist`: create `readirect`.
- `password authentication failed`: check `DB_USERNAME` and `DB_PASSWORD`.
- `connection refused`: confirm PostgreSQL service is running.
- Port conflict: check whether PostgreSQL is using `5432`.

## Clone and Install

```powershell
git clone https://github.com/mendezjerick/ReaDirect.git
cd ReaDirect

composer install
npm install
```

## Environment Setup

Copy the example environment file and generate an app key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

`.env` is local only. Never commit it.

## Required .env Configuration

Important values in `.env.example`:

```ini
APP_NAME=ReaDirect
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=readirect
DB_USERNAME=postgres
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

OPENAI_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT_SECONDS=30

STT_PROVIDER=mock
STT_WHISPER_CPP_ENABLED=false
STT_WHISPER_CPP_BINARY_PATH=whisper-cli
STT_WHISPER_CPP_MODEL_PATH=
STT_TIMEOUT_SECONDS=30

READIRECT_AI_ENABLED=true
READIRECT_AI_BASE_URL=http://127.0.0.1:8001
READIRECT_AI_API_TOKEN=
READIRECT_AI_TIMEOUT_SECONDS=60
```

Database variables connect Laravel to PostgreSQL.

OpenAI variables are server-side only. Keep `OPENAI_ENABLED=false` unless you are testing real LLM feedback. Put real API keys only in your private `.env`.

STT variables control transcript generation. Use `STT_PROVIDER=mock` for normal setup. Use `whisper_cpp` only when the local binary and model path are configured.

ReaDirect AI variables connect Laravel to the separate `ReaDirect-AI-ASR` FastAPI service. The Wav2Vec2-only ASR runtime and fine-tuned Wav2Vec2 letters-v2 model run in that separate service; this Laravel repo only calls it over HTTP and stores advisory analysis signals. Start it from the AI repo with:

```powershell
uvicorn api.main:app --reload --host 127.0.0.1 --port 8001
```

The `SEED_ADMIN_*` values in `.env.example` are safe local reference values. The current project creates admins through an Artisan command, not an automatic admin seeder.

## Database Migration and Seeding

Migrations create tables. Seeders load default roles, demo users, agents, rules, modules, and CSV seed content.

Fresh local setup:

```powershell
php artisan migrate:fresh --seed
```

Existing local database:

```powershell
php artisan migrate
php artisan db:seed
```

Warning: `migrate:fresh` deletes local tables and data. Use it only for local setup or reset, never production.

## Admin Account

Seeders create demo teacher/learner data, but the system admin is created with an Artisan command:

```powershell
php artisan readirect:create-admin admin@readirect.local --name="ReaDirect Admin" --password="ChangeThisPassword123!"
```

Then open `/login`.

Example local admin:

```text
Email: admin@readirect.local
Password: ChangeThisPassword123!
```

Change local passwords as needed. Never use weak or shared passwords in production.

Demo teacher from seeders:

```text
Email: teacher@example.com
Password: password
```

Demo learner code:

```text
RD-1001
```

## Running the Full System Locally

For the normal local development system, run the AI service, Laravel app server, Vite dev server, and queue worker. Laravel is the URL you open in the browser; Vite only serves frontend assets.

From the folder that contains both repositories:

```powershell
cd C:\path\to\holder-ReaDirect
```

Terminal 1 - ReaDirect AI/ASR service:

```powershell
cd ReaDirect-AI-ASR
python scripts\validate_ai_service_startup.py
powershell -ExecutionPolicy Bypass -File scripts/start_ai_service_dev.ps1
```

The AI service should be available at:

```text
http://127.0.0.1:8001/health
```

Terminal 2 - Laravel backend:

```powershell
cd ReaDirect
php artisan migrate
php artisan serve
```

Terminal 3 - Vite frontend assets:

```powershell
cd ReaDirect
npm run dev
```

Terminal 4 - queue worker:

```powershell
cd ReaDirect
php artisan queue:listen --tries=1 --timeout=0
```

Open the app in the browser:

```text
http://localhost:8000
```

Vite runs on `http://127.0.0.1:5173` by default, but users should open the Laravel URL above.

Quick health checks:

```powershell
Invoke-WebRequest http://127.0.0.1:8001/health -UseBasicParsing
Invoke-WebRequest http://127.0.0.1:8000 -UseBasicParsing
```

Expected local ports:

```text
Laravel: http://127.0.0.1:8000
AI ASR:  http://127.0.0.1:8001
Vite:    http://127.0.0.1:5173
```

If you are not testing the AI service, set `READIRECT_AI_ENABLED=false` in `.env` and run only Laravel plus Vite.

Build check:

```powershell
npm run build
```

Composer also includes a combined Laravel/Vite/queue/logs script. Run the AI service separately first if `READIRECT_AI_ENABLED=true`:

```powershell
composer run dev
```

## Storage and Audio Files

Run the storage link once for public storage assets:

```powershell
php artisan storage:link
```

Learner audio is stored through Laravel storage. Private audio is served through authorized routes and may not be exposed directly by `storage:link`. Do not commit uploaded audio files.

## Queue / Jobs

Local `.env.example` uses:

```ini
QUEUE_CONNECTION=database
```

If you use queued work locally, run:

```powershell
php artisan queue:work
```

For basic local testing, many flows still run synchronously through normal requests.

## Running Tests

```powershell
php artisan test
npm run build
```

There is no separate frontend test command currently.

## Common Development Commands

```powershell
composer install
npm install
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:fresh --seed
php artisan test
npm run dev
npm run build
```

## CSV / Content Bank Overview

Current source seed content lives in:

```text
database/seed-data/readirect/
```

These CSV files are item banks and seed content for assessment content, module activities, rules, feedback templates, and agent scripts. Seeders import them into PostgreSQL.

Prefer editing CSVs and seeders for shared content changes. Do not edit production databases directly unless explicitly instructed. Never put real learner data, API keys, database dumps, or audio files in content files.

## ZIP-Based Content Bank Workflow

The content-bank workflow lets collaborators enrich CSVs outside the app and return reviewed edits safely.

```text
content-bank/
├── export/
│   ├── README.md
│   └── readirect-content-bank-export.zip
├── import/
│   ├── .gitkeep
│   └── README.md
├── work/
│   └── .gitkeep
└── README.md
```

### Folders

`content-bank/export/`

- Contains the Git-tracked export ZIP: `readirect-content-bank-export.zip`.
- The ZIP contains organized editable CSV/content-bank files.
- It is safe to commit when it contains only seed content and documentation.

`content-bank/import/`

- Local-only drop zone for edited ZIPs.
- Contents are ignored by Git except `.gitkeep` and `README.md`.
- Recommended import ZIP name: `readirect-content-bank-import.zip`.

`content-bank/work/`

- Temporary extraction/staging folder.
- Contents are ignored by Git except `.gitkeep`.

`database/seed-data/readirect/`

- Still the source used by Laravel seeders.

### Export Current Content

```powershell
powershell -ExecutionPolicy Bypass -File scripts/export-content-bank.ps1
```

Expected output:

```text
content-bank/export/readirect-content-bank-export.zip
```

### Edit Content

1. Copy or download `content-bank/export/readirect-content-bank-export.zip`.
2. Extract it outside the app or into a safe working folder.
3. Edit/enrich CSVs.
4. Keep CSV headers unchanged unless importers are updated.
5. Re-zip the edited folder.
6. Name it `readirect-content-bank-import.zip`.
7. Place it in `content-bank/import/`.

### Import Edited ZIP

Dry run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -DryRun
```

Actual import:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/import-content-bank.ps1 -ZipPath "content-bank/import/readirect-content-bank-import.zip" -Force
```

Then rerun seeders as appropriate:

```powershell
php artisan db:seed
```

Or reset local data:

```powershell
php artisan migrate:fresh --seed
```

After importing, changed files in `database/seed-data/readirect/` are the source files to review and commit. Do not commit import ZIPs, work-folder files, real learner data, audio/video files, API keys, or database dumps.

## Git Workflow for Collaborators

All collaborators must work on their own branches. Do not push directly to `main`. `main` should be updated only through reviewed Pull Requests.

```powershell
git checkout main
git pull origin main
git checkout -b feature/your-task-name
```

After making changes:

```powershell
git status
git add .
git commit -m "Describe your change clearly"
git push origin feature/your-task-name
```

Then create a Pull Request on GitHub.

Rules:

- Keep `main` stable.
- Use one branch per task.
- Pull latest `main` before starting new work.
- Coordinate before multiple people edit the same CSV files.
- Never commit `.env`, API keys, database dumps, real learner audio, private credentials, import ZIPs, or work-folder contents.

Example branch names:

- `feature/module-2-content`
- `feature/admin-filter-fix`
- `feature/stt-debug-panel`
- `fix/login-redirect`
- `docs/setup-guide`
- `content/enrich-reading-passages`

## Files That Must Not Be Committed

- `.env`
- `vendor/`
- `node_modules/`
- storage logs
- database dumps: `*.sql`, `*.dump`, `*.backup`
- real learner audio files
- API keys
- personal PostgreSQL passwords
- model checkpoints
- `content-bank/import/*` except `.gitkeep` and `README.md`
- `content-bank/work/*` except `.gitkeep`

The tracked export ZIP is allowed:

```text
content-bank/export/readirect-content-bank-export.zip
```

## Troubleshooting

### composer install fails

Check PHP version and extensions. Enable missing extensions, restart PowerShell, and rerun `composer install`.

### pdo_pgsql error

Enable these in `php.ini`:

```ini
extension=pdo_pgsql
extension=pgsql
```

Restart the terminal.

### fileinfo missing

Enable:

```ini
extension=fileinfo
```

### zip missing

Enable:

```ini
extension=zip
```

### npm install fails

Check Node.js version. If needed, remove `node_modules` locally and run `npm install` again.

### Database connection failed

Check that PostgreSQL is running and that `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` are correct.

### psql not recognized

Add PostgreSQL `bin` to PATH, for example:

```text
C:\Program Files\PostgreSQL\18\bin
```

Restart PowerShell.

### Vite page not loading

Run `npm run dev`, confirm `APP_URL=http://localhost:8000`, and refresh the browser.

### Admin login not working

Run migrations/seeders and create the admin:

```powershell
php artisan migrate:fresh --seed
php artisan readirect:create-admin admin@readirect.local --name="ReaDirect Admin" --password="ChangeThisPassword123!"
```

### OpenAI / LLM not working

Keep `OPENAI_ENABLED=false` for fallback. Add a real key only to private `.env` when testing LLM. Then run:

```powershell
php artisan config:clear
```

### STT not working

Use `STT_PROVIDER=mock` unless local Whisper.cpp is configured. For `whisper_cpp`, check binary path, model path, and timeout. Manual transcript fallback remains available.

### Content-bank import ZIP appears in git status

Confirm `.gitignore` has `content-bank/import/*`. Check:

```powershell
git check-ignore -v content-bank/import/readirect-content-bank-import.zip
```

### Export ZIP missing

Run:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/export-content-bank.ps1
```

## Project Folder Guide

- `app/Services` - scoring, module, STT, LLM, admin, and reporting services
- `app/Http/Controllers` - Laravel request controllers
- `resources/js/Pages` - Inertia page components
- `resources/js/Components` - reusable Vue components
- `database/migrations` - database schema changes
- `database/seeders` - database seeders
- `database/seed-data/readirect` - CSV seed source content
- `content-bank/export` - Git-tracked content export ZIP and organized export files
- `content-bank/import` - ignored local import drop zone
- `content-bank/work` - ignored temporary extraction folder
- `public/assets/agents` - fixed agent visual assets
- `storage` - local generated files, logs, and private uploads
- `config` - Laravel and ReaDirect configuration
- `scripts` - PowerShell helper scripts

## Deeper Documentation

- `DATABASE_SETUP.md`
- `DEPLOYMENT_NOTES.md`
- `STT_INTEGRATION.md`
- `LLM_INTEGRATION.md`
- `docs/ai-service/LARAVEL_AI_INTEGRATION.md`
- `docs/ai-service/AI_ASR_EXTERNAL_FILES_GUIDE.md`
- `docs/ai-service/FINAL_HANDOFF_CHECKLIST.md`
- `docs/ai-service/LARAVEL_INTEGRATION_CONTRACT.md`
- `docs/ai-service/API_EXAMPLES.md`
- `ADMIN_DASHBOARD.md`
- `ADMIN_TESTING_MODE.md`
- `ADMIN_FILTERS.md`
- `content-bank/README.md`

## Local Setup Checklist

- [ ] Git cloned
- [ ] Branch created
- [ ] PHP installed
- [ ] Composer installed
- [ ] Node/npm installed
- [ ] PostgreSQL database created
- [ ] `.env` created
- [ ] `APP_KEY` generated
- [ ] `composer install` done
- [ ] `npm install` done
- [ ] migrations/seeders run
- [ ] admin account created
- [ ] `php artisan serve` running
- [ ] `npm run dev` running
- [ ] admin login works
- [ ] content-bank workflow understood
