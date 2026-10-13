# Platform package payments

Platform billing uses the central database and encrypted credential versions. Tenant storefront credentials and order payment flows are independent.

## Deploy

No migration has been run against an existing database. After backing up the central database and reviewing the migration, run:

```shell
php artisan migrate --force --no-interaction
```

The new central migration is `2026_10_12_103730_create_platform_package_payments.php`. It also converts central `users`, `user_packages`, and `audit_logs` to InnoDB because the inspected installation used MyISAM. Conversion can lock large tables: schedule the migration appropriately. Rollback removes new billing tables/columns but intentionally retains InnoDB.

No new dependency or tenant migration is required. Existing dependencies include Razorpay SDK 2.9.3 and Brick Math 0.18.0. No Vue, Vite, or asset build is needed.

Ensure the existing Laravel scheduler runs. It invokes `packages:reconcile-payments` every five minutes with overlap and single-server protection. Manual recovery:

```shell
php artisan packages:reconcile-payments --payment=123 --no-interaction
php artisan packages:reconcile-payments --limit=100 --no-interaction
```

These recovery commands fetch provider state; they never create new orders. They contact Razorpay when run outside mocked tests.

## Configure

Open **Super-admin → Payment Configuration**. Select Test, enter the platform Key ID and Key secret, and enable it. The Webhook secret is optional: without it, browser verification and reconciliation confirm payments, while webhook requests are rejected. Add a webhook secret later to enable signed webhook delivery for the new credential version. Blank credential fields preserve values in that environment. Changing credentials creates an immutable encrypted version; pending attempts retain the previous version. Switching modes does not copy credentials between environments. Only credential-presence indicators are rendered.

Copy the version-specific webhook endpoint shown on the page. For example, the currently resolved local version-1 endpoint is:

`http://127.0.0.1:8000/api/webhooks/platform/razorpay/1`

Use your publicly reachable HTTPS deployment origin in the Razorpay dashboard; loopback is not publicly reachable. Configure the corresponding test/live environment, the same webhook secret, and these events:

- `payment.captured`
- `order.paid`
- `payment.failed`

Keep historical endpoints and their original webhook secrets available for pending payments when rotating credentials. Delivery is verified against the raw request body and rechecked with the attempt's API credentials. No provider payload or secret is persisted in the event ledger.

