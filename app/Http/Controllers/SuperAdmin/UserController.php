<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminUserStoreRequest;
use App\Http\Requests\SuperAdminUserUpdateRequest;
use App\Models\User;
use App\Repositories\UserDomainRepository;
use App\Repositories\UserRepository;
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
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserDomainRepository $userDomainRepository,
    ) {}

    /**
     * Display a paginated list of regular users.
     */
    public function index(Request $request): View
    {
        $users = $this->userRepository->paginateRegularUsers(
            10, $request->string('search')->trim()->toString(),
        );

        return view('super-admin.admin.index', compact('users'));
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): View
    {
        return view('super-admin.admin.add');
    }

    /**
     * Validate and store a user with their domains.
     */
    public function store(SuperAdminUserStoreRequest $request): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $user = $this->userRepository->createRegularUser([
                'name' => $request->string('first_name')->trim()->toString().' '.$request->string('last_name')->trim()->toString(),
                'first_name' => $request->string('first_name')->trim()->toString(),
                'last_name' => $request->string('last_name')->trim()->toString(),
                'email' => $request->string('email')->lower()->trim()->toString(),
                'mobile_number' => $request->string('mobile_number')->trim()->toString(),
                'status' => $request->string('status')->toString(),
                'password' => Str::random(32),
                'is_super_admin' => false,
            ]);

            $this->userDomainRepository->createForUser($user, $request->validated()['domains']);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User created successfully.',
                'redirect' => route('super-admin.admin.index'),
            ], 201);
        }

        return redirect()->route('super-admin.admin.index')->with('success', 'User created successfully.');
    }

    /**
     * User detail pages are not implemented yet.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing a user and their domains.
     */
    public function edit(User $user): View
    {
        $user = $this->userRepository->loadRegularUserForEdit($user);

        return view('super-admin.admin.edit', compact('user'));
    }

    /**
     * Validate and update a user and synchronize their domains.
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User updated successfully.',
                'redirect' => route('super-admin.admin.index'),
            ]);
        }

        return redirect()->route('super-admin.admin.index')->with('success', 'User updated successfully.');
    }

    /**
     * User deletion is not implemented yet.
     */
    public function destroy(string $id)
    {
        //
    }
}
