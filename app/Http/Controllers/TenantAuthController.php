<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantChangePasswordRequest;
use App\Http\Requests\TenantDashboardRequest;
use App\Http\Requests\TenantLoginRequest;
use App\Models\Tenant\User;
use App\Services\TenantDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TenantAuthController extends Controller
{
    /** Initialize read-only tenant reporting. */
    public function __construct(private readonly TenantDashboardService $dashboardService) {}

    /**
     * Show the tenant login form.
     *
     * @return View The response for this action.
     */
    public function showLogin(): View
    {
        return view('tenant.login');
    }

    /**
     * Show the tenant password change form.
     *
     * @return View The response for this action.
     */
    public function passwordForm(): View
    {
        return view('tenant.change-password');
    }

    /**
     * Show authorized store reports for the selected date range.
     *
     * @param  TenantDashboardRequest  $request  Authorized reporting filters.
     * @return View The response for this action.
     */
    public function dashboard(TenantDashboardRequest $request): View
    {
        $tenantUser = Auth::guard('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);

        $tenantUser->loadMissing('roles:id,name');

        $data = [];
        $data['tenantUser'] = $tenantUser;
        $data['tenantDomain'] = $request->session()->get('tenant_domain');
        $data['roleNames'] = $tenantUser->roles->pluck('name')->join(', ');
        $data['dashboard'] = $this->dashboardService->overview($tenantUser, $request->validated());

        return view('tenant.dashboard', $data);
    }

    /**
     * Authenticate the tenant user and start a session.
     *
     * @param  TenantLoginRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function login(TenantLoginRequest $request): RedirectResponse|JsonResponse
    {
        if (! Auth::guard('tenant')->attempt($request->safe()->only(['email', 'password']) + ['status' => 'active'])) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('domain', 'email');
        }

        $request->session()->regenerate();
        $tenantUser = Auth::guard('tenant')->user();

        if ($tenantUser instanceof User && $tenantUser->is_first_login) {
            return redirect()->route('tenant.password.form');
        }

        return redirect()->route('tenant.dashboard');
    }

    /**
     * Change the tenant password and complete the first-login requirement.
     *
     * @param  TenantChangePasswordRequest  $request  The incoming request.
     * @return RedirectResponse|JsonResponse The response for this action.
     */
    public function changePassword(TenantChangePasswordRequest $request): RedirectResponse|JsonResponse
    {
        $tenantUser = Auth::guard('tenant')->user();
        abort_unless($tenantUser instanceof User, 401);

        $tenantUser->update([
            'password' => Hash::make($request->validated('password')),
            'is_first_login' => false,
        ]);

        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = 'Password changed successfully.';

            return response()->json($data);
        }

        return redirect()->route('tenant.dashboard')->with('success', 'Password changed successfully.');
    }

    /**
     * Log out the tenant user and invalidate the session.
     *
     * @param  Request  $request  The incoming request.
     * @return RedirectResponse The response for this action.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login.form');
    }
}
