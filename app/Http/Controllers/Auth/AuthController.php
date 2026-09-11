<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isAdmin()
                ? redirect()->route('admin.attendance.index')
                : redirect()->route('attendance.dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            if ($user->isPending()) {
                Auth::logout();
                return back()->withErrors(['email' => 'Your staff account is pending administrator approval. Please wait for an admin to approve your account.']);
            }

            // Smart Redirect: Admins land on Admin Dashboard; Staff land on Staff Portal
            if ($user->isAdmin()) {
                return redirect()->intended(route('admin.attendance.index'));
            }

            return redirect()->intended(route('attendance.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegisterForm()
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            return $user->isAdmin()
                ? redirect()->route('admin.attendance.index')
                : redirect()->route('attendance.dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'pending', // Requires Admin Approval
        ]);

        return redirect()->route('login')->with('success', 'Registration submitted successfully! Your account is pending administrator approval before you can sign in.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
