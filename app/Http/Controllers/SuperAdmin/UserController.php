<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminUserStoreRequest;
use App\Http\Requests\SuperAdminUserUpdateRequest;
use App\Models\User;
use App\Repositories\UserDomainRepository;
use App\Repositories\UserRepository;
use App\Services\TenantProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Create a user controller with user and domain repositories.
     *
     * @param  UserRepository  $userRepository  The injected user repository.
     * @param  UserDomainRepository  $userDomainRepository  The injected user domain repository.
     * @param  TenantProvisioningService  $tenantProvisioningService  The injected tenant provisioning service.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserDomainRepository $userDomainRepository,
        private readonly TenantProvisioningService $tenantProvisioningService,
    ) {}

    /**
     * Display a paginated list of regular users.
     *
     * @param  Request  $request  The incoming request.
     * @return View The response for this action.
     */
    public function index(Request $request): View
    {
        $users = $this->userRepository->paginateRegularUsers(
            10,
            $request->string('search')->trim()->toString(),
        );

        $data = [];
        $data['users'] = $users;

        return view('super-admin.admin.index', $data);
    }

    /**
     * Show the form for creating a user.
     *
     * @return View The response for this action.
     */
    public function create(): View
    {
        return view('super-admin.admin.add');
    }

    /**
     * Validate and store a user with their domains.
     *
     * @param  SuperAdminUserStoreRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function store(SuperAdminUserStoreRequest $request): RedirectResponse|JsonResponse
    {
        $plainPassword = Str::ucfirst(Str::lower(Str::password(5, letters: true, numbers: false, symbols: false))).'@'.random_int(100, 999);
        $centralUser = DB::transaction(function () use ($request, $plainPassword): User {
            $centralUser = $this->userRepository->createRegularUser([
                'name' => $request->string('first_name')->trim()->toString().' '.$request->string('last_name')->trim()->toString(),
                'first_name' => $request->string('first_name')->trim()->toString(),
                'last_name' => $request->string('last_name')->trim()->toString(),
                'email' => $request->string('email')->lower()->trim()->toString(),
                'mobile_number' => $request->string('mobile_number')->trim()->toString(),
                'status' => $request->string('status')->toString(),
                'password' => $plainPassword,
                'is_super_admin' => false,
            ]);

            $this->userDomainRepository->createForUser($centralUser, $request->validated()['domains']);

            return $centralUser->load('domains');
        });

        foreach ($centralUser->domains as $domain) {
            $this->tenantProvisioningService->provision($centralUser, $domain, $plainPassword);
        }

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'User created successfully.';
            $data['redirect'] = route('super-admin.admin.index');

            return response()->json($data, 201);
        }

        return redirect()->route('super-admin.admin.index')->with('success', 'User created successfully.');
    }

    /**
     * Placeholder for the show action; no behavior is implemented yet.
     *
     * @param  string  $id  The id used by this action.
     * @return void No return value.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing a user and their domains.
     *
     * @param  User  $user  The user used by this action.
     * @return View The response for this action.
     */
    public function edit(User $user): View
    {
        $user = $this->userRepository->loadRegularUserForEdit($user);

        $data = [];
        $data['user'] = $user;

        return view('super-admin.admin.edit', $data);
    }

    /**
     * Validate and update a user and synchronize their domains.
     *
     * @param  SuperAdminUserUpdateRequest  $request  The incoming request.
     * @param  User  $user  The user used by this action.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function update(SuperAdminUserUpdateRequest $request, User $user): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $this->userRepository->updateRegularUser($user, [
                'name' => $request->string('first_name')->trim()->toString().' '.$request->string('last_name')->trim()->toString(),
                'first_name' => $request->string('first_name')->trim()->toString(),
                'last_name' => $request->string('last_name')->trim()->toString(),
                'mobile_number' => $request->string('mobile_number')->trim()->toString(),
                'status' => $request->string('status')->toString(),
            ]);

            $this->userDomainRepository->syncForUser($user, $request->validated()['domains']);
        });

        $newDomainPassword = Str::ucfirst(Str::lower(Str::password(5, letters: true, numbers: false, symbols: false))).'@'.random_int(100, 999);
        $user->load('domains.tenantDatabase');
        foreach ($user->domains as $domain) {
            if ($domain->tenantDatabase === null) {
                $this->tenantProvisioningService->provision($user, $domain, $newDomainPassword);
            }
        }

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'User updated successfully.';
            $data['redirect'] = route('super-admin.admin.index');

            return response()->json($data);
        }

        return redirect()->route('super-admin.admin.index')->with('success', 'User updated successfully.');
    }

    /**
     * Placeholder for the destroy action; no behavior is implemented yet.
     *
     * @param  string  $id  The id used by this action.
     * @return void No return value.
     */
    public function destroy(string $id)
    {
        //
    }
}
