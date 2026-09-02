# E-commerce Admin

Laravel-based administration panel for managing users, domains, infrastructure packages, and audit history.

## Requirements

- PHP 8.3 or newer
- Composer
- SQLite (default) or another Laravel-supported database
- Node.js is optional; the application currently uses CDN-hosted Bootstrap and Font Awesome assets.

## Installation

From the project directory:

```bash
composer install
```

Create the environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell, use:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

The default configuration uses SQLite. Create the database file if it does not exist:

```bash
touch database/database.sqlite
```

On Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
```

Run migrations and seed the default super admin:

```bash
php artisan migrate --seed
```

### MySQL note

The default database is SQLite, but MySQL is also supported. The jobs migration limits the indexed `failed_jobs.connection` and `failed_jobs.queue` columns to 100 characters so their composite index stays within MySQL's 1000-byte key limit.

If a migration previously failed after creating partial tables, rebuild the local database and seed it again:

```bash
php artisan migrate:fresh --seed
```

This command deletes existing database data, so use it only when a reset is acceptable.

## Run locally

Start the Laravel development server:

```bash
php artisan serve
```

Open `http://127.0.0.1:8000`.

Default seeded login:

- Email: `superadmin@gmail.com`
- Password: `Superadmin@123`

Change the seeded password before using the application outside local development.

## Useful commands

```bash
php artisan route:list
php artisan migrate:status
php artisan test --compact
vendor/bin/pint --format agent
php artisan view:clear
```

## Main routes

| Purpose | Route |
| --- | --- |
| Super-admin login | `GET /super-admin/login` |
| Dashboard | `GET /super-admin/dashboard` |
| Users | `GET /super-admin/admin` |
| Packages | `GET /super-admin/package` |
| Audit logs | `GET /super-admin/audit-logs` |
| Create user API | `POST /api/users` |

Super-admin web routes and the user-creation API use the `auth` and `EnsureSuperAdmin` middleware.

## Create user API

The endpoint accepts JSON with the same fields as the super-admin user form:

```json
{
  "first_name": "Ada",
  "last_name": "Lovelace",
  "email": "ada@example.com",
  "mobile_number": "9876543210",
  "domains": ["example.com", "shop.example.com"],
  "status": "active"
}
```

It creates a regular user and associated domains in a transaction. The password is generated internally and is not returned in the response.

## Documentation

- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md): application behavior and architecture.
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md): tables, fields, relationships, and data flow.
