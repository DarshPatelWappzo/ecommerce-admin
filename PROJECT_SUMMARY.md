# Project Summary

## Purpose

This application is a Laravel 13.29 administration panel for a software/e-commerce platform. It currently provides a super-admin area for managing regular users, their domains, infrastructure packages, and historical audit records.

The application uses PHP 8.3, Eloquent ORM, Blade templates, Bootstrap 5, and SQLite by default. Tenant provisioning uses MySQL databases generated per central domain as `tenant_{centralUserId}_{domainId}`. Tenant users are separate from central users, receive the Admin role, and must change their temporary password on first login.


## Authentication and authorization

Authentication uses Laravel's `web` session guard and the `users` table. A user is treated as a super admin when `users.is_super_admin` is `true`.

The `EnsureSuperAdmin` middleware aborts with HTTP 403 unless the authenticated user is a super admin. Dashboard, user-management, package-management, and audit-log web routes use both `auth` and `EnsureSuperAdmin`.

The default database seeder creates:

- Email: `superadmin@gmail.com`
- Password: `Superadmin@123`
- Super-admin flag: `true`

## Request flow

### Super-admin web flow

1. The browser opens a route under `/super-admin`.
2. Laravel authenticates the session with the `web` guard.
3. `EnsureSuperAdmin` checks the `is_super_admin` flag.
4. A controller receives a typed Form Request.
5. The Form Request validates and normalizes input.
6. A repository persists or queries Eloquent models.
7. The controller returns a Blade view, redirect, or JSON response when requested.

User creation also creates the submitted domains inside a database transaction.

### User API flow

`POST /api/users` uses `SuperAdminUserStoreRequest`, creates a regular user with a generated password, creates the user's domains, and returns a JSON response with the public user fields and domain names.

The endpoint uses the existing `auth` and `EnsureSuperAdmin` middleware. The project does not currently install a separate API-token package, so API clients must use the application's authenticated session unless a token-based guard is added later.

## Application areas

### Dashboard

The dashboard is the super-admin landing page and provides navigation to the administration modules.

### Users

The Users module manages regular users. It supports:

- Paginated listing and search.
- Create and edit forms.
- First name, last name, email, mobile number, status, and domains.
- Soft deletion at the model/schema level.

Super-admin users are excluded from the regular user listing.

### Packages

Packages represent infrastructure plans. Each package contains pricing, user-capacity limits, compute resources, storage/bandwidth, supported features, billing period, status, and descriptive text.

Package creation and editing use a shared Blade form. The slug is generated from the package name in the browser. Package queries are handled by `PackageRepository` and are paginated.

### User package assignments

The `user_packages` table connects users to packages and records the assignment start date, optional end date, and status. The model relationships exist, while the dedicated admin controller is currently scaffolded for future assignment-management screens.

### User domains

The `user_domains` table stores domains belonging to users. Domains are normalized to lowercase and trimmed before validation. Domain creation and synchronization are handled by `UserDomainRepository` during user management.

### Audit logs

Packages, users, and user domains use the reusable `App\Traits\Auditable` trait. Model events create audit records automatically for `created`, `updated`, and `deleted` events through `AuditLogService`. Domain changes are grouped under the `users` module and use the `domain` audit field.

Audit behavior:

- Create records store the new values.
- Update records store only changed fields.
- Delete records store the values before deletion.
- IDs, passwords, remember tokens, timestamps, and soft-delete timestamps are excluded.
- Audit logs are not deleted when a package is soft-deleted.

The read-only audit page is available at `/super-admin/audit-logs`. It uses server-side pagination and displays an eye-button modal containing field-level old/new values for package and domain changes.

To audit another Eloquent model, add the trait:

```php
use App\Traits\Auditable;

class Product extends Model
{
    use Auditable;
}
```

## Code organization

- `app/Http/Controllers`: request orchestration and responses.
- `app/Http/Requests`: validation and input normalization.
- `app/Models`: Eloquent models and relationships.
- `app/Repositories`: persistence/query logic for users, domains, and packages.
- `app/Services`: reusable application services such as audit logging.
- `app/Traits`: reusable model behavior.
- `resources/views`: Blade layouts and super-admin screens.
- `database/migrations`: schema history.
- `database/factories`: test data factories.
- `tests/Feature`: request, authorization, schema, and workflow tests.

## Frontend conventions

The UI is server-rendered Blade with Bootstrap classes and a shared layout/sidebar. Small interactions use vanilla JavaScript, including AJAX form submission, pagination behavior, slug generation, and Bootstrap modals. No Vue, React, Livewire, or Inertia layer is installed.
