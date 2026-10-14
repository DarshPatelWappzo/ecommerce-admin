---
paths:
  - 'app/Repositories/PlatformPaymentHistoryRepository.php, app/Services/PlatformPaymentHistoryService.php'
  - 'app/Repositories/SuperAdminDashboardRepository.php, app/Services/SuperAdminDashboardService.php'
---

# Repositories Services

## Platform payment reports count the central ledger once
Use subscriptions.connection explicitly for every reporting lookup. Count captured package_payments once, including resolution captures; never join one-to-many domains/events into the aggregate. Summary ignores financial status filters, uses paid_at dates, and groups by currency with live default. Table dates use capture time for captured rows and creation time otherwise. Identity fields are current; package quotes are snapshots. No platform refunds or reliable purchase type are recorded. Exports use safe allowlists, formula-neutralized CSV and bounded ID chunks; reporting never reconciles or mutates payments.

## Dashboard reports business accounts and live gross collections
Count non-deleted central non-super-admin accounts once; subscription status selects one started entitlement per owner using TenantSubscriptionRepository precedence and TenantSubscriptionService status, excluding future-only terms. Report live captured package_payments by paid_at and purchased package_id, keep currencies separate, and state that platform refunds/net revenue and historical active-count growth are unavailable. Preserve the CSS/SVG dashboard and use authenticated validated partial-chart refreshes.
