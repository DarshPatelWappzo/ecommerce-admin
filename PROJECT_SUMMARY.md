# Project Context: E-commerce Admin

Last verified against the local working tree: **8 October 2026**.

This document consolidates the application's architecture, modules, contracts, development conventions, and operational commands. It reflects current local source, including uncommitted changes. It is not a deployment report or a claim that every test passes. Read `AGENTS.md` and applicable `.ai/rules` before changing code.

## 1. Purpose and identities

This Laravel application provides central platform administration and isolated e-commerce administration for multiple tenants. Each registered central domain receives its own MySQL database. Tenant staff manage catalog data, customers, orders, payments, invoices, returns, refunds, replacements, staff permissions, and audit history.

| Identity                   | Responsibility                                       | Storage and access                                                              |
| -------------------------- | ---------------------------------------------------- | ------------------------------------------------------------------------------- |
| Central user / super admin | Accounts, domains, packages, provisioning            | Central users; super-admin browser access uses the web guard and is_super_admin |
| Tenant staff               | Tenant administration and staff APIs                 | Tenant users; tenant session guard or tenant-bound bearer token                 |
| Customer                   | Customer records and return/replacement self-service | Tenant customers; email OTP and customer-bound bearer token                     |

The repository is an administration application with JSON APIs, not a complete customer storefront. Customer self-service currently centers on returns and replacements.

## 2. Verified technology stack

Installed versions checked with `composer show --direct`:

| Component             | Installed version / role      |
| --------------------- | ----------------------------- |
| PHP requirement       | ^8.3 in composer.json         |
| Laravel               | 13.29.0                       |
| Laravel Sanctum       | 4.3.3; personal access tokens |
| Laravel JSValidation  | 4.10.3                        |
| Razorpay PHP SDK      | 2.9.3                         |
| Laravel Dompdf        | 3.1.2; invoice PDFs           |
| Pest / Laravel plugin | 4.7.8 / 4.1.0                 |
| Laravel Pint          | 1.30.5                        |
| Laravel Boost         | 2.6.0                         |

The shared Blade layout references Bootstrap 5.3.3, Font Awesome 6.7.2, jQuery 3.7.1, Select2 4.1.0-rc.0, and SweetAlert2 11 through CDNs. Application CSS and JavaScript are served from `public`.

`package.json` has no npm scripts. It declares `concurrently` and optional `@laravel/multiplex`. There is no current Vite build workflow or React/Vue/Inertia/Livewire application layer. Node is useful for JavaScript tests and `composer run dev`.

## 3. Repository and architecture map

| Location                        | Responsibility                                                          |
| ------------------------------- | ----------------------------------------------------------------------- |
| routes/web.php                  | Super-admin and tenant browser routes                                   |
| routes/api.php                  | Central, staff, customer, and webhook APIs                              |
| routes/console.php              | Scheduled reconciliation                                                |
| bootstrap/app.php               | Routing, middleware priority, exception responses                       |
| app/Http/Controllers/SuperAdmin | Platform screens and provisioning retry                                 |
| app/Http/Controllers/Api/Tenant | Dedicated tenant API controllers                                        |
| app/Http/Controllers/Tenant\*   | Tenant screens; some also serve JSON                                    |
| app/Http/Controllers/Customer\* | OTP and customer returns/replacements                                   |
| app/Http/Requests               | Normalization, validation, action authorization                         |
| app/Http/Resources              | Customer, address, and order serialization                              |
| app/Http/Middleware             | Tenant resolution and authentication boundaries                         |
| app/Models                      | Central models and personal access tokens                               |
| app/Models/Tenant               | Models using the tenant connection                                      |
| app/Repositories                | Queries, filtering, pagination, persistence, stock helpers              |
| app/Services                    | Transactions, calculations, provisioning, financial workflows, auditing |
| app/Console/Commands            | Tenant maintenance and reconciliation commands                          |
| app/Mail                        | Credentials, OTP, replacement notification mail                         |
| database/migrations             | Central schema                                                          |
| database/migrations/tenant      | Schema applied independently to each tenant                             |
| database/seeders                | Central and tenant seeders                                              |
| resources/views                 | Layouts, screens, components, email templates                           |
| public/css and public/js        | Directly served frontend assets                                         |
| tests/Feature and tests/Unit    | Pest tests and Node unit tests                                          |
| .ai/rules/index.md              | Source-path map for shared implementation rules                         |

Typical flow: route/middleware selects and authenticates the tenant, a Form Request validates and authorizes, the controller orchestrates repositories/services, and a Blade view, redirect, resource, or JSON response is returned. Shared services keep browser and API state changes consistent.

