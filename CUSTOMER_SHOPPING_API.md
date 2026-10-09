# Customer shopping API implementation report

Implemented on 2026-10-08 against Laravel 13.29.0, Sanctum 4.3.3 and Pest 4.7.8.

## Delivered behavior

- Persistent tenant-local cart: get, add/merge, set/increase/decrease quantity, remove, clear, current prices, SKU/options/images, tax/discount calculations, available stock and invalid-line messages.
- Paginated wishlist: duplicate-safe saving, removal, live catalog details and atomic move to cart. Normal cart addition leaves the wishlist intact.
- Checkout: saved address discovery/selection/defaults, immutable order address snapshots, persistent coupon selection/removal, server totals, enabled payment methods, price-change acknowledgement, confirmed order creation, stock reservation, durable idempotency and payment recovery.
- Customer order history: pagination, number search, status/date filters, saved line prices/taxes/discounts, address snapshots, shipment tracking, issued invoice PDF, current-price reorder, processed refund totals and shared return/replacement eligibility.
- Read-only active product/category catalog with product ID/slug lookup, variants, images, tax, stock and after-sales policy fields.
- Existing staff endpoints, admin screens, payment/webhook response contracts and gateway architecture remain in place.

## Authentication

Use the existing OTP endpoints with `X-Tenant-Domain` (or the existing `domain` input) for tenant resolution:

```http
POST /api/customer/auth/request-code
Content-Type: application/json
X-Tenant-Domain: your-registered-domain
```

```json
{ "email": "customer@example.com" }
```

Then submit the email and six-digit `code` to `POST /api/customer/auth/verify-code`. Use the returned bearer token on authenticated requests:

```http
Authorization: Bearer <customer-token>
Accept: application/json
Content-Type: application/json
```

The existing `customer-api` ability and one-hour expiry are unchanged. The central token chooses the tenant database; changing domain/customer/tenant fields cannot redirect authenticated shopping queries. Inactive or archived customers and expired/staff tokens are rejected by the existing middleware. Trusted gateway reconciliation continues to work after a customer is archived.

## Complete customer route list

All paths below are relative to `/api/customer`. Existing auth and after-sales routes are included for integration.

| Method | Path                                                 | Behavior                                                    |
| ------ | ---------------------------------------------------- | ----------------------------------------------------------- |
| POST   | /auth/request-code                                   | Existing OTP request; tenant domain required                |
| POST   | /auth/verify-code                                    | Existing OTP verification/token issuance                    |
| POST   | /auth/logout                                         | Existing token logout                                       |
| GET    | /cart                                                | Current cart                                                |
| POST   | /cart/items                                          | Add/merge SKU                                               |
| PATCH  | /cart/items/{item}                                   | Set/increase/decrease quantity                              |
| DELETE | /cart/items/{item}                                   | Remove owned line                                           |
| DELETE | /cart                                                | Clear active cart/coupon                                    |
| GET    | /wishlist                                            | Paginated saved products                                    |
| POST   | /wishlist                                            | Save product                                                |
| DELETE | /wishlist/{product}                                  | Remove owned saved product                                  |
| POST   | /wishlist/{product}/move-to-cart                     | Atomically add and remove wishlist entry                    |
| GET    | /addresses                                           | Paginated owned saved addresses                             |
| GET    | /checkout/summary                                    | Revalidated totals, addresses, fingerprint, enabled methods |
| POST   | /checkout/coupon                                     | Validate and save coupon                                    |
| DELETE | /checkout/coupon                                     | Remove coupon                                               |
| POST   | /checkout/place-order                                | Idempotent order placement                                  |
| GET    | /orders                                              | Paginated own history                                       |
| GET    | /orders/{order}                                      | Historical details and eligibility                          |
| GET    | /orders/{order}/tracking                             | Original shipment tracking                                  |
| GET    | /orders/{order}/invoice                              | Owned issued invoice PDF                                    |
| POST   | /orders/{order}/payment                              | Initiate/retry, verify or reconcile Razorpay                |
| POST   | /orders/{order}/reorder                              | Atomic current-catalog reorder                              |
| GET    | /products                                            | Paginated active catalog                                    |
| GET    | /products/{product}                                  | Active product by ID or slug, including variants            |
| GET    | /categories                                          | Paginated active categories                                 |
| GET    | /return-reasons                                      | Existing active return reasons                              |
| GET    | /orders/{order}/items/{item}/return-eligibility      | Existing shared return eligibility                          |
| POST   | /orders/{order}/items/{item}/returns                 | Existing quantity-level return request                      |
| GET    | /returns                                             | Existing owned returns                                      |
| GET    | /returns/{return}                                    | Existing owned return details                               |
| GET    | /replacement-reasons                                 | Existing active replacement reasons                         |
| GET    | /orders/{order}/items/{item}/replacement-eligibility | Existing shared replacement eligibility                     |
| POST   | /orders/{order}/items/{item}/replacements            | Existing replacement request                                |
| GET    | /replacements                                        | Existing owned replacements                                 |
| GET    | /replacements/{replacement}                          | Existing owned replacement details                          |
| POST   | /replacements/{replacement}/cancel                   | Existing eligible cancellation                              |

