# Database Schema

The default database connection is SQLite. Schema changes are defined in `database/migrations` and can be applied with `php artisan migrate`.

## Relationship overview

```text
users 1 ────< user_domains
users 1 ────< user_packages >──── 1 packages
users 1 ────< audit_logs
packages 1 ────< audit_logs (polymorphic auditable record)
```

Audit logs use a polymorphic reference (`auditable_type`, `auditable_id`) so the same audit table can later support products, orders, categories, or other models.

## `users`

Authentication and user-management table.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key. |
| `name` | string | Display/full name. |
| `first_name` | string, nullable | User's first name. |
| `last_name` | string, nullable | User's last name. |
| `email` | string | Unique login email. |
| `mobile_number` | string(30), nullable | Contact number. |
| `email_verified_at` | timestamp, nullable | Laravel verification field. |
| `password` | string | Hashed password. |
| `remember_token` | string, nullable | Laravel session remember token. |
| `is_super_admin` | boolean | Defaults to `false`. |
| `status` | string(30) | Defaults to `active`. |
| `created_at`, `updated_at` | timestamps | Record timestamps. |
| `deleted_at` | timestamp, nullable | Soft-delete timestamp. |

Relationships: `User` has many `user_domains`, `user_packages`, and `audit_logs`.

## `packages`

Infrastructure-plan catalog. The original `create_packages_table` migration contains the complete package schema.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key. |
| `name` | string(100) | Package name. |
| `slug` | string(120) | Unique URL-friendly identifier. |
| `min_monthly_users` | unsigned big integer | Defaults to `0`. |
| `max_monthly_users` | unsigned big integer, nullable | Maximum supported monthly users. |
| `cpu_vcores` | decimal(5,2), nullable | CPU capacity. |
| `ram_gb` | decimal(8,2), nullable | RAM capacity in GB. |
| `application_servers` | unsigned small integer | Defaults to `1`. |
| `database_type` | string(100), nullable | Database technology/type. |
| `infrastructure_summary` | string | Short technical summary. |
| `min_monthly_cost` | decimal(12,2) | Minimum monthly price. |
| `max_monthly_cost` | decimal(12,2) | Maximum monthly price. |
| `currency` | char(3) | Defaults to `INR`. |
| `billing_period` | enum | `monthly` or `yearly`; defaults to `monthly`. |
| `bandwidth_gb` | decimal(10,2), nullable | Included bandwidth. |
| `storage_gb` | decimal(10,2), nullable | Included storage. |
| `backup_included` | boolean | Defaults to `false`. |
| `cdn_included` | boolean | Defaults to `false`. |
| `load_balancer_included` | boolean | Defaults to `false`. |
| `description` | text, nullable | General package description. |
| `cost_disclaimer` | text, nullable | Pricing exclusions or conditions. |
| `sort_order` | unsigned small integer | Defaults to `0`. |
| `is_recommended` | boolean | Defaults to `false`. |
| `status` | enum | `active` or `inactive`; defaults to `active`. |
| `created_at`, `updated_at` | timestamps | Record timestamps. |
| `deleted_at` | timestamp, nullable | Soft-delete timestamp. |

Indexes:

- Unique index on `slug`.
- Composite index on `status, sort_order`.
- Composite index on `min_monthly_users, max_monthly_users`.

Relationships: `Package` has many `user_packages` and is the auditable model currently enabled for automatic audit logging.

## `user_domains`

Domains associated with users.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key. |
| `user_id` | foreign key | References `users.id`; cascades on update/delete. |
| `domain_name` | string | Indexed domain name. |
| `created_at`, `updated_at` | timestamps | Record timestamps. |
| `deleted_at` | timestamp, nullable | Soft-delete timestamp. |

The application repository normalizes domain names to lowercase and soft-deletes domains removed during synchronization.

## `user_packages`

User-to-package assignments.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key. |
| `user_id` | foreign key | References `users.id`; cascades on update/delete. |
| `package_id` | foreign key | References `packages.id`; cascades on update/delete. |
| `start_date` | datetime | Assignment start. |
| `end_date` | datetime, nullable | Optional assignment end. |
| `status` | string(30) | Defaults to `active`; indexed. |
| `created_at`, `updated_at` | timestamps | Record timestamps. |
| `deleted_at` | timestamp, nullable | Soft-delete timestamp. |

Indexes:

- Composite index on `user_id, package_id`.
- Composite index on `start_date, end_date`.
- Index on `status`.

## `audit_logs`

Immutable application history created by the `Auditable` trait and `AuditLogService`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | big integer | Primary key. |
| `user_id` | foreign key, nullable | References `users.id`; set to `NULL` if the user is deleted. |
| `action` | string(50) | `created`, `updated`, or `deleted`. |
| `module` | string(100) | Audited model table/module, e.g. `packages`. |
| `auditable_type` | string | Fully qualified model class. |
| `auditable_id` | unsigned big integer | ID of the affected model record. |
| `old_values` | JSON, nullable | Previous values, primarily for updates/deletes. |
| `new_values` | JSON, nullable | New values, primarily for creates/updates. |
| `description` | text, nullable | Human-readable action description. |
| `created_at`, `updated_at` | timestamps | Audit timestamps. |

Indexes:

- Composite index on `auditable_type, auditable_id`.
- Index on `action`.
- Index on `module`.

Audit logs do not have a foreign key to the audited record, so deleting or soft-deleting a package does not remove its history. The actor relationship uses `withTrashed()` so a soft-deleted user can still be displayed.

## Tenant database mapping

The central `tenant_databases` table maps each central domain to one physical tenant database. `domain_id` is unique, and `database_name` is internally generated from numeric IDs, for example `tenant_15_42`.

Tenant databases independently contain `users`, `roles`, `permissions`, `role_user`, and `role_permissions`. The first tenant user receives the Admin role and uses `is_first_login` to enforce the temporary-password change.

## Laravel support tables

The migrations also create framework tables:

- `migrations`: records applied migrations.
- `sessions`: database-backed web sessions.
- `cache` and `cache_locks`: database cache storage and locks.
- `jobs`, `job_batches`, and `failed_jobs`: database queue support. The `failed_jobs.connection` and `failed_jobs.queue` columns are limited to 100 characters because they share a composite index and must fit MySQL's 1000-byte key limit.

- `password_reset_tokens`: password reset support.

## Main data flows

### Create user

`SuperAdminUserStoreRequest` validates the user and domain data → `UserRepository` inserts the user → `UserDomainRepository` inserts domains → the transaction commits both operations.

### Assign package

An application service/controller can create a `user_packages` row referencing an existing user and package. The foreign keys protect referential integrity.

### Audit package or domain

`Package`, `User`, or `UserDomain` model event → `Auditable` trait → `AuditLogService` → `audit_logs`. Updates use Eloquent dirty changes only; failed transactions roll back the audit insert together with the business operation. `UserDomain` records are grouped under the `users` module and map `domain_name` to `domain` in audit values.