## 4. Database boundaries and provisioning

### Central storage

Business tables include `users`, `packages`, `user_packages`, `user_domains`, `tenant_databases`, `audit_logs`, and `personal_access_tokens`. Framework migrations also provide sessions, password reset tokens, cache/locks, and queue tables.

`UserDomain` associates a central account with a domain. `TenantDatabase` records the generated database name, provisioning state, timestamps, and errors. Central tokens carry `tenant_database_id`; identical numeric user IDs in separate tenant databases are different identities.

### Tenant storage

Database names follow `tenant_{centralUserId}_{domainId}`. `TenantDatabaseCreator` validates that format and requires the central default connection to be MySQL for provisioning. Although configuration defaults to SQLite, SQLite alone cannot run the complete tenant provisioning workflow.

`TenantConnectionManager` sets the database on the named tenant connection, purges/reconnects it, and clears it after use. Select the connection before fetching tenant users or binding tenant route models.

| Domain          | Main tenant tables                                                                                                                                                         |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Staff           | users, roles, permissions, role_user, role_permissions                                                                                                                     |
| Catalog         | categories, products, product_variants, tags, attributes, attribute_options, product associations/value tables, variant_attribute_values, product_images, related_products |
| Customers       | customers, customer_addresses, customer_login_codes                                                                                                                        |
| Tax/promotions  | taxes, coupons, coupon restriction pivots, coupon_redemptions                                                                                                              |
| Orders          | orders, order_items, order_addresses, order_status_histories, order_shipments, order_stock_movements, order_idempotency_keys                                               |
| Payments        | order_payments, order_payment_checkouts, order_payment_events                                                                                                              |
| Invoices        | invoices, invoice_items, invoice_sequences                                                                                                                                 |
| Returns/refunds | return_reasons, return_requests, refunds, refund_attempts, return_stock_movements                                                                                          |
| Replacements    | replacement_requests, replacement_status_histories, replacement_stock_movements                                                                                            |
| Audit           | audit_logs                                                                                                                                                                 |

This map comes from migrations, not an audit of every deployed database. Use current migrations and live schema tools for exact columns and deployment status.

### Provisioning lifecycle

`TenantProvisioningService` creates/reuses metadata, creates the MySQL database, runs tenant migrations and `TenantDatabaseSeeder`, creates the administrator, assigns the Admin role, and emails temporary credentials. States include creating, migrating, seeding, active, and failed.

Tenant staff accounts are separate from central accounts and must change temporary passwords on first login. Retry can reset an existing administrator's temporary password and first-login flag. Central persistence and database provisioning are separate steps; provisioning failures have explicit retry support.

## 5. Authentication and permissions

### Super-admin browser

Protected routes under `/super-admin` use auth and `EnsureSuperAdmin`. Screens cover dashboard, regular accounts/domains, packages, provisioning retry, and audit history. Package-assignment relationships exist, but the dedicated assignment-management controller is scaffolded rather than a complete routed workflow.

### Tenant browser

Protected routes under `/tenant` resolve the session's tenant_domain before auth:tenant, then enforce active staff and first-login requirements. `EnsureActiveTenantUser` invalidates disabled users' sessions. Password-change routes also enforce the active-user boundary.

Authenticated browser requests cannot switch tenants through submitted domain/header overrides. The session tenant wins. Unauthenticated login may select a domain; missing/inactive tenants fail closed.

### Tenant staff API

`POST /api/tenant/login` resolves the domain input or X-Tenant-Domain header and returns a bearer token. `AuthenticateTenantToken` checks token type, expiry, tenant-api ability, active tenant, and active staff. It selects the database before route model binding.

Action permissions and completed-first-login requirements remain in request/controller logic. Tenant user updates synchronize scalar role_id and require both users.update and roles.update, including self-updates. Preserve the existing permission contracts.

### Customer API

`POST /api/customer/auth/request-code` and `POST /api/customer/auth/verify-code` provide tenant-scoped email OTP. Codes are hashed, expire after ten minutes, allow at most five verification attempts, and are deleted on success. Request throttling includes a tenant/email key.

Customer tokens currently expire after one hour and carry customer-api ability. `AuthenticateCustomerToken` selects the token's tenant and requires an active customer. Return/replacement actions enforce ownership of orders and purchased items.

### Existing central API exception

