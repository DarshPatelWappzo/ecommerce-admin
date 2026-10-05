<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TenantReturnActionRequest extends TenantReturnRequest
{
    public function rules(): array
    {
        return self::rulesFor($this->route()->getActionMethod());
    }

    public static function rulesFor(string $action): array
    {
        $common = ['amount' => ['prohibited'], 'refund_amount' => ['prohibited'], 'gateway_refund_id' => ['prohibited']];

        return [...$common, ...match ($action) {
            'transition' => [
                'status' => ['required', Rule::in(['approved', 'rejected', 'in_transit', 'received', 'cancelled', 'inspection_passed', 'inspection_failed', 'closed'])],
                'rejection_reason' => ['required_if:status,rejected,inspection_failed', 'nullable', 'string', 'max:2000'],
                'admin_note' => ['nullable', 'string', 'max:2000'],
                'inventory_disposition' => ['required_if:status,inspection_passed', 'nullable', Rule::in(['restock', 'damaged', 'defective', 'quarantine', 'do_not_restock'])],
            ],
            'manual' => ['manual_method' => ['required', Rule::in(['bank_transfer', 'upi', 'cash', 'other'])], 'reference_number' => ['required', 'string', 'max:100'], 'refund_date' => ['required', 'date', 'before_or_equal:now'], 'admin_note' => ['required', 'string', 'max:2000']],
            'saveReason' => ['id' => ['nullable', 'integer', Rule::exists('tenant.return_reasons', 'id')], 'name' => ['required', 'string', 'max:150'], 'status' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'between:0,65535']],
            default => [],
        }];
    }
}
