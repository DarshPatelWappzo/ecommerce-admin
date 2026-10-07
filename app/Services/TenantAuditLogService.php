<?php

namespace App\Services;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Customer;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TenantAuditLogService
{
    public function __construct(private readonly TenantAuditValues $values) {}

    /**
     * Record an explicit business event on the active tenant connection.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $module, string $action, ?Model $auditable = null, array $oldValues = [], array $newValues = [], ?string $description = null, array $metadata = [], User|Customer|null $actor = null): void
    {
        if (! config('database.connections.tenant.database') || ($auditable && $auditable->getConnectionName() !== 'tenant')) {
            throw new InvalidArgumentException('Audit records require an active tenant connection and a tenant model.');
        }
        $actor ??= Auth::guard('tenant')->user() ?? Auth::guard('sanctum')->user();
        $oldValues = $this->values->sanitize($oldValues);
        $newValues = $this->values->sanitize($newValues);
        if ($oldValues !== [] && $newValues !== [] && ! in_array($action, [TenantAuditAction::CREATED, TenantAuditAction::DELETED], true)) {
            [$oldValues, $newValues] = $this->values->difference($oldValues, $newValues);
            if ($oldValues === [] && $newValues === []) {
                return;
            }
        }
        $reference = $auditable?->getAttribute('order_number') ?? $auditable?->getAttribute('replacement_number')
            ?? $auditable?->getAttribute('refund_number') ?? $auditable?->getAttribute('return_number') ?? $auditable?->getAttribute('number')
            ?? $auditable?->getAttribute('customer_code') ?? $auditable?->getAttribute('code')
            ?? $auditable?->getAttribute('sku') ?? $auditable?->getAttribute('name')
            ?? ($auditable instanceof User ? trim($auditable->first_name.' '.$auditable->last_name) : $auditable?->getKey());
        $metadata = $this->values->sanitize([
            ...$metadata,
            'reference' => $reference,
            'actor_name' => $actor instanceof User ? trim($actor->first_name.' '.$actor->last_name) : null,
            'actor_type' => $actor instanceof User ? 'staff' : ($actor instanceof Customer ? 'customer' : 'system'),
            ...($actor instanceof Customer ? ['customer_id' => $actor->id] : []),
        ]);
        $description ??= $this->describe($module, $action, $reference, $oldValues, $newValues);

        AuditLog::create([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'module' => $module,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'description' => mb_convert_encoding($description, 'UTF-8', 'UTF-8'),
            'metadata' => $metadata,
            'ip_address' => request()->route() ? request()->ip() : null,
            'user_agent' => request()->route() ? mb_substr(mb_convert_encoding(request()->userAgent() ?? '', 'UTF-8', 'UTF-8'), 0, 2000) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function describe(string $module, string $action, string|int|null $reference, array $old, array $new): string
    {
        $label = Str::headline(Str::singular($module)).' '.($reference ?? 'event');
        foreach (['status', 'is_active'] as $field) {
            if (array_key_exists($field, $old) && array_key_exists($field, $new) && is_scalar($old[$field]) && is_scalar($new[$field])) {
                $from = is_bool($old[$field]) ? ($old[$field] ? 'active' : 'inactive') : $old[$field];
                $to = is_bool($new[$field]) ? ($new[$field] ? 'active' : 'inactive') : $new[$field];

                return $label.' status changed from '.$from.' to '.$to.'.';
            }
        }
        if (in_array($action, [TenantAuditAction::ROLE_ASSIGNED, TenantAuditAction::ROLE_REMOVED], true)) {
            $roles = implode(', ', array_values(($new['roles'] ?? $old['roles']) ?? []));

            return $label.': '.Str::headline($action).' ('.$roles.').';
        }
        if ($action === TenantAuditAction::INVENTORY_ADJUSTED && isset($old['quantity'], $new['quantity'])) {
            return $label.' quantity changed from '.$old['quantity'].' to '.$new['quantity'].'.';
        }

        return $label.': '.Str::headline($action).'.';
    }

    /**
     * Compare existing workflow snapshots before writing one event.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function recordSnapshot(Model $model, string $action, ?array $oldValues, ?array $newValues, User|Customer|null $actor = null): void
    {
        $old = $this->values->sanitize($oldValues ?? []);
        $new = $this->values->sanitize($newValues ?? []);
        if ($oldValues !== null && $newValues !== null && $old === $new) {
            return;
        }
        $module = method_exists($model, 'auditModule') ? $model->auditModule() : $model->getTable();
        $this->log($module, $action, $model, $old, $new, actor: $actor);
    }
}
