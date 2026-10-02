---
paths:
  - app/Http/Middleware/ResolveTenant.php
---

# Middleware

## Bind web authentication to the session tenant
All protected tenant web routes must resolve only tenant_domain from the session before auth:tenant loads its numeric user ID. Ignore request domain/header overrides, including login submissions when the tenant guard's session key is present. Inspect the session key via getName(), never guard check()/user() before connecting. Missing or inactive session tenants must fail closed; only unauthenticated web login may choose a tenant from request input. API login remains independent of web sessions.
