<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminUserStoreRequest;
use App\Models\User;
use App\Repositories\UserDomainRepository;
use App\Repositories\UserRepository;
use App\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Initialize the controller dependencies.
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
     * Create a user from the validated request.
     *
     * @param  SuperAdminUserStoreRequest  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function store(SuperAdminUserStoreRequest $request): JsonResponse
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

        $data = [];
        $data['message'] = 'User created successfully.';
        $data['data'] = [
            'id' => $centralUser->id,
            'first_name' => $centralUser->first_name,
            'last_name' => $centralUser->last_name,
            'email' => $centralUser->email,
            'mobile_number' => $centralUser->mobile_number,
            'status' => $centralUser->status,
            'domains' => $centralUser->domains->pluck('domain_name')->values(),
        ];

        return response()->json($data, 201);
    }

    // for testing purpose only
    // fetch current users with their domains
    /**
     * List the user records.
     *
     * @return JsonResponse The response for this action.
     */
    public function index()
    {

        $users = User::with('domains')->get();

        $data = [];
        $data['data'] = $users->map(function ($user) {
            $data = [];
            $data['id'] = $user->id;
            $data['first_name'] = $user->first_name;
            $data['last_name'] = $user->last_name;
            $data['email'] = $user->email;
            $data['mobile_number'] = $user->mobile_number;
            $data['status'] = $user->status;
            $data['domains'] = $user->domains->pluck('domain_name')->values();

            return $data;
        });

        return response()->json($data);
    }
}
