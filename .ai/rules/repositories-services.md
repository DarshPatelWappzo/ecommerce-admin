---
paths:
  - 'app/Repositories/PlatformPaymentHistoryRepository.php, app/Services/PlatformPaymentHistoryService.php'
---

# Repositories Services

## Platform payment reports count the central ledger once
Use subscriptions.connection explicitly for every reporting lookup. Count captured package_payments once, including resolution captures; never join one-to-many domains/events into the aggregate. Summary ignores financial status filters, uses paid_at dates, and groups by currency with live default. Table dates use capture time for captured rows and creation time otherwise. Identity fields are current; package quotes are snapshots. No platform refunds or reliable purchase type are recorded. Exports use safe allowlists, formula-neutralized CSV and bounded ID chunks; reporting never reconciles or mutates payments.