All authenticated routes retain `throttle:60,1`. Placement and payment additionally use a separate `customer-purchase` counter at 10/minute so the stricter limiter does not reuse the general counter.

## Request and response examples

Example IDs must come from the current tenant's catalog, cart, address and order responses. Response examples below are excerpts; full representations include further documented fields.

Add a SKU:

```http
POST /api/customer/cart/items
```

```json
{ "product_id": 1, "product_variant_id": 1, "quantity": 2 }
```

```json
{
    "data": {
        "id": 1,
        "status": "active",
        "items": [
            {
                "id": 1,
                "product_id": 1,
                "product_variant_id": 1,
                "quantity": 2,
                "current_price": "100.00",
                "available_stock": 10,
                "valid": true,
                "validation_message": null
            }
        ],
        "totals": {
            "subtotal": "200.00",
            "discount_total": "0.00",
            "tax_total": "36.00",
            "grand_total": "236.00"
        }
    }
}
```

A simple product can omit `product_variant_id` only when it has exactly one active variant. Configurable products always require selection. The stored line always uses the actual variant ID because the existing inventory/order architecture is SKU based.

Set an exact quantity with `{"quantity":3}`. Use `{"quantity":1,"operation":"increase"}` or `{"quantity":1,"operation":"decrease"}` to adjust it. A result below one returns 422; use DELETE to remove the line. Quantities are limited to 100000 and a cart to 100 lines.

Save a wishlist product with `{"product_id":1}`. Move it with `{"product_variant_id":1,"quantity":1}`. A failed move rolls back both cart and wishlist changes.

Preview checkout:

```http
GET /api/customer/checkout/summary?shipping_address_id=1&billing_address_id=1
```

Omitted address IDs use that customer's corresponding defaults. Missing/foreign/incomplete addresses return 422. Apply a coupon using `{"coupon_code":"SAVE10","shipping_address_id":1,"billing_address_id":1}`; remove it using DELETE. Get the final summary again after changing quantities/coupon/addresses.

Place the order using the summary's exact `data.fingerprint`:

```http
POST /api/customer/checkout/place-order
Idempotency-Key: checkout-example-001
```

```json
{
    "payment_method": "cod",
    "shipping_address_id": 1,
    "billing_address_id": 1,
    "expected_pricing_fingerprint": "<replace with the 64-character summary fingerprint>",
    "customer_note": "Please call before delivery."
}
```

Alternatively, send `idempotency_key` in JSON. The header takes precedence. Keep the same key and identical validated payload when retrying the same placement.

```json
{
    "data": {
        "order": {
            "id": 1,
            "status": "confirmed",
            "payment_status": "unpaid",
            "payment_method": "cod",
            "payment_state": "cod_pending_collection",
            "grand_total": "236.00"
        },
        "checkout": null
    }
}
```

Razorpay returns the same order envelope plus a `checkout` containing only the public key, provider order ID, minor-unit amount, currency and local order ID. Provider secrets/private payment rows are never serialized.

Payment initiation/retry uses `POST /orders/{order}/payment` with `{"action":"initiate"}` (the default). Reconciliation uses `{"action":"reconcile"}`. Verification uses:

```json
{
    "action": "verify",
    "razorpay_order_id": "order_Example1",
    "razorpay_payment_id": "pay_Example1",
    "razorpay_signature": "<replace with the 64-character signature>"
}
```

Verification checks the stored provider order, signature and server-fetched provider payment. An authorized/pending provider payment remains pending despite a browser success callback.

Order filters:

```http
GET /api/customer/orders?search=ORD-&status=confirmed&from=2026-10-01&to=2026-10-08&per_page=15&page=1
```

Date bounds may be used independently. Pagination follows existing customer APIs: the paginator has top-level `data`, `current_page`, `last_page`, `per_page`, `total` and links. Single-record/calculation responses use a `data` envelope.

Unavailable stock, products, variants, coupons or addresses return 422 with field errors. Ownership lookups return 404. Conflicting checkout keys or an unresolved second placement return 409. Gateway uncertainty returns the existing safe 503 response. Missing/expired customer authentication returns the existing 401 response.

