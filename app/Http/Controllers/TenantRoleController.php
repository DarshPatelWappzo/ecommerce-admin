<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRoleStoreRequest;
use App\Http\Requests\TenantRoleUpdateRequest;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Repositories\TenantPermissionRepository;
use App\Repositories\TenantRoleRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class TenantRoleController extends Controller
{
    /**
     * Initialize the controller dependencies.
     *
     * @param  TenantRoleRepository  $tenantRoleRepository  The injected tenant role repository.
     * @param  TenantPermissionRepository  $tenantPermissionRepository  The injected tenant permission repository.
     */
    public function __construct(
        private readonly TenantRoleRepository $tenantRoleRepository,
        private readonly TenantPermissionRepository $tenantPermissionRepository,
    ) {}

    /**
     * List the tenant role records.
     *
     * @return View The response for this action.
     */
    public function index(): View
    {
        $this->ensureCanManageRoles('roles.view');
        $data = $this->tenantViewData();
        $data['roles'] = $this->tenantRoleRepository->allWithAssignmentCounts();

        return view('tenant.roles.index', $data);
    }

    /**
     * Show the form for creating a tenant role.
     *
     * @return View The response for this action.
     */
    public function create(): View
    {
        $this->ensureCanManageRoles('roles.create');
        $data = $this->tenantViewData();
        $data['role'] = $this->tenantRoleRepository->make(['status' => true]);
        $data['permissionsByModule'] = $this->tenantPermissionRepository->allGroupedByModule();
        $data['selectedPermissions'] = [];

        return view('tenant.roles.form', $data);
    }

    /**
     * Create a tenant role from the validated request.
     *
     * @param  TenantRoleStoreRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function store(TenantRoleStoreRequest $request): RedirectResponse|JsonResponse
    {
        $this->ensureCanManageRoles('roles.create');

        $this->tenantRoleRepository->create([
            'name' => $request->string('name')->trim()->toString(),
            'slug' => Str::slug($request->string('name')->toString()),
            'description' => $request->validated('description'),
            'status' => $request->boolean('status'),
        ], $request->validated('permissions', []));

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'Role created successfully.';
            $data['redirect'] = route('tenant.roles.index');

            return response()->json($data, 201);
        }

        return redirect()->route('tenant.roles.index')->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing the specified tenant role.
     *
     * @param  Role  $role  The role used by this action.
     * @return View The response for this action.
     */
    public function edit(Role $role): View
    {
        $this->ensureCanManageRoles('roles.update');
        $this->ensureRoleIsEditable($role);
        $data = $this->tenantViewData();
        $data['role'] = $role;
        $data['permissionsByModule'] = $this->tenantPermissionRepository->allGroupedByModule();
        $data['selectedPermissions'] = $this->tenantRoleRepository->permissionIds($role);

        return view('tenant.roles.form', $data);
    }

    /**
     * Update the specified tenant role from the validated request.
     *
     * @param  TenantRoleUpdateRequest  $request  The incoming request.
     * @param  Role  $role  The role used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function update(TenantRoleUpdateRequest $request, Role $role): RedirectResponse|JsonResponse
    {
        $this->ensureCanManageRoles('roles.update');
        $this->ensureRoleIsEditable($role);

        $this->tenantRoleRepository->update($role, [
            'name' => $request->string('name')->trim()->toString(),
            'slug' => Str::slug($request->string('name')->toString()),
            'description' => $request->validated('description'),
            'status' => $request->boolean('status'),
        ], $request->validated('permissions', []));

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'Role updated successfully.';
            $data['redirect'] = route('tenant.roles.index');

            return response()->json($data);
        }

        return redirect()->route('tenant.roles.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Delete the specified tenant role.
     *
     * @param  Role  $role  The role used by this action.
     * @return RedirectResponse The response for this action.
     */
    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureCanManageRoles('roles.delete');
        $this->ensureRoleIsEditable($role);

        if ($this->tenantRoleRepository->hasAssignedUsers($role)) {
            return back()->with('error', 'This role cannot be deleted while users are assigned to it.');
        }

        $this->tenantRoleRepository->delete($role);

        return back()->with('success', 'Role deleted successfully.');
    }

    /**
     * Require the authenticated tenant user to have the requested role permission.
     *
     * @param  string  $permission  The permission used by this action.
     * @return void No return value.
     */
    private function ensureCanManageRoles(string $permission): void
    {
        $user = auth('tenant')->user();
        abort_unless($user instanceof User, 401);
        abort_unless($this->tenantRoleRepository->userHasPermission($user, $permission), 403);
    }

    /**
     * Prevent changes to the protected admin role.
     *
     * @param  Role  $role  The role used by this action.
     * @return void No return value.
     */
    private function ensureRoleIsEditable(Role $role): void
    {
        abort_if($role->slug === 'admin', 403, 'The admin role cannot be edited or deleted.');
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
