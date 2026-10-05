<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * Record a multi-table change on the same connection as the affected model.
     *
     * @param  array<string, mixed>|null  $oldValues  The previous aggregate state.
     * @param  array<string, mixed>|null  $newValues  The saved aggregate state.
     */
    public function recordSnapshot(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        $user = Auth::guard('tenant')->user() ?? Auth::guard('sanctum')->user();
        $log = new AuditLog;
        $log->setConnection($model->getConnectionName());
        $log->fill([
            'user_id' => $user instanceof User ? $user->getAuthIdentifier() : null,
            'action' => $action,
            'module' => $model->auditModule(),
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => Str::headline(class_basename($model)) . ' ' . $action,
        ])->save();
    }

    /**
     * @var list<string>
     */
    private const EXCLUDED_FIELDS = [
        'id',
        'password',
        'remember_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function recordCreated(Model $model): void
    {
        $this->store($model, 'created', null, $this->filterValues($model, $model->getAttributes()));
    }

    public function recordUpdated(Model $model): void
    {
        $rawChanges = $this->filterValues($model, $model->getChanges(), false);

        if ($rawChanges === []) {
            return;
        }

        $rawOldValues = [];
        foreach (array_keys($rawChanges) as $field) {
            $rawOldValues[$field] = $model->getOriginal($field);
        }

        $this->store(
            $model,
            'updated',
            $model->auditValues($rawOldValues),
            $model->auditValues($rawChanges),
        );
    }

    public function recordDeleted(Model $model): void
    {
        $this->store($model, 'deleted', $this->filterValues($model, $model->getOriginal()), null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function store(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'module' => $model->auditModule(),
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => Str::headline(class_basename($model)) . ' ' . $action,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function filterValues(Model $model, array $values, bool $transform = true): array
    {
        $filteredValues = array_diff_key($values, array_flip(self::EXCLUDED_FIELDS));

        return $transform ? $model->auditValues($filteredValues) : $filteredValues;
    }
}
