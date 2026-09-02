<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the super-admin login form or redirect an authenticated super-admin.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->is_super_admin) {
            return redirect()->route('super-admin.dashboard');
        }

        return view('super-admin.login');
    }

    /**
     * Authenticate a super-admin and start a new session.
     */
    public function store(SuperAdminLoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt([
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'is_super_admin' => true,
        ])) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('super-admin.dashboard'));
    }

    /**
     * Log out the current super-admin and invalidate the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super-admin.login');
    }
}
