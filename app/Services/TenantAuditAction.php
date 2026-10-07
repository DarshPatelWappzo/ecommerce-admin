<?php

namespace App\Services;

final class TenantAuditAction
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public const RESTORED = 'restored';

    public const STATUS_CHANGED = 'status_changed';

    public const CANCELLED = 'cancelled';

    public const GATEWAY_PAYMENT_RECONCILED = 'gateway_payment_reconciled';

    public const PAYMENT_UPDATED = 'payment_updated';

    public const INVOICE_GENERATED = 'invoice_generated';

    public const INVENTORY_ADJUSTED = 'inventory_adjusted';

    public const ROLE_ASSIGNED = 'role_assigned';

    public const ROLE_REMOVED = 'role_removed';

    public const PERMISSION_CHANGED = 'permission_changed';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REQUESTED = 'requested';

    public const COMPLETED = 'completed';
}
