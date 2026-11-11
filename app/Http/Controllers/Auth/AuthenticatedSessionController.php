<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => auth()->user()?->getMorphClass(),
            'subject_id' => auth()->id(),
            'action' => 'login',
            'description' => 'User logged in',
            'changes' => ['email' => $request->input('email')],
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($user) {
            ActivityLog::create([
                'user_id' => $user->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => 'logout',
                'description' => 'User logged out',
                'changes' => [],
            ]);
        }

        return redirect('/');
    }
}
