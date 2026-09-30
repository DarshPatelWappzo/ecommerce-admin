---
paths:
    - "app/Services/TenantOrder*.php"
    - "app/Services/TenantCoupon*.php"
    - app/Services/TenantPaymentService.php
    - app/Services/TenantOrderService.php
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
