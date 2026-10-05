---
paths:
  - 'app/Services/TenantReturn*.php, app/Services/TenantRefund*.php, app/Services/RazorpayRefundGateway.php'
---

# Services Services

## Item returns, financial reservations and legacy policy
Returns lock the tenant order before the item/return/refund rows, use shipment.delivered_at and purchase policy snapshots, and reserve active/completed quantities. Legacy unsnapshotted orders use the current product policy and freeze it on first return. Refund only original discounted merchandise plus item tax; shipping stays excluded. Reserve one refund per return before any external call, keep uncertain gateway submissions processing, reconcile by durable attempt receipt, and retry only verified failures with a new attempt. Only inspection disposition restock increases variant stock. Keep fulfillment status and original invoices unchanged.
