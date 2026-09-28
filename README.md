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

Customers do not have login or self-service routes. Tenant staff orders use nullable `customer_id` for guests and immutable submitted customer/business/GSTIN/address snapshots. Customer/address edits do not rewrite those snapshots; orders survive customer removal. There is no refund or invoice workflow in this release.

### Product tax assignment API

Tenant product endpoints require a tenant bearer token and the existing `products.create`, `products.update`, or `products.view` permission for the action.

- `GET /api/tenant/products/create` includes `taxes`: active, non-deleted choices. The edit endpoint (`GET /api/tenant/products/{product}/edit`) also includes the current tax even if unavailable.
- `POST /api/tenant/products` accepts optional `tax_id` alongside the existing product payload. Omitted or null means tax is not configured.
- `PUT`/`PATCH /api/tenant/products/{product}` accepts `tax_id`. Omit it to preserve the assignment, send null to clear it, or send an available tenant tax ID to change it. Existing inactive/deleted assignments may be retained. Invalid or unavailable new assignments return normal 422 field errors.
- Product list, detail, create and update responses retain their envelopes and include `tax_id` and nullable `tax`. A configured tax exposes `id`, `name`, `code`, `rate` (four-decimal string, e.g. `"18.1234"`), `is_active`, and `is_available`. A soft-deleted or inactive tax has `is_available: false`. Null is distinct from an explicitly assigned zero-rate tax.

Apply the additive tenant migration with `php artisan tenant:migrate-all --no-interaction`. New tenant provisioning runs the same tenant migration directory automatically. Existing products retain null tax assignments. This feature does not change pricing, checkout, or historical order/invoice tax values.

- [PROJECT_SUMMARY.md](PROJECT_SUMMARY.md): application behavior and architecture.
- [DATABASE_SCHEMA.md](DATABASE_SCHEMA.md): tables, fields, relationships, and data flow.

### Tenant order management

Apply the additive migrations to existing tenants:

```sh
php artisan tenant:migrate-all --no-interaction
```

New tenants receive the same migrations through the existing provisioning flow and `TenantDatabaseSeeder`. No central order tables are added. The preparation migration adds nullable product `hsn_code` and converts existing MySQL/MariaDB `users`, `customers`, `products`, `product_variants`, `taxes`, and `audit_logs` tables to InnoDB if needed. This preserves rows and enables transactions, row locks and order foreign keys. Engine conversion can lock large tables; schedule this migration during a maintenance window with a database backup. It is intentionally not reversed on rollback. The next migrations create the order tables, stock movement ledger and durable idempotency records, and grant order permissions to existing Admin roles.

Permissions: `orders.view`, `orders.create`, `orders.update`, `orders.confirm`, `orders.process`, `orders.cancel`, `orders.payments`, `orders.ship`, `orders.deliver`, `orders.price_override`, `orders.discount`. View and update reuse existing permissions; the remaining nine are new. Other roles must receive the appropriate permissions through Roles. Admin browser routes are under `/tenant/orders`; bearer-authenticated staff APIs follow the existing `/api/tenant` convention.

| Method | Path | Behavior |
| --- | --- | --- |
| GET / POST | `/api/tenant/orders` | Paginated list / create |
| GET / PATCH | `/api/tenant/orders/{order}` | Details / draft-only edits |
| GET | `/api/tenant/orders/options?kind=customers&search=Asha` | Active customer choices and address prefills |
| GET | `/api/tenant/orders/options?kind=variants&search=shirt` | Active variant choices, SKU, price and available stock |
| GET | `/api/tenant/orders/options?kind=taxes&search=GST` | Active shipping-tax choices with precise rates |
| POST | `/api/tenant/orders/preview` | Shared authoritative calculation, no writes |
| POST | `/api/tenant/orders/{order}/confirm` | Confirm and reserve stock |
| POST | `/api/tenant/orders/{order}/process` | Begin processing |
| POST | `/api/tenant/orders/{order}/cancel` | Cancel before shipment; requires `comment` |
| POST | `/api/tenant/orders/{order}/payments` | Record an offline/COD receipt |
| POST | `/api/tenant/orders/{order}/shipments` | Ship and deduct stock once |
| POST | `/api/tenant/orders/{order}/deliver` | Mark delivered without another stock deduction |

List filters: `search` (number, customer name/email), `from`, `to` (YYYY-MM-DD), `status`, `payment_status`, `payment_method`, and `per_page` (1–100). Filters are preserved in pagination; payment filtering uses existence queries to avoid duplicate orders. List responses use the existing top-level paginator envelope; single records use `data`, with `message` on writes. Amounts are two-decimal strings and tax rates four-decimal strings.

Send `Authorization: Bearer <tenant-token>`, `Accept: application/json`, and `Content-Type: application/json`. Create and payment requests require a unique `Idempotency-Key` header (or `idempotency_key` body field; the header takes precedence), containing up to 128 letters, digits, underscores or hyphens. Keys are durable, scoped to the tenant, staff member and operation. Identical retries return the original response, even if the order/catalog has since changed. Reusing a key for different normalized input returns 409; validation failures return 422 and do not consume the key. Use a new key for a new operation, retain it when retrying an uncertain network result.

