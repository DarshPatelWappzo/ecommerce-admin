---
paths:
  - 'app/Services/TenantReturn*.php, app/Services/TenantRefund*.php, app/Services/RazorpayRefundGateway.php'
  - 'app/Services/*Subscription*.php, app/Services/PackagePricingService.php, app/Services/InfrastructureCostEstimator.php'
  - 'app/Services/*Payment*.php, app/Services/PackageCheckoutService.php, app/Services/*Subscription*.php'
---

# Services Services

## Item returns, financial reservations and legacy policy
Returns lock the tenant order before the item/return/refund rows, use shipment.delivered_at and purchase policy snapshots, and reserve active/completed quantities. Legacy unsnapshotted orders use the current product policy and freeze it on first return. Refund only original discounted merchandise plus item tax; shipping stays excluded. Reserve one refund per return before any external call, keep uncertain gateway submissions processing, reconcile by durable attempt receipt, and retry only verified failures with a new attempt. Only inspection disposition restock increases variant stock. Keep fulfillment status and original invoices unchanged.

## Keep platform subscriptions separate from infrastructure and storefront payments
The tenant plans catalog is read-only. Resolve existing central user_packages through the authenticated tenant_database.user_id (central owner), never tenant staff IDs or request parameters. Package selling prices are independent of infrastructure costs; capacity is not an enforced user limit. Limits are display-only, NULL means unlimited and absent rows mean unconfigured. Publish infrastructure details only through explicit public_infrastructure opt-in. Future platform billing must use platform credentials, never tenant storefront gateway credentials.

## Platform package billing uses immutable central quotes and credential versions
Platform billing uses subscriptions.connection and central encrypted platform gateway versions, never tenant storefront credentials. Selling prices are fixed per-cycle totals; infrastructure interpolation is not a billing basis. Serialize reservation and activation on the central owner before payment locks; ambiguous order creation remains reserved and is recovered by receipt without another POST. Preserve test/live separation, seven-day grace and exclusive calendar-aware expiry. users, user_packages and audit_logs must be InnoDB for atomic activation; keep renewal and after-sales routes accessible after expiry.
