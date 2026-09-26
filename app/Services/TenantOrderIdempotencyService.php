<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TenantOrderIdempotencyService
{
    /**
     * Execute a tenant order action exactly once for the given principal and operation key.
     *
     * @param  int  $principal  The tenant principal identifier that owns the key scope.
     * @param  string  $operation  The operation namespace used for the idempotency key.
     * @param  string  $key  The client-supplied idempotency key.
     * @param  array  $payload  The request payload used to produce the canonical fingerprint.
     * @param  Closure  $action  The operation callback to run when the key is first seen.
     * @return array The stored response payload.
     */
    public function run(int $principal, string $operation, string $key, array $payload, Closure $action): array
    {
        $fingerprint = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));

        return DB::connection('tenant')->transaction(function () use ($principal, $operation, $key, $fingerprint, $action): array {
            $scope = ['principal_id' => $principal, 'operation' => $operation, 'key' => $key];
            $query = DB::connection('tenant')->table('order_idempotency_keys');
            $query->insertOrIgnore([...$scope, 'fingerprint' => $fingerprint, 'created_at' => now(), 'updated_at' => now()]);
            $record = $query->where($scope)->lockForUpdate()->first();
            if (! hash_equals($record->fingerprint, $fingerprint)) {
                throw new ConflictHttpException('This idempotency key was already used with different input.');
            }
            if ($record->response !== null) {
                return json_decode($record->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = json_encode($action(), JSON_THROW_ON_ERROR);
            DB::connection('tenant')->table('order_idempotency_keys')->where('id', $record->id)->update(['response' => $result, 'updated_at' => now()]);

            return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
        }, 3);
    }

    /**
     * Normalize nested payload arrays into a stable ordering for hashing.
     *
     * @param  array  $value  The payload data to canonicalize.
     * @return array The normalized payload array.
     */
    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }

        return $value;
    }
}
