<?php

namespace App\Services;

use App\Models\Tenant\Tax;
use App\Repositories\TenantTaxRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class TenantTaxService
{
    public function __construct(private readonly TenantTaxRepository $taxes) {}

    /** @param array{name: string, code: string, rate: string|int|float, description?: ?string, is_active: bool|int|string} $data */
    public function save(array $data, ?Tax $tax = null): Tax
    {
        try {
            return $this->taxes->save($data, $tax);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'The tax code has already been taken.']);
        }
    }
}
