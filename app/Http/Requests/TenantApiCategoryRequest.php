<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantCategoryRepository;
use App\Repositories\TenantRoleRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantApiCategoryRequest extends TenantCategorySaveRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $permission = match ($this->route()->getActionMethod()) {
            'create', 'store' => 'categories.create',
            'edit', 'update' => 'categories.update',
            default => 'categories.view',
        };
        $user = $this->user('sanctum');
        if (! $user instanceof User || $user->status !== 'active'
            || ! app(TenantRoleRepository::class)->userHasPermission($user, $permission)) {
            throw new HttpResponseException(response()->json([
                'message' => 'You need '.$permission.' permission to access this action.',
                'error_code' => 403,
            ], 403));
        }
        if ($user->is_first_login) {
            throw new HttpResponseException(response()->json([
                'message' => 'Please change your password before accessing categories.',
                'error_code' => 403,
            ], 403));
        }

        if ($this->route('category') !== null) {
            try {
                if (! ctype_digit((string) $this->route('category'))) {
                    throw new NotFoundHttpException;
                }
                app(TenantCategoryRepository::class)->find((int) $this->route('category'));
            } catch (NotFoundHttpException) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Category not found.', 'error_code' => 404,
                ], 404));
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : parent::rules();
    }
}
