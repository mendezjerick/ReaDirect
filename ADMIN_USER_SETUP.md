# Create The First Admin User

Use this when you need access to `/admin/dashboard`.

## 1. Confirm the database is configured

Check `.env` and make sure the database values are correct. Then clear cached config:

```powershell
php artisan config:clear
```

## 2. Run migrations and seed roles

```powershell
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

## 3. Create or update a system admin

Recommended:

```powershell
php artisan readirect:create-admin admin@readirect.test --name="System Admin" --password="password"
```

For a real deployment, use a strong password:

```powershell
php artisan readirect:create-admin admin@example.gov --name="System Admin"
```

If `--password` is omitted, the command prompts for one.

## 4. Log in

Open:

```text
http://127.0.0.1:8000/login
```

Use the email and password from the command.

## 5. Open admin

```text
http://127.0.0.1:8000/admin/dashboard
```

## Troubleshooting

If `/login` redirects to another dashboard, you are already logged in. Log out first:

```text
http://127.0.0.1:8000
```

Then use the app logout button if visible, or clear browser cookies for `127.0.0.1`.

If the admin page returns `403`, the user is not a `system_admin`. Re-run:

```powershell
php artisan readirect:create-admin admin@readirect.test --name="System Admin" --password="password"
```

If PostgreSQL says `could not find driver`, enable the PHP `pdo_pgsql` extension for the PHP version used by `php artisan`.