Current `GET /api/users` and `POST /api/users` routes have no authentication middleware in their route definitions. `SuperAdminUserStoreRequest::authorize()` returns true. The index action is marked in source as being for testing. Do not describe these endpoints as super-admin-protected because their browser counterparts are protected. This is an existing access-control gap; this documentation update does not change it.

## 6. Business modules and critical contracts

### Catalog, categories, and taxes

Products support variants/SKUs, stock, images, categories, tags, attributes/options, related products, tax assignment, and return/replacement policy flags and day windows. Main code includes `TenantProductService`, catalog/category repositories, and corresponding requests/controllers.

Disabled return/replacement policies clear their associated day values. These product settings are separate from executing the operational workflows.

Product tax_id can be null. An explicit zero-rate tax differs from missing tax configuration. Product APIs preserve omitted assignments and clear explicit null values; current unavailable assignments are handled separately from new choices. Order confirmation requires available product, variant, and tax records.

### Customers and addresses

Customers support individual/business profiles, normalized contact data, company/GSTIN, status, internal notes, and soft deletion. Address shipping/billing defaults are coordinated under customer locking. The first address becomes both defaults; deleting a default chooses a replacement.

India address choices live in `config/customer_locations.php`. Orders preserve customer, business, tax identity, and address snapshots. Later edits/removal must not rewrite historical orders. Staff orders can have a null customer_id for guests.

### Orders and inventory

Core classes: `TenantOrderService`, `TenantOrderCalculationService`, `TenantOrderIdempotencyService`, and `TenantOrderRepository`.

Normal progression: draft -> pending -> confirmed -> processing -> shipped -> delivered. Drafts may also go directly to confirmed. Cancellation depends on both state and financial restrictions.

- Currency is INR; catalog prices are tax-exclusive with one percentage tax. Do not infer CGST/SGST/IGST from GSTIN.
- Shipping cost and tax are optional. Explicitly selected shipping tax must be available; an explicit zero rate is valid.
- Draft submission checks a pricing fingerprint so changed prices/taxes require review.
- Confirmation reserves inventory. Shipping deducts stock and releases reservations. Eligible cancellation releases reserved stock.
- Payment and fulfillment status are separate. Delivery requires paid status and captured funds equal to grand total, including explicit COD collection.
- Captured funds, a gateway checkout, or an issued invoice restrict cancellation. Return refunds do not imply a general cancellation/credit-note workflow exists.
- Preserve order locks, stock ledgers, history, snapshots, and durable idempotency where provided.

### Coupons

`TenantCouponService` discounts eligible net merchandise after manual discounts and before item tax; shipping is excluded. Product/category restrictions form a union; customer restrictions are additional. Guests cannot use customer-specific restrictions or per-customer limits.

Drafts consume no usage. Pending through delivered orders use a unique order redemption; eligible cancellation releases it. Preserve coupon snapshots and transaction/locking behavior.

### Payments

`TenantPaymentService`, `RazorpayOrderGateway`, and `OrderPaymentGateway` implement checkout and reconciliation. Configured methods include COD, cash, bank transfer, and Razorpay.

A durable checkout owns the full order balance. Retry/recovery reuses the stored provider order or durable receipt rather than blindly creating another charge. Provider data is verified server-side before captured money is recorded.

The webhook verifies the raw signature and selects the tenant from trusted provider-order information. Duplicate/late captures remain financial facts and can put the checkout into review, blocking fulfillment. Polling and scheduled reconciliation recover missed callbacks/webhooks. Checkout reservations also constrain offline collection and cancellation.

### Invoices

`TenantInvoiceService`, `TenantInvoiceSnapshotBuilder`, `TenantInvoiceNumberService`, and `TenantInvoicePdfService` handle drafts, dispatch approval, issuance, numbering, print, and PDF download.

Issuance preserves order snapshots and uses financial-year sequences with order-before-sequence locking. Prepaid issuance requires fully captured money; COD has different issuance eligibility. Dispatch approval is explicit and separate from payment. Automatic invoice processing runs synchronously after approval commits while the tenant connection remains selected.

Seller settings in `config/invoices.php` are keyed by exact tenant database name. Missing seller details do not block otherwise valid issuance and must not be invented. Invoice discount_amount already includes coupon discount; never subtract it twice. A general credit-note workflow is not implemented.

### Returns and refunds

Customer claims identify order_item_id, not a bare product ID. Eligibility uses delivery timestamps, purchased policy snapshots, and already-claimed quantities across returns/replacements. Legacy policy handling lives in the eligibility services.

The main return flow covers request, approval, transit/receipt, inspection, refund processing, and closure. `ReturnRequest::TRANSITIONS` defines branches for rejection, cancellation, failed inspection, and refund failure/retry.