Create example (replace IDs with the current tenant's IDs):

```http
POST /api/tenant/orders
Idempotency-Key: order-2026-0001

{
  "customer_id": null,
  "customer_name": "Asha",
  "customer_email": "asha@example.com",
  "currency": "INR",
  "billing": {
    "name": "Asha",
    "phone": "+91 9876543210",
    "address_line_1": "12 Market Road",
    "city": "Ahmedabad",
    "state_code": "GJ",
    "country_code": "IN",
    "postal_code": "380001"
  },
  "same_as_billing": true,
  "items": [{"product_id": 12, "product_variant_id": 34, "quantity": 2}],
  "shipping_amount": "50.00",
  "shipping_tax_id": 3,
  "payment_method": "cod",
  "submit_as": "draft"
}
```

A selected active customer supplies the authoritative contact/company/GSTIN snapshot. For guests, provide a name and email or phone. Addresses are copied, not linked; only India and configured states are supported. Omit `same_as_billing` or set false to supply a separate `shipping` address with the same fields. Optional `customer_note` and `internal_note` are staff-only. `submit_as` accepts draft (default), pending or confirmed; direct confirmation also requires `orders.confirm`. PATCH preserves omitted fields, replaces supplied item/address arrays, and is limited to drafts.

Preview accepts `items`, `shipping_amount` and `shipping_tax_id`; it returns items, totals and a `fingerprint`. Include that value as `expected_pricing_fingerprint` when creating/updating with `submit_as: confirmed` to reject changed totals. The admin form does this automatically. Confirming an existing draft separately also verifies its saved price/tax fingerprint; changed catalog details require reopening and saving the draft first.

Prices are tax-exclusive, in INR. Each item requires an active configured product tax (including an explicitly configured 0% tax); unavailable or unknown taxes are rejected, including drafts. Shipping greater than zero requires an active tax selected from Taxes. Shipping tax is included exactly once in `tax_total`. Tax uses decimal arithmetic and half-up rounding to two decimals per item and once for shipping; `rounding_adjustment` is `0.00`. CGST/SGST/IGST values remain null because jurisdiction/component rules are not configured. GSTIN never determines tax. Authorized item `unit_price` overrides and absolute `discount_amount` reductions are supported; discounts cannot exceed the line subtotal. The calculator's item-discount stage is the future coupon integration point; there are no coupon tables or redemption flows.

HSN is a nullable string of up to 20 characters on product add/edit and product APIs, preserved as text (including leading zeroes) and copied to order items. Subsequent catalog, tax and customer changes never rewrite submitted snapshots. Pending orders retain their submitted prices and cannot be edited; cancel/recreate if changes are needed. Confirmation rechecks active products/variants/taxes and available stock.

Lifecycle: draft → pending/confirmed/cancelled; pending → confirmed/cancelled; confirmed → processing/cancelled; processing → shipped/cancelled; shipped → delivered. Draft/pending do not reserve inventory. Confirmation reserves, shipment reduces on-hand and releases the reservation, pre-shipment cancellation releases once, and delivery does not change inventory. Order/product/variant locks and a unique stock-event ledger protect retries and stock contention. Product edits cannot reduce stock or reservations below order commitments; reserved variants cannot be removed.

Payment example:

```http
POST /api/tenant/orders/123/payments
Idempotency-Key: receipt-2026-0001

{"method":"bank_transfer","reference_number":"BANK-REF-123","amount":"100.00","currency":"INR"}
```

Supported receipt methods are `cod`, `cash`, and `bank_transfer`. References are required, trimmed, uppercased and unique per order/method. Only captured receipts count towards received/outstanding and paid/partially_paid status; selecting COD records a pending intent, not receipt of funds. The server rejects overpayment, payments against draft/cancelled orders, and client attempts to record online payments. It preserves separate receipt attempts. Captured funds block cancellation because no refund workflow exists. Gateway identity columns are reserved with a provider/account/payment unique index; no online capture endpoint or SaaS subscription reuse is introduced.

Other action examples:

```http
POST /api/tenant/orders/123/confirm
{}

POST /api/tenant/orders/123/process
{"comment":"Ready for dispatch"}

POST /api/tenant/orders/123/shipments
{"courier_name":"Example Courier","tracking_number":"TRACK-123","tracking_url":"https://example.com/track/TRACK-123"}

POST /api/tenant/orders/123/deliver
{"comment":"Delivered"}
```

Cancellation requires `{"comment":"Customer requested cancellation"}` on its own endpoint. No hard-delete endpoint is provided. No Postman collection exists in this repository; these examples document the API contract. Run focused verification with `php artisan test --compact tests/Feature/TenantOrderManagementTest.php`. Tests use isolated SQLite databases; they cover transactional rollback and sequential overselling/retry scenarios, but do not prove concurrent InnoDB locking. Verify simultaneous confirmations/receipts against an isolated MySQL/InnoDB staging database before deployment.

### Tenant coupons

Coupons are managed under the tenant Coupons sidebar. The list supports code/description search, status, discount type, and validity interval overlap filters (`from` / `to`, YYYY-MM-DD). Create, edit, detail, activate/deactivate and soft-delete actions use the existing Blade/Bootstrap layout and tenant authorization. Codes are normalized to uppercase and remain reserved after deletion. Disabled, scheduled and expired are distinct statuses; usage exhaustion is reported when applying a coupon.

Run `php artisan tenant:migrate-all --no-interaction` to install the two additive coupon migrations. They add `coupons`, three restriction pivots, `coupon_redemptions`, nullable coupon identity/snapshot fields and a zero-default coupon discount on existing orders, plus a zero-default coupon discount on order items. Existing order amounts remain unchanged. Legacy non-InnoDB categories are converted to InnoDB for foreign keys; that engine conversion remains after rollback. Provisioning installs the same migrations. Existing Admin roles and newly seeded Admin roles receive `coupons.view/create/update/delete`; other roles need explicit grants.

All APIs use the existing tenant **staff bearer token** and permission checks. There is no customer-login/storefront API in this project, so coupon order operations use the existing authorized order API.

| Method | Path | Behavior |
| --- | --- | --- |
| GET / POST | `/api/tenant/coupons` | Paginated list / create |
| GET / PUT / PATCH / DELETE | `/api/tenant/coupons/{coupon}` | Show / replace rules / soft delete |
| PATCH | `/api/tenant/coupons/{coupon}/status` | Set `is_active` explicitly |
| POST | `/api/tenant/orders/preview` | Validate coupon and return eligibility, priced lines, totals and fingerprint |
| PATCH | `/api/tenant/orders/{order}/coupon` | Apply `coupon_code` through the existing draft update flow |
| DELETE | `/api/tenant/orders/{order}/coupon` | Remove coupon and reprice draft |

Coupon create/update example (updates replace restriction selections; omitted arrays clear those restrictions):

```json
{
  "code": "SAVE10",
  "description": "Ten percent off eligible merchandise",
  "is_active": true,
  "discount_type": "percentage",
  "discount_value": "10.00",
  "maximum_discount": "500.00",
  "minimum_subtotal": "100.00",
  "starts_at": "2026-10-01 00:00:00",
  "ends_at": "2026-10-31 23:59:59",
  "usage_limit": 100,
  "per_customer_limit": 1,
  "products": [],
  "categories": [],
  "customers": []
}
```

Send `coupon_code` alongside existing order creation/preview fields, including `customer_id` for customer-limited coupons. Preview returns `data.coupon_eligible: true` when an applied code is valid (false when no coupon is supplied), with `data.totals.coupon_discount`, tax and grand total. Invalid codes return the normal 422 envelope with `errors.coupon_code`. Preview does not reserve usage. Create and draft update also accept `coupon_code`; send null to remove, or omit it during update to retain the code. The dedicated removal endpoint ignores submitted pricing. Final submission recalculates everything on the server; client coupon amounts and snapshots are ignored.

Calculation order: effective catalog/special price or an authorized price override → manual line discount → coupon allocation → existing per-line percentage tax → shipping and optional shipping tax. The minimum subtotal is the eligible merchandise amount **after manual discounts, before coupon and tax**, excluding shipping. Product/category selections form a union; with neither selection, all merchandise qualifies. Customer selections are an additional restriction. Percentage discounts round half-up to paise, then the optional cap and eligible-subtotal ceiling apply. Fixed discounts use the same cap and ceiling. Allocation is proportional to remaining eligible net line values, rounded down per line with the final line receiving the remainder; taxes then round half-up per line as before. Line `discount_amount` and order `discount_total` include the coupon; `coupon_discount` identifies the coupon component and must not be subtracted again.

One coupon per order. Start/end instants are inclusive and use the application timezone unless an offset is supplied. Guest orders can use unrestricted coupons, but must select a registered customer for per-customer limits or customer restrictions. Drafts consume no usage. A draft consumes one redemption when submitted as pending or confirmed; processing, shipped and delivered retain it. Pending orders already have committed pricing and usage, so subsequent confirmation retains that snapshot even if the coupon changes. Permitted unpaid cancellations release usage exactly once; captured funds continue to block cancellation under the existing payment policy.

Submission, usage checks and redemption creation share the existing tenant transaction. Coupon row locks serialize competing redemptions and coupon edits; locking reads avoid stale usage/restriction snapshots. The unique redemption `order_id` and existing durable idempotency key prevent retry duplication. Coupon deletion is soft; order code, amount, rules, eligible subtotal and per-line allocations remain historical snapshots. Drafts created before this migration may need reopening and saving to refresh their pricing fingerprint.

Focused tests: `php artisan test --compact tests/Feature/TenantCouponTest.php tests/Feature/TenantOrderManagementTest.php`. Coverage includes authorization, tenant isolation, restrictions, dates, limits, competing draft submission, retries, cancellation, tax rounding and historical totals. These SQLite tests exercise repeated/competing submissions sequentially; actual simultaneous MySQL/InnoDB execution is not covered.
