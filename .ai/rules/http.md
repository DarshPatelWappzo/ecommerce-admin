---
paths:
  - 'app/Http/**'
---

# Http

## Reject inactive staff throughout tenant web authentication
Tenant web login must include the server-controlled status=active condition. Every protected tenant web route runs EnsureActiveTenantUser after tenant resolution and auth:tenant, before model binding and action authorization. Disabled existing sessions are logged out, invalidated and have their CSRF token regenerated, then receive the standard authentication response (login redirect for HTML, 401 for JSON). Password-change and first-login routes are covered as well as role/category and other administration routes.
