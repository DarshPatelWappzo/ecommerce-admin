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

## Tenant provisioning

Each registered central domain receives its own MySQL database named `tenant_{centralUserId}_{domainId}`. Tenant databases contain independent users, roles, permissions, and role pivots. Package, subscription, domain, and audit data remain in the central database.

Tenant provisioning commands:

```bash
php artisan tenant:migrate {user_id}
php artisan tenant:seed {user_id}
php artisan tenant:retry-provision {user_id}
php artisan tenant:list
```

Tenant administrators receive a temporary password by email and must change it on first login before accessing tenant application routes. Configure SMTP values in `.env`; the default `log` mailer is suitable only for local inspection.

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

### Customer management

Customers and addresses live in each tenant database, independently from platform and tenant admin users. The admin sidebar links to the customer list, profile and address forms. India is the currently supported address country; state choices are configured in `config/customer_locations.php`.

Deploy the additive schema and permission migrations with:

```bash
php artisan tenant:migrate-all --no-interaction
```

New tenant provisioning already runs this migration directory and the updated tenant seeder. Existing Admin roles receive the new permissions during migration; no reseeding is required. Grant `customers.view`, `customers.create`, `customers.update`, `customers.delete`, and `customers.addresses` to other roles as needed. Initial-address API creation requires both create and address-management permissions.

The protected APIs use the existing `/api/tenant` prefix and bearer token authentication:

| Method | Path | Purpose |
| --- | --- | --- |
| GET / POST | `/api/tenant/customers` | List / create |
| GET / PUT / PATCH / DELETE | `/api/tenant/customers/{customer}` | Show / update / soft delete |
| PATCH | `/api/tenant/customers/{customer}/status` | Activate/deactivate |
| GET / POST | `/api/tenant/customers/{customer}/addresses` | List / create addresses |
| PUT / PATCH / DELETE | `/api/tenant/customers/{customer}/addresses/{address}` | Update / delete an owned address |
| PATCH | `/api/tenant/customers/{customer}/addresses/{address}/default` | Change shipping/billing defaults |

Create example (`Content-Type: application/json`, `Accept: application/json`, `Authorization: Bearer <token>`):

```http
POST /api/tenant/customers

{
  "first_name": "Asha",
  "email": "asha@example.com",
  "customer_type": "business",
  "company_name": "Example Trading",
  "gstin": null,
  "address": {
    "recipient_name": "Asha",
    "phone_country_code": "+91",
    "phone": "9876543210",
    "address_line_1": "12 Market Road",
    "city": "Ahmedabad",
    "state_code": "GJ",
    "country_code": "IN",
    "postal_code": "380001"
  }
}
```

The `address` object is optional. On admin pages, add addresses from the customer details page after saving. Creates return 201; updates, deletion, and list/detail requests return 200. Validation errors use Laravel's 422 `message`/`errors` response. Missing records and addresses owned by another customer return 404; missing permissions return 403.

List filters: `search`, `status`, `customer_type`, `joined_from`, `joined_to` (YYYY-MM-DD), `per_page` (1–100), `sort` (`created_at`, `customer_code`, `first_name`), and `direction` (`asc`, `desc`). Responses retain the project's top-level paginator format; single records are under `data`, with `message` on writes.

Example partial updates:

```http
PATCH /api/tenant/customers/123

{"notes": null}
```

```http
PATCH /api/tenant/customers/123/addresses/456/default

{"is_default_shipping": true, "is_default_billing": true}
```

Omitted fields are preserved; explicit null clears nullable fields. At least one contact must remain. Email is lowercased and reserved even after soft deletion. Phone formatting removes spaces, parentheses, dots and hyphens; calling codes are stored with `+`. Phone numbers are not unique because there is no customer login identifier in this application. Codes use ULIDs with a unique index. GSTIN is uppercased and checked for format only, without registration verification or uniqueness enforcement. Explicitly switching to Individual clears business name and GSTIN.

The first address becomes both defaults. Later explicit flags change defaults under a transaction and parent-customer row lock. Deleting a default selects the oldest remaining address as replacement. Internal notes appear only in authorized admin pages/API resources; audit logs contain changed field names, status/type, record identifiers and default changes, not contact, address, GSTIN or note values.

There is currently no customer login, order, invoice, payment, refund or self-service module. Future order integration should use nullable `customer_id` for guest checkout and immutable purchase-time customer/business/GSTIN/address snapshots. Customer/address updates must never rewrite those snapshots; historical orders should survive customer soft deletion. Spending summaries must follow the eventual paid/refunded status rules and remain separated by currency.

### Product tax assignment API

Tenant product endpoints require a tenant bearer token and the existing `products.create`, `products.update`, or `products.view` permission for the action.

- `GET /api/tenant/products/create` includes `taxes`: active, non-deleted choices. The edit endpoint (`GET /api/tenant/products/{product}/edit`) also includes the current tax even if unavailable.
- `POST /api/tenant/products` accepts optional `tax_id` alongside the existing product payload. Omitted or null means tax is not configured.
- `PUT`/`PATCH /api/tenant/products/{product}` accepts `tax_id`. Omit it to preserve the assignment, send null to clear it, or send an available tenant tax ID to change it. Existing inactive/deleted assignments may be retained. Invalid or unavailable new assignments return normal 422 field errors.
- Product list, detail, create and update responses retain their envelopes and include `tax_id` and nullable `tax`. A configured tax exposes `id`, `name`, `code`, `rate` (four-decimal string, e.g. `"18.1234"`), `is_active`, and `is_available`. A soft-deleted or inactive tax has `is_available: false`. Null is distinct from an explicitly assigned zero-rate tax.

Apply the additive tenant migration with `php artisan tenant:migrate-all --no-interaction`. New tenant provisioning runs the same tenant migration directory automatically. Existing products retain null tax assignments. This feature does not change pricing, checkout, or historical order/invoice tax values.

- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md): application behavior and architecture.
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md): tables, fields, relationships, and data flow.
