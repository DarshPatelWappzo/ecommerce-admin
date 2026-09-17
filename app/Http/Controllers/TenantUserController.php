<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantUserStoreRequest;
use App\Http\Requests\TenantUserUpdateRequest;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use App\Repositories\TenantUserRepository;
use App\Services\TenantUserCreationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
     * @return View The response for this action.
     */
    public function index(Request $request): View
    {
        $this->ensureHasPermission('users.view');

        $data = $this->tenantViewData();
        $data['canDeleteUsers'] = $this->tenantRoleRepository->userHasPermission(auth('tenant')->user(), 'users.delete');
        $data['users'] = $this->tenantUserRepository->paginate(
            10,
            $request->string('search')->trim()->toString(),
        );

        return view('tenant.users.index', $data);
    }

    /**
     * Show the form for creating a tenant user.
     *
     * @return View The response for this action.
     */
    public function create(): View
    {
        $this->ensureHasPermission('users.create');

        $data = $this->tenantViewData();
        $data['user'] = $this->tenantUserRepository->make(['status' => 'active']);
        $data['roles'] = $this->tenantRoleRepository->allActive();
        $data['selectedRoleId'] = null;

        return view('tenant.users.form', $data);
    }

    /**
     * Create a tenant user from the validated request.
     *
     * @param  TenantUserStoreRequest  $request  The incoming request.
     * @param  TenantUserCreationService  $creationService  The creation service used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function store(TenantUserStoreRequest $request, TenantUserCreationService $creationService): RedirectResponse|JsonResponse
    {
        $this->ensureHasPermission('users.create');
        $creationService->create($request->validated(), (string) session('tenant_domain'));

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'User created successfully.';
            $data['redirect'] = route('tenant.users.index');

            return response()->json($data, 201);
        }

        return redirect()->route('tenant.users.index')->with('success', 'User created successfully.');
    }

    /**
     * Show the form for editing the specified tenant user.
     *
     * @param  User  $user  The user used by this action.
     * @return View The response for this action.
     */
    public function edit(User $user): View
    {
        $this->ensureHasPermission('users.update');
        $this->ensureHasPermission('roles.update');

        $data = $this->tenantViewData();
        $data['user'] = $user->load('roles:id');
        $data['roles'] = $this->tenantRoleRepository->allActive();
        $data['selectedRoleId'] = $this->tenantUserRepository->roleIds($user)[0] ?? null;

        return view('tenant.users.form', $data);
    }

    /**
     * Update the specified tenant user from the validated request.
     *
     * @param  TenantUserUpdateRequest  $request  The incoming request.
     * @param  User  $user  The user used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function update(TenantUserUpdateRequest $request, User $user): RedirectResponse|JsonResponse
    {
        $this->ensureHasPermission('users.update');
        $this->ensureHasPermission('roles.update');
        $this->tenantUserRepository->update($user, [
            'first_name' => $request->string('first_name')->trim()->toString(),
            'last_name' => $request->string('last_name')->trim()->toString(),
            'mobile_number' => $request->string('mobile_number')->trim()->toString(),
            'status' => $request->string('status')->toString(),
        ], [$request->validated('role_id')]);

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'User updated successfully.';
            $data['redirect'] = route('tenant.users.index');

            return response()->json($data);
        }

        return redirect()->route('tenant.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Delete the specified tenant user.
     *
     * @param  Request  $request  The incoming request.
     * @param  User  $user  The user used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function destroy(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $this->ensureHasPermission('users.delete');
        abort_unless(auth('tenant')->user()->status === 'active', 403);
        $this->tenantUserRepository->delete($user);

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'User deleted successfully.';

            return response()->json($data);
        }

        return redirect()->route('tenant.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Require the authenticated tenant user to have the requested permission.
     *
     * @param  string  $permission  The permission used by this action.
     * @return void No return value.
     */
    private function ensureHasPermission(string $permission): void
    {
        $tenantUser = auth('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);
        abort_unless($this->tenantRoleRepository->userHasPermission($tenantUser, $permission), 403);
    }

    /**
     * Build the shared tenant user and domain data for views.
     *
     * @return array{tenantUser: User, tenantDomain: mixed} The shared tenant view data.
     */
    private function tenantViewData(): array
    {
        $tenantUser = auth('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);

        $data = [];
        $data['tenantUser'] = $tenantUser;
        $data['tenantDomain'] = session('tenant_domain');

        return $data;
    }
}