## Existing service integration and financial rules

1. Lock the customer row on the selected tenant connection. Active-cart uniqueness is also enforced in the database.
2. Resolve the cart's current SKUs, quantity availability and owned saved addresses.
3. Use `TenantOrderCalculationService` and `TenantCouponService` for effective special prices, coupon allocation, tax-exclusive line accounting, shipping tax, decimal rounding and fingerprinting.
4. Use `TenantOrderIdempotencyService` under the dedicated `customer.checkout` operation. Store the logical order ID, not a stale payment result.
5. Use `TenantOrderService::save(..., submit_as: confirmed)` for snapshots, coupon redemption, status history/audit and existing transactional inventory reservation. It rechecks the preview fingerprint and stock under existing product/variant locks.
6. Commit the order and `cart_orders` relationship before any gateway call.
7. Use `TenantPaymentService`/`RazorpayOrderGateway` to recover existing provider facts before initiation/retry. Existing durable checkout references, raw webhook verification, reconciliation, duplicate-event handling and exceptional-capture review remain authoritative.
8. Fulfilment, invoice issuance, payment collection, returns, replacements and refunds continue through existing services.

Customer actors leave staff-only foreign keys null; audit metadata records the actual customer. Staff actions preserve their existing IDs.

The same key/payload returns the same order even after catalog deletion or payment-method disablement. A different validated payload with that key returns 409. New placements still require a currently enabled method. An unresolved order on the same cart blocks a different placement key.

The API exposes COD, cash, bank transfer and Razorpay only when enabled in existing payment configuration. COD acceptance leaves collection pending; cash/bank transfer leave verification pending. New customer APIs cannot collect offline funds. Refund summaries read processed existing refund records, preserving captured-payment history and showing partial/full refunded amounts independently.

No new jurisdiction/GSTIN inference or discount algorithm was added. `discount_total` already includes coupon discount; do not subtract it a second time.

## Cart conversion and recovery

- Adding, changing, viewing and previewing never reserve stock. Existing confirmed-order creation reserves stock.
- COD conversion happens when the order is accepted/confirmed, while its collection remains pending.
- Razorpay, cash and bank transfer keep the cart recoverable until trusted full payment. Initiation, authorization, failure and timeout do not clear the cart.
- Trusted reconciliation/webhooks and staff receipts schedule conversion after the tenant transaction commits. This avoids acquiring the customer lock while holding the payment/order lock. Cart access also synchronizes as crash recovery.
- Conversion archives the purchased cart and unchanged purchased lines. A fresh active cart is created. New lines and lines whose revision changed after placement are carried forward intact; a changed quantity is deliberately preserved as a later customer edit.
- A payment under exceptional-capture review does not trigger conversion. Existing review/fulfilment safeguards remain.
- A pending order retains its reservation according to the existing order lifecycle. There is no new automatic timeout cancellation or reservation-expiry policy.

## Tenant subscription integration

The current customer authentication checks central tenant status/domain and customer status. It has no centralized package/grace-period purchase policy. This task adds no independent date-based subscription restriction.

When that policy is introduced, gate new-purchase actions centrally (cart writes, wishlist moves, summary/coupon, placement and reorder). Keep history, issued invoices, tracking and eligible after-sales access separate from purchase eligibility. Review recovery of already committed payment obligations explicitly; do not block captured-payment reconciliation through a storefront purchase gate. Existing tenant-inactive authentication behavior is unchanged.

## Schema and deployment

New additive tenant migration: `database/migrations/tenant/2026_10_08_104256_create_customer_shopping_tables.php`.

| Table/change | Purpose and constraints                                                                                                  |
| ------------ | ------------------------------------------------------------------------------------------------------------------------ |
| carts        | Customer-owned status/coupon; unique nullable active_customer_id enforces one active cart                                |
| cart_items   | Cart/product/variant foreign keys, positive validated quantity, revision; unique cart/variant                            |
| wishlists    | Customer/product foreign keys and unique customer/product                                                                |
| cart_orders  | Unique order foreign key, cart reference, selected payment method, immutable item/revision snapshot, converted timestamp |
| orders index | Customer/date/id index for owned history                                                                                 |

No existing table/column/route/service was removed or renamed. Base variants are normalized before persistence; nullable variant uniqueness is avoided. Migrations run through the same tenant migration directory for provisioning and existing tenants.

Only isolated SQLite test migrations were executed. Production/real tenant migrations were not run. Deploy the migration before using these APIs:

```powershell
php artisan tenant:migrate-all --no-interaction
```