Official reference: [Razorpay webhook validation and testing](https://github.com/razorpay/markdown-docs/blob/master/webhooks/validate-test.md).

## Purchase and renew

An active tenant user with the active `admin` role and `plans.view` permission opens **Settings → Plans & Subscription**, selects a supported cycle, and chooses **Buy Now** or **Renew Now**.

Selling prices are the configured `package_prices.price` total for each cycle, converted to integer INR paise with decimal arithmetic. They are not monthly rates multiplied by the cycle. Infrastructure user-count interpolation is unrelated and is not used for billing. No capacity input is accepted. Gateway checkout requires a price of at least INR 1; free-package activation is outside this flow.

The quote, package name, duration and pricing basis are snapshotted before order creation. Early renewal extends the current exclusive expiry; renewal after expiry (including grace) starts from the time capture is confirmed locally. Months use no-overflow calendar arithmetic; grace ends seven days after expiry. API fields map legacy `start_date/end_date/grace_expires_at` to `starts_at/expires_at/grace_ends_at`. History preserves every purchased term. Test subscriptions are isolated from live summaries and clearly labelled.

No upgrades, downgrades, proration or automatic recurring billing are implemented. Existing subscriptions without a known expiry require support before renewal.

A browser signature is insufficient: payment/order association, exact amount, currency, environment, receipt, purpose and captured state are checked against Razorpay. Authorization stays pending. Dismissal and failure do not cancel the order or prevent later capture.

An unresolved attempt blocks a second purchase for that central owner in the same environment. Reuse the same idempotency key or use **Check / resume** in history. A definitively rejected creation allows a new key. A timeout keeps the reservation; reconciliation looks up the original receipt and never blindly submits another order. If no order can be found after an ambiguous creation, manual investigation is required; there is intentionally no unsafe automatic reset.

## API examples

All six tenant endpoints require the existing tenant bearer token and billing authorization. Payment lookups additionally require the authenticated tenant database ID to match the attempt. Customers cannot access these endpoints.

```http
POST /api/tenant/packages/1/checkout
Authorization: Bearer <tenant-token>
Idempotency-Key: unique-purchase-uuid
Content-Type: application/json

{"billing_cycle":"monthly"}
```

Example result (other safe attempt fields are also returned):

```json
{"data":{"id":1,"reference":"uuid","status":"pending","amount":99919,"currency":"INR","mode":"test","checkout":{"key":"rzp_test_PUBLIC","order_id":"order_example","amount":99919,"currency":"INR","name":"Standard"}}}
```

```http
POST /api/tenant/package-payments/1/verify
Authorization: Bearer <tenant-token>
Content-Type: application/json

{"razorpay_order_id":"order_example","razorpay_payment_id":"pay_example","razorpay_signature":"<64-character-hex-signature>"}
```

Read `GET /api/tenant/packages`, `GET /api/tenant/package-payments/1`, `GET /api/tenant/subscription`, and `GET /api/tenant/subscription/payments?page=1`. Verification returns the updated payment in `data`; only `status: captured` has a resulting subscription. History uses 15 records per page. Validation errors use the existing API 422 format. Provider unavailability returns a safe 503 message. See `API_DOCUMENTATION.json` for all routes and example payloads.

## Razorpay test-mode acceptance

After migration and test configuration:

1. Configure an eligible nonzero package cycle price.
2. Sign in as a tenant admin and complete Razorpay test checkout.
3. Verify exactly one central payment and subscription appear with environment Test.
4. Refresh/replay the callback and redeliver a webhook; confirm no extra term is granted.
5. Close checkout, fail a test payment, and try an authorized-but-uncaptured payment. Confirm no premature activation.
6. Capture the pending payment in Razorpay test mode or let automatic capture complete, then use Check / resume or reconciliation.
7. Rotate test credentials and confirm an older pending attempt still verifies through its original version.
8. Confirm early, expired and month-end renewals and their seven-day grace dates.

Automated tests use isolated SQLite and mocked HTTP responses. The engine-conversion test creates and drops a uniquely named isolated MySQL database; it never migrates an existing database:

```shell
php artisan test --compact tests/Feature/PlatformPackagePaymentTest.php tests/Feature/PackageSubscriptionCatalogTest.php
node --test tests/Unit/package-checkout.test.js tests/Unit/plans-catalog.test.js
```

For the MySQL migration test, set the process environment variable `PLATFORM_MYSQL_TEST=1` and run `php artisan test --compact tests/Feature/PlatformPaymentMigrationTest.php`; the test account needs CREATE/DROP DATABASE privileges.

Real Razorpay checkout, dashboard delivery, visual browser acceptance and simultaneous multi-process contention have not been verified. No real payment API was called during implementation. No application server was started, no existing database was migrated, and changes remain uncommitted.


## Implementation files and verification record

- Central persistence: `database/migrations/2026_10_12_103730_create_platform_package_payments.php`; `app/Models/PlatformGatewayConfiguration.php`, `PlatformGatewayVersion.php`, `PackagePayment.php`, `PlatformWebhookEvent.php`, `UserPackage.php`; `config/subscriptions.php`.
- Gateways and checkout: `app/Services/PlatformPaymentGateway.php`, `PlatformPaymentGatewayManager.php`, `PlatformRazorpayGateway.php`, `PlatformGatewayConfigurationService.php`, `PackageCheckoutService.php`, `PackagePricingService.php`, `TenantSubscriptionService.php`; `app/Exceptions/PlatformPaymentException.php`.
- Repositories: `app/Repositories/PlatformPaymentRepository.php`, `PackageRepository.php`, `TenantSubscriptionRepository.php`.
- HTTP: `app/Http/Requests/PlatformGatewayRequest.php`, `PackagePaymentRequest.php`; `app/Http/Controllers/SuperAdmin/PlatformGatewayController.php`, `PackagePaymentController.php`, `PlatformWebhookController.php`, `TenantPlansController.php`; `routes/web.php`, `routes/api.php`, `bootstrap/app.php`.
- UI: `resources/views/super-admin/platform-payments/index.blade.php`, `resources/views/tenant/plans/index.blade.php`, `resources/views/layouts/sidebar.blade.php`, `public/js/package-checkout.js`.
- Recovery: `app/Console/Commands/ReconcilePackagePayments.php`, `routes/console.php`.
- Tests: `tests/Feature/PlatformPackagePaymentTest.php`, `PlatformPaymentMigrationTest.php`, `tests/Unit/package-checkout.test.js`.
- Documentation: `API_DOCUMENTATION.json`, this guide, and the Boost-recorded platform billing rule in `.ai/rules/services-services.md` with its index mapping.

Verification on 2026-10-12:

- Combined platform, catalog, tenant gateway, tenant payment and central model regression run: **203 passed, 1075 assertions**.
- Final platform/catalog run after the current-subscription selection regression was added: **65 passed, 293 assertions** (these overlap the combined run).
- Isolated MySQL migration/rollback run: **1 passed, 8 assertions**.
- Checkout and catalog JavaScript: **6 passed**.
- Pint ran with `--dirty --format agent` restricted to this task's PHP paths; final run passed. `git diff --check` passed.
- Full application suite was not run. Run `php artisan test --compact` when ready for the broader suite.