`TenantReturnService` locks the order before related records. Only explicit restock disposition increases sellable inventory. Refunds use original discounted merchandise plus item tax and exclude shipping. Original fulfillment history and invoices remain intact.

`TenantRefundService` reserves one refund per return before external submission. Durable refund_attempts receipts support reconciliation. Ambiguous gateway outcomes stay processing; retries are for verified failures. Manual and Razorpay refunds use separate paths.

### Replacements

Replacements use the original SKU, not a general exchange to another product. `TenantReplacementEligibilityService` and `TenantReplacementService` coordinate purchased-item and quantity constraints with returns.

Normal flow: requested -> approved -> picked_up -> received -> qc_passed -> replacement_processing -> shipped -> delivered -> completed.

- Rejection and failed QC require an admin note.
- qc_failed is terminal, sets defective disposition, records history/audit, and queues customer notification. It adds no sellable stock and creates no shipment or automatic refund.
- Passing QC can explicitly restock the received item according to disposition.
- Processing reserves original-SKU stock; unavailable stock moves the claim to out_of_stock.
- Out-of-stock claims can retry processing or convert once into the existing return/refund workflow.
- Shipping needs courier/tracking information, consumes reservations, and creates a separate replacement shipment.
- Customer cancellation is available in requested/approved states, before pickup.

### Audit history

Central auditing uses `Auditable` and `AuditLogService`. Tenant audit services/actions/value helpers write tenant history. Preserve field exclusions and sanitization, especially for credentials and sensitive customer information. Tenant audit access is permission-gated.

## 7. Route and API map

| Surface          | Path families                                                                                                                                                                         |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Central browser  | /super-admin/login, dashboard, admin, package, audit-logs                                                                                                                             |
| Tenant browser   | /tenant/login, dashboard, users, roles, categories, products, catalog settings, taxes, customers, coupons, orders, payments, invoices, returns, replacements, audit logs              |
| Staff API        | /api/tenant/login, logout/password change, users, categories, products, catalog settings, customers/addresses, orders, coupons, payments, invoices, returns, replacements, audit logs |
| Customer API     | /api/customer/auth/\*, reasons, purchased-item eligibility/create endpoints, own return/replacement lists/details                                                                     |
| Razorpay webhook | POST /api/tenant/payments/razorpay/webhook                                                                                                                                            |
| Central API      | GET /api/users and POST /api/users; see access-control exception above                                                                                                                |

Do not infer full CRUD support from this map. Routes, Form Requests, controllers, and resources determine exact verbs, fields, permissions, and response envelopes. Existing APIs mix resources and explicit JSON; preserve their contracts.

```powershell
php artisan route:list --except-vendor --no-interaction
php artisan route:list --path=api --no-interaction
php artisan route:list --name=tenant.orders --no-interaction
```

## 8. Frontend conventions

Reuse Blade layouts, sidebar/header partials, and shared CSS. Forms use Laravel JSValidation, inline field-error messages, and novalidate. Confirmations use Swal.fire.

Listings share listing-page-header, dashboard-card listing-table-card, table-responsive, listing-table, listing-empty, and listing-table-footer. Components include x-listing-search, x-status-badge, x-filter-button, x-filter-offcanvas, and x-filter-field.

Filters use GET, validated whitelists, named-route reset links, and query-preserving pagination. Count nonempty values including 0. Place drawers in the overlays stack outside AJAX replacement containers.

Preserve selectors, permissions, routes, and behavior during visual changes. Sidebar collapse uses `public/js/sidebar.js` and sidebar-collapsed local storage. Product deletion uses [data-product-delete] and `product-form.js`. Other major scripts include order-form.js, order-payment.js, replacement-actions.js, customer-management.js, and ajax-pagination.js.

## 9. Setup and configuration

Start from `.env.example`; keep secrets out of documentation. A complete tenant environment requires MySQL as the central default, credentials able to create tenant databases, and shared MySQL settings for the tenant connection. Configure mail for temporary credentials and customer OTP; the log mailer does not deliver messages.

For a fresh checkout, after creating/configuring `.env`:

```powershell
composer install
php artisan key:generate --no-interaction
php artisan migrate --seed --no-interaction
php artisan serve
```

Generate the key only during initial setup. `DatabaseSeeder` is the source for the local super-admin seed account. `composer run dev` starts the server and queue listener through concurrently; install the declared Node dependencies first when using it.

