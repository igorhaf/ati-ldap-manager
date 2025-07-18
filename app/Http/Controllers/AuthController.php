<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Services\RoleResolver;

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

            $user = Auth::user();
            $role = RoleResolver::resolve($user);

            // Verificar se usuário root está tentando acessar pela URL correta
            if ($role === RoleResolver::ROLE_ROOT) {
                $host = $request->getHost();
                
                if ($host !== 'contasadmin.sei.pe.gov.br') {
                    // Fazer logout do usuário
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    
                    return back()->withErrors([
                        'uid' => 'O acesso a este usuário não pode ser feito por essa URL'
                    ])->onlyInput('uid');
                }
            }

            if ($role === RoleResolver::ROLE_USER) {
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

    /**
     * Verificar se usuário root pode acessar a URL atual
     */
    private function checkRootAccess(Request $request)
    {
        if (!auth()->check()) {
            return true;
        }

        $user = auth()->user();
        $role = RoleResolver::resolve($user);

        if ($role === RoleResolver::ROLE_ROOT) {
            $host = $request->getHost();
            
            if ($host !== 'contasadmin.sei.pe.gov.br') {
                if ($request->expectsJson()) {
                    abort(403, 'Usuários root só podem acessar via contasadmin.sei.pe.gov.br');
                }
                
                abort(403, 'Usuários root só podem acessar via contasadmin.sei.pe.gov.br');
            }
        }

        return true;
    }
} 