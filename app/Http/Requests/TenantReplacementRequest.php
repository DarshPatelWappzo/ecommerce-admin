<?php

namespace App\Http\Requests;

use App\Models\Tenant\ReplacementRequest;
use App\Models\Tenant\User;
use Illuminate\Validation\Rule;

class TenantReplacementRequest extends TenantReturnRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (! $user instanceof User || $user->status !== 'active' || $user->is_first_login) {
            return false;
        }
        $action = $this->route()->getActionMethod();
        $permission = match ($action) {
            'store' => 'replacements.create',
            'transition' => ReplacementRequest::permission((string) $this->input('status')),
            'refund' => 'refunds.initiate',
            default => 'replacements.view',
        };

        return $this->allowed($permission) && ($action !== 'refund' || $this->allowed('replacements.update_status'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed> Validated filters or action fields.
     */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        if ($action === 'store') {
            return self::creationRules();
        }
        if ($action === 'transition') {
            return self::transitionRules();
        }
        if ($action === 'refund') {
            return [];
        }

        return [...parent::rules(), 'status' => ['nullable', Rule::in(array_keys(ReplacementRequest::TRANSITIONS))], 'create_order_number' => ['nullable', 'string', 'max:40']];
    }

    /** @return array<string, mixed> Shared server and browser creation validation. */
    public static function creationRules(): array
    {
        return ['order_id' => ['required', 'integer'], 'order_item_id' => ['required', 'integer'], 'quantity' => ['required', 'integer', 'min:1'], 'reason_id' => ['required', 'integer', Rule::exists('tenant.return_reasons', 'id')->where('status', true)], 'reason_note' => ['nullable', 'string', 'max:2000']];
    }

    /** @return array<string, mixed> Shared server and browser action validation. */
    public static function transitionRules(): array
    {
        return [
            'status' => ['required', Rule::in(array_diff(array_keys(ReplacementRequest::TRANSITIONS), ['requested', 'out_of_stock', 'converted_to_refund']))],
            'admin_note' => ['required_if:status,rejected,qc_failed', 'nullable', 'string', 'max:2000'],
            'inventory_disposition' => ['required_if:status,qc_passed', 'nullable', Rule::in(ReplacementRequest::DISPOSITIONS)],
            'courier_name' => ['required_if:status,shipped', 'nullable', 'string', 'max:100'],
            'tracking_number' => ['required_if:status,shipped', 'nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:1000'],
        ];
    }
}
