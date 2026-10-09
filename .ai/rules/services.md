---
paths:
  - 'app/Services/TenantOrder*.php'
  - 'app/Services/TenantCoupon*.php'
  - app/Services/TenantPaymentService.php
  - app/Services/TenantOrderService.php
  - 'app/Services/TenantInvoice*.php'
  - 'app/Services/Customer*.php'
---

# Services

## Order tax policy confirmed by the user

Orders use INR and tax-exclusive catalog prices with a single percentage tax. Do not infer CGST/SGST/IGST or jurisdiction rules from GSTIN. Shipping greater than zero requires staff to select an active existing tax; an explicitly configured zero rate is valid, but an unknown/unavailable tax is not zero-rated. Captured funds block cancellation until a refund workflow exists.

## Order tax policy confirmed by the user

Orders use INR and tax-exclusive catalog prices with a single percentage tax. Do not infer CGST/SGST/IGST or jurisdiction rules from GSTIN. Shipping cost and shipping GST are optional: omitted shipping cost is zero, and shipping with no selected tax has no shipping tax charge. If a shipping tax is explicitly selected, it must be active and available; an explicitly configured zero rate is valid. This supersedes the earlier requirement to select a tax for positive shipping charges. Captured funds block cancellation until a refund workflow exists.

## Coupon accounting and redemption lifecycle

Coupons apply to eligible net merchandise after manual discounts and before existing per-line tax; shipping is excluded. Product/category restrictions form a union, with customer restrictions additional. Drafts consume no usage; pending through delivered orders consume one unique order redemption, released only on cancellation allowed by the existing captured-funds policy. Lock the coupon, restrictions and usage inside the tenant order transaction; preserve submitted snapshots. Guest orders cannot use per-customer limits or customer restrictions.

## Customer order payment ownership and replay safety

Reuse tenant order_payments for collected funds; captured is the existing received-money status. One durable checkout per order owns the immutable full balance; retry the same provider order and recover uncertain creation by UUID receipt, never blindly create another. Hold the order lock for reconciliation and block offline collection/cancellation after checkout reservation. Verify raw webhooks before resolving tenants from server-fetched provider order notes; never route by caller-supplied tenant IDs. No automatic refunds or active-checkout cancellation exists.

## Collect payment before delivery

Delivery requires both paid payment status and captured payments equal to the order grand total, checked under the order lock through the shared transition service for admin and API. COD must be explicitly collected before delivery; changing fulfillment status must never imply payment collection.

## Preserve exceptional captures and recover without charging

Record verified duplicate and late captures as financial facts; flag the checkout for review and block fulfilment instead of dropping the payment or issuing a refund. Recovery must use the stored provider order or durable receipt and never create a replacement charge for an ambiguous outcome. Webhook acknowledgements follow committed event and ledger writes; polling provides recovery when callbacks or webhooks are missed.

## Invoice snapshots and approval
Invoice discount_amount already includes coupon_discount; never subtract or display their sum as the total discount. Preserve the order's single-tax snapshots without inventing CGST/SGST/IGST. Dispatch approval is explicit and separate from payment; prepaid issuance needs fully captured funds, COD does not. Invoice work locks the order before the financial-year sequence, and runs synchronously after approval commits while tenant middleware owns the connection.

## Seller details are optional for invoice issuance
The user confirmed that missing seller configuration must not block invoice issuance. Snapshot whichever tenant seller details are available, preserve empty values without inventing an identity, and enforce order/payment/dispatch eligibility and financial consistency independently.

## Customer checkout recovery and cart conversion
Customer shopping runs on the bearer-token-selected tenant database and serializes mutations on the customer row. Reuse TenantOrderService, TenantOrderCalculationService and customer.checkout idempotency; commit the confirmed order and cart_orders snapshot before any gateway request. COD converts at acceptance; other methods convert after trusted full payment. Preserve later cart edits by revision and block a second order for an unresolved cart.

## Customer actors are not tenant staff IDs
Shared order save/transition and gateway initiation accept User or Customer. Customer actors leave staff foreign keys null and use customer identity in audit metadata. Trusted payment writes schedule cart conversion after the tenant transaction commits, avoiding customer/order lock inversion; keep lazy cart synchronization as crash recovery.
