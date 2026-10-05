---
paths:
  - 'app/Http/Requests/Tenant*UserUpdateRequest.php'
---

# Requests

## Require user and role update permissions together
Web and API tenant user update requests always accept and synchronize role_id, so both users.update and roles.update are required, including updates to the caller's own record. API requests use the sanctum guard and retain the active-user and completed-first-login checks. Denied updates must leave both profile attributes and role assignments unchanged; authorized fixtures must grant both permissions to reach validation and not-found paths.
