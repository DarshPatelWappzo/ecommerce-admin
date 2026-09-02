<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminUserStoreRequest;
use App\Repositories\UserDomainRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserDomainRepository $userDomainRepository,
    ) {}

    public function store(SuperAdminUserStoreRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
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

            return $user->load('domains');
        });

        return response()->json([
            'message' => 'User created successfully.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'status' => $user->status,
                'domains' => $user->domains->pluck('domain_name')->values(),
            ],
        ], 201);
    }
}
