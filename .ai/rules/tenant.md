---
paths:
  - 'database/migrations/tenant/**'
---

# Tenant

## Do not inherit MyISAM for tenant foreign keys
The deployed MySQL server can default new tables to MyISAM. Explicitly use InnoDB for tenant tables participating in foreign keys or transactions. Failed MySQL DDL may leave tables and columns committed without constraints; recovery must preserve existing rows, check engines/columns/foreign keys separately, and resume pending migrations. Validate engine-specific recovery in an isolated MySQL test; SQLite alone does not expose this mismatch.

## Preserve foreign-key support when rolling back covering indexes
MySQL may silently remove an automatically created foreign-key index when a new composite index starts with the same columns. Before dropping that composite index in down(), preserve or create another supporting index. The shopping history rollback must retain customer_id indexing and the orders-to-customers foreign key. Verify rollback with real MySQL; SQLite does not enforce this index dependency.
