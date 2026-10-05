<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantApiUserStoreRequest;
use App\Http\Requests\TenantApiUserUpdateRequest;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use App\Repositories\TenantUserRepository;
use App\Services\TenantUserCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserController extends Controller
{
    /**
     * Initialize the controller dependencies.
     *
     * @param  TenantUserRepository  $tenantUserRepository  The injected tenant user repository.
     * @param  TenantRoleRepository  $tenantRoleRepository  The injected tenant role repository.
     */
    public function __construct(
        private readonly TenantUserRepository $tenantUserRepository,
        private readonly TenantRoleRepository $tenantRoleRepository,
    ) {}

    /**
     * List the tenant user records.
     *
     * @param  Request  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function index(Request $request): JsonResponse
    {
        if ($error = $this->authorizeUserAccess($request)) {
            return $error;
        }

        $data = $this->tenantUserRepository->paginate(10);

        return response()->json($data);
    }

    /**
     * Create a tenant user from the validated request.
     *
     * @param  TenantApiUserStoreRequest  $request  The incoming request.
     * @param  TenantUserCreationService  $creationService  The creation service used by this action.
     * @return JsonResponse The response for this action.
     */
    public function store(TenantApiUserStoreRequest $request, TenantUserCreationService $creationService): JsonResponse
    {
        $domain = $request->user('sanctum')->currentAccessToken()->tenantDatabase->domain->domain_name;
        $user = $creationService->create($request->validated(), $domain);

        $data = [];
        $data['message'] = 'User created successfully.';
        $data['data'] = $user->load('roles:id,name');

        return response()->json($data, 201);
    }

    /**
     * Return the specified tenant user.
     *
     * @param  Request  $request  The incoming request.
     * @param  string  $user  The user used by this action.
     * @return JsonResponse The response for this action.
     */
    public function show(Request $request, string $user): JsonResponse
    {
        if ($error = $this->authorizeUserAccess($request)) {
            return $error;
        }

        $tenantUser = ctype_digit($user) ? $this->tenantUserRepository->findById($user) : null;

        if (! $tenantUser) {
            $data = [];
            $data['message'] = 'User not found.';
            $data['error_code'] = 404;

            return response()->json($data, 404);
        }

        $data = [];
        $data['data'] = $tenantUser;

        return response()->json($data);
    }

    /**
     * Update the specified tenant user from the validated request.
     *
     * @param  TenantApiUserUpdateRequest  $request  The incoming request.
     * @param  string  $user  The user used by this action.
     * @return JsonResponse The response for this action.
     */
    public function update(TenantApiUserUpdateRequest $request, string $user): JsonResponse
    {
        $tenantUser = ctype_digit($user) ? $this->tenantUserRepository->findById($user) : null;

        if (! $tenantUser) {
            $data = [];
            $data['message'] = 'User not found.';
            $data['error_code'] = 404;

            return response()->json($data, 404);
        }

        $tenantUser = $this->tenantUserRepository->update($tenantUser, [
            'first_name' => $request->string('first_name')->trim()->toString(),
            'last_name' => $request->string('last_name')->trim()->toString(),
            'mobile_number' => $request->string('mobile_number')->trim()->toString(),
            'status' => $request->validated('status'),
        ], [$request->validated('role_id')]);

        $data = [];
        $data['message'] = 'User updated successfully.';
        $data['data'] = $tenantUser->load('roles:id,name');

        return response()->json($data);
    }

    /**
     * Delete the specified tenant user.
     *
     * @param  Request  $request  The incoming request.
     * @param  string  $user  The user used by this action.
     * @return JsonResponse The response for this action.
     */
    public function destroy(Request $request, string $user): JsonResponse
    {
        if ($error = $this->authorizeUserAccess($request, 'users.delete')) {
            return $error;
        }

        $tenantUser = ctype_digit($user) ? $this->tenantUserRepository->findById($user) : null;

        if (! $tenantUser) {
            $data = [];
            $data['message'] = 'User not found.';
            $data['error_code'] = 404;

            return response()->json($data, 404);
        }

        $this->tenantUserRepository->delete($tenantUser);

        $data = [];
        $data['message'] = 'User deleted successfully.';

        return response()->json($data);
    }

    /**
     * Check tenant user access and return an error response when access is denied.
     *
     * @param  Request  $request  The incoming request.
     * @param  string  $permission  The permission used by this action.
     * @return JsonResponse|null An error response when access is denied, or null when allowed.
     */
    private function authorizeUserAccess(Request $request, string $permission = 'users.view'): ?JsonResponse
    {
        $user = $request->user('sanctum');

        if (! $user instanceof User) {
            $data = [];
            $data['message'] = 'Please log in to continue.';
            $data['error_code'] = 401;

            return response()->json($data, 401);
        }

        if ($user->status !== 'active' || ! $this->tenantRoleRepository->userHasPermission($user, $permission)) {
            $data = [];
            $data['message'] = $permission === 'users.delete' ? 'You do not have permission to delete users.' : 'You do not have permission to view users.';
            $data['error_code'] = 403;

            return response()->json($data, 403);
        }

        if ($user->is_first_login) {
            $data = [];
            $data['message'] = 'Please change your password before accessing users.';
            $data['error_code'] = 403;

            return response()->json($data, 403);
        }

        return null;
    }
}
