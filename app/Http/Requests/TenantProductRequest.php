<?php

namespace App\Http\Requests;

use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class TenantProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'create', 'store' => 'products.create',
            'edit', 'update', 'saveTag', 'saveAttribute' => 'products.update',
            'destroy' => 'products.delete',
            default => 'products.view',
        };
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (
            ! $user instanceof User || $user->status !== 'active' || $user->is_first_login
            || ! app(TenantRoleRepository::class)->userHasPermission($user, $permission)
        ) {
            if ($this->is('api/*') || $this->expectsJson()) {
                throw new HttpResponseException(response()->json(['message' => 'You need ' . $permission . ' permission and an active account.', 'error_code' => 403], 403));
            }

            return false;
        }

        if ($this->route('product') !== null && (! ctype_digit((string) $this->route('product'))
            || ! Product::whereKey($this->route('product'))->exists())) {
            abort(404, 'Product not found.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
            'status' => ['nullable', 'boolean'],
            'product_type' => ['nullable', Rule::in(['simple', 'configurable'])],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', Rule::in(['in_stock', 'out_of_stock', 'low_stock'])],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
