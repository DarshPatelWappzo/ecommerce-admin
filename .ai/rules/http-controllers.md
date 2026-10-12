---
paths:
  - 'app/Services/*Payment*.php, app/Services/Razorpay*.php, app/Services/TenantRefundService.php, app/Http/Controllers/OrderPaymentWebhookController.php'
---

# Http Controllers

## Tenant credential versions own merchant payment operations
Tenant Razorpay credentials are encrypted in the tenant database, never resolved from global config. Pin checkout and ledger rows to payment_gateway_credential_id; verification, recovery and refunds use that version even when new payments are disabled. Archive old values on rotation without duplicating current secrets. Webhook URLs select the central tenant and credential version before raw-body signature verification; provider notes must confirm the same tenant, never switch tenants. Unversioned legacy rows require explicit exact-key linking; never infer a new merchant account.