`config/customer_shopping.php` defaults to server-owned zero shipping and no shipping tax, matching the existing optional-shipping calculation. Configure a positive charge and an eligible tax if required. There is no existing automatic shipping-rate engine; tenant-specific/address/carrier pricing requires a future server-side policy integration, not customer-submitted totals.

Existing Razorpay credentials, enabled methods, webhook configuration and reconciliation schedule remain necessary. No `.env` changes, dependency changes, commits or development-server startup were performed.

## Files created or modified

- `database/migrations/tenant/2026_10_08_104256_create_customer_shopping_tables.php`
- `app/Models/Tenant/Cart.php`
- `app/Models/Tenant/CartItem.php`
- `app/Models/Tenant/Wishlist.php`
- `app/Models/Tenant/CartOrder.php`
- `app/Repositories/CustomerShoppingRepository.php`
- `app/Http/Resources/CustomerProductResource.php`
- `app/Http/Requests/CustomerShoppingRequest.php`
- `app/Http/Requests/CustomerCartRequest.php`
- `app/Http/Requests/CustomerWishlistRequest.php`
- `app/Http/Requests/CustomerCheckoutRequest.php`
- `app/Http/Requests/CustomerOrderRequest.php`
- `app/Services/CustomerCartService.php`
- `app/Services/CustomerWishlistService.php`
- `app/Services/CustomerCheckoutService.php`
- `app/Services/CustomerOrderService.php`
- `config/customer_shopping.php`
- `app/Http/Controllers/CustomerCartController.php`
- `app/Http/Controllers/CustomerWishlistController.php`
- `app/Http/Controllers/CustomerCheckoutController.php`
- `app/Http/Controllers/CustomerOrderController.php`
- `app/Http/Controllers/CustomerCatalogController.php`
- `database/factories/Tenant/CartFactory.php`
- `database/factories/Tenant/WishlistFactory.php`
- `database/factories/Tenant/CartItemFactory.php`
- `database/factories/Tenant/CartOrderFactory.php`
- `tests/Feature/CustomerShoppingTest.php`
- `app/Models/Tenant/OrderItem.php`
- `app/Services/TenantOrderService.php`
- `app/Services/TenantPaymentService.php`
- `app/Services/TenantReturnEligibilityService.php`
- `app/Services/TenantReplacementEligibilityService.php`
- `routes/api.php`
- `.ai/rules/index.md`
- `.ai/rules/services.md`
- `tests/Feature/TenantAuditLogTest.php`

This report is also new. Pre-existing work, including `API_DOCUMENTATION.json` and unrelated tests, was preserved. The existing audit migration test received only a targeted rollback-path update so later additive migrations cannot change which audit migrations it exercises.

## Verification

- Final shopping feature suite and full audit suite: 92 passed, 504 assertions (61 shopping cases and 31 audit cases).
- Broad affected regression run: 627 tests, 621 passed, 2 skipped, 4 failed; 3803 assertions. Covered orders, payments, coupons, products/inventory, customers, invoices, returns/refunds, replacements, API/web authentication, tenant resolution, audit, tax and provisioning.
- The audit failure was caused by a positional `--step=2` rollback selecting the newly added migration. It now targets the audit files explicitly; the focused rerun passed and the entire audit suite subsequently passed.
- Three unrelated existing assertion failures remain: two `TenantProductManagementTest` unavailable-tax label checks expect uninterrupted text where the existing Blade view contains whitespace/newlines; `TenantTaxManagementTest` expects 403 for an inactive web user while existing authentication redirects. Their application code was not changed.
- Shopping tests exercise database switching with identical customer IDs, original tenant disconnection, customer/staff/expired tokens, SKU merges, invalid/deleted catalog records, stock/pricing changes, coupons, address ownership, idempotency/replay, offline states, gateway timeout/failure/recovery, duplicate callbacks, later cart edits, historical snapshots, issued PDFs, reorder rollback, claimed after-sales quantities, archived-customer reconciliation and bounded cart query count.
- Payment tests use a fake gateway; no real payment provider was contacted.
- Pint was run on explicitly selected task-owned PHP paths to preserve unrelated dirty files.
- Customer routes were inspected with `php artisan route:list --path=api/customer --no-interaction`.
- PHP syntax checks passed for all 33 task PHP files. `git diff --check` and final source review passed. All 37 customer routes are registered, including 23 new routes.

Live simultaneous MySQL/InnoDB contention and real gateway/network behavior were not exercised by the SQLite/fake-gateway test environment. The implementation uses database uniqueness, customer-row serialization, existing inventory row locks and durable idempotency, but those production concurrency conditions still need integration verification. The complete project suite has not been claimed green; run `php artisan test --compact` before release.
