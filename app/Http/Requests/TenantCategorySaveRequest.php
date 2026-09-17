<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantCategoryRepository;
use App\Repositories\TenantRoleRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantCategorySaveRequest extends FormRequest
{
    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'category name', 'parent_id' => 'parent category'];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('tenant');

        return $user instanceof User && app(TenantRoleRepository::class)->userHasPermission(
            $user, $this->routeIs('tenant.categories.store') ? 'categories.create' : 'categories.update',
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable', 'integer', Rule::exists('tenant.categories', 'id')->whereNull('deleted_at'),
                Rule::notIn(app(TenantCategoryRepository::class)->excludedParentIds(
                    $this->route('category') === null ? null : (int) $this->route('category'),
                )),
            ],
            'status' => ['required', 'boolean'],
        ];
    }
}
