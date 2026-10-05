<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['name', 'status', 'sort_order'])]
class ReturnReason extends TenantModel
{
    protected function casts(): array
    {
        return ['status' => 'boolean', 'sort_order' => 'integer'];
    }

    public function auditModule(): string
    {
        return 'return_reasons';
    }
}
