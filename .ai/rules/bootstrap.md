---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Select token tenant before route model binding
AuthenticateTenantToken must run before SubstituteBindings so tenant models are queried only after the bearer token selects the tenant database. Regression tests must keep the tenant connection unavailable until the connection manager runs; preconnected SQLite fixtures alone hide ordering failures.
