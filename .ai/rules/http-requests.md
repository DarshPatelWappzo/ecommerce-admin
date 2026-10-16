---
paths:
  - 'app/Repositories/TenantDashboardRepository.php, app/Services/TenantDashboardService.php, app/Http/Requests/TenantDashboardRequest.php'
---

# Http Requests

## Tenant dashboard reports only authorized local ledger data
Dashboard reporting is read-only on the resolved tenant connection and requires dashboard.view plus each section's existing module view permission. INR revenue uses captured payments by paid_at; refunds use processed refunds by processed_at. Payment Status counts attempts by creation date, not orders or refunded balances. Catalog/low stock are current snapshots, so do not invent historical growth. Return/replacement rates count distinct eligible orders in the selected order-date cohort; exclude drafts/cancelled orders and rejected/cancelled requests. Date ranges are bounded to 366 days and prior-period comparisons use equal-length ranges.
