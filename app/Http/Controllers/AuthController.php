<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'uid' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt(['uid' => $credentials['uid'], 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            $role = \App\Services\RoleResolver::resolve(Auth::user());
            if ($role === \App\Services\RoleResolver::ROLE_USER) {
                return redirect('/password-change');
            }

            return redirect()->intended('/ldap-manager');
        }

        return back()->withErrors(['uid' => 'Credenciais inválidas'])->onlyInput('uid');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
} 