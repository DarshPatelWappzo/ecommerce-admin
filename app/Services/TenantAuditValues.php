<?php

namespace App\Services;

use BackedEnum;
use DateTimeInterface;

class TenantAuditValues
{
    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    public function sanitize(array $values, int $depth = 0): array
    {
        if ($depth > 20) {
            return ['value' => '[omitted: nesting limit]'];
        }
        $safe = [];
        foreach ($values as $key => $value) {
            $field = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (in_array($field, ['createdat', 'updatedat', 'deletedat'], true)) {
                continue;
            }
            if (in_array($field, ['key', 'pin', 'pan'], true) || preg_match('/password|passwd|token|secret|apikey|keyid|privatekey|razorpaykey|authorization|cookie|session|card|cvv|cvc|otp|onetime|credential/', $field)) {
                $safe[$key] = '[redacted]';

                continue;
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                } else {
                    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                }
            }
            $safe[$key] = match (true) {
                is_array($value) => $this->sanitize($value, $depth + 1),
                $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
                $value instanceof BackedEnum => $value->value,
                is_float($value) && ! is_finite($value) => null,
                is_scalar($value), $value === null => $value,
                default => '[omitted: unsupported value]',
            };
        }

        return $safe;
    }

    /**
     * Compare normalized snapshots, preserving removed keys and explicit nulls.
     *
     * @param  array<string|int, mixed>  $old
     * @param  array<string|int, mixed>  $new
     * @return array{0: array<string|int, mixed>, 1: array<string|int, mixed>}
     */
    public function difference(array $old, array $new): array
    {
        $before = $after = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            $left = $old[$key] ?? null;
            $right = $new[$key] ?? null;
            if (array_key_exists($key, $old) && array_key_exists($key, $new) && $left === $right) {
                continue;
            }
            if (is_array($left) && is_array($right) && ! array_is_list($left) && ! array_is_list($right)) {
                [$left, $right] = $this->difference($left, $right);
                if ($left === [] && $right === []) {
                    continue;
                }
            }
            if (array_key_exists($key, $old)) {
                $before[$key] = $left;
            }
            if (array_key_exists($key, $new)) {
                $after[$key] = $right;
            }
        }

        return [$before, $after];
    }
}