`composer run setup` installs PHP dependencies, ensures an environment file, generates a key, migrates, and caches views. It does not replace tenant provisioning or seed the central account itself.

| Configuration                                       | Purpose                                             |
| --------------------------------------------------- | --------------------------------------------------- |
| config/database.php                                 | Central default and dynamic tenant MySQL connection |
| config/auth.php and config/sanctum.php              | Guards, providers, tokens                           |
| config/order_payments.php                           | Enabled methods and Razorpay keys                   |
| config/invoices.php                                 | Prefix, draft review, tenant seller settings        |
| config/customer_locations.php                       | Address country/state choices                       |
| config/mail.php, config/queue.php, config/cache.php | Delivery, queued work, locks/throttling             |

Payment environment names include ORDER_PAYMENTS_COD_ENABLED, ORDER_PAYMENTS_CASH_ENABLED, ORDER_PAYMENTS_BANK_TRANSFER_ENABLED, ORDER_PAYMENTS_RAZORPAY_ENABLED, ORDER_RAZORPAY_KEY_ID, and ORDER_RAZORPAY_KEY_SECRET. Webhook configuration prefers RAZORPAY_WEBHOOK_SECRET and falls back to ORDER_RAZORPAY_WEBHOOK_SECRET. Razorpay is disabled by default.

## 10. Maintenance and scheduled work

```powershell
php artisan tenant:list --no-interaction
php artisan tenant:migrate <user_id> --no-interaction
php artisan tenant:seed <user_id> --no-interaction
php artisan tenant:retry-provision <user_id> --no-interaction
php artisan tenant:migrate-all --no-interaction
php artisan orders:reconcile-payments --no-interaction
php artisan returns:reconcile-refunds --no-interaction
```

Replace <user_id> with a central account ID; do not paste the placeholder literally. Reconciliation also accepts --tenant= for a central tenant-database ID and --limit=. Check command help before operational use.

Central and tenant migrations are separate. Existing tenants need additive tenant migrations; new provisioning runs the tenant directory and seeder. Some migrations grant new Admin permissions; other roles need deliberate assignment.

`routes/console.php` schedules payment and refund reconciliation every five minutes with overlap prevention and single-server locking. Deployments need a running scheduler and appropriate queue workers. Multi-server scheduling requires compatible shared lock storage. Replacement notifications are queued after commit.

## 11. Tests and verification

Feature suites cover authentication, tenant isolation, provisioning/retry, users/roles, catalog/categories, customers, tax, coupons, orders, payments, invoices, returns/refunds, replacements, auditing, listing filters, and presentation. Console migration tests are under `tests/Feature/Console/Commands`.

`phpunit.xml` uses in-memory SQLite, array cache/mail/session, and synchronous queues. Tenant tests supply tenant fixtures. This does not establish production MySQL locking or browser layout correctness. `tests/Pest.php` does not globally enable RefreshDatabase; follow each suite's setup.

```powershell
php artisan test --compact tests/Feature/TenantPaymentTest.php
php artisan test --compact tests/Feature/TenantReturnRefundTest.php
node --test tests/Unit/order-payment.test.js tests/Unit/product-policy.test.js tests/Unit/replacement-actions.test.js tests/Unit/sidebar.test.js
git diff --check
```

For code changes, read the testing skill, add/update behavior-focused coverage, and run affected tests. Follow AGENTS.md for PHP formatting and complete-suite follow-up. Review existing changes before broad formatter runs. Mobile navigation, drawers, Select2, and AJAX interactions need browser verification.

This documentation refresh checked installed versions, route registration, source/configuration, migrations, and rules. It does not report a new application test-suite run, live payment test, deployed schema audit, or browser acceptance result.

## 12. Working safely in this repository

1. Read AGENTS.md, .ai/rules/index.md, matching rules, and relevant domain skills. Search rules for the feature's keywords.
2. Check git status and preserve existing user work. Inspect sibling implementations before introducing patterns.
3. Confirm installed versions and consult Boost documentation for changes relying on framework/package APIs.
4. Identify the correct database, guard, permission, request, repository, and shared service.
5. Preserve tenant selection before authentication/model binding, snapshots, lock order, idempotency, and response contracts.
6. Verify changed behavior and failure modes; report exact checks and remaining limits.
7. Keep this overview current. Use Boost record-rule for durable shared implementation rules.

Related references: [README](README.md), [database overview](DATABASE_SCHEMA.md), [agent instructions](AGENTS.md), and [shared rules index](.ai/rules/index.md). Older prose can lag source; routes, migrations, requests, services, and tests determine current implementation.
