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

    /**
     * Extrai a OU do subdomínio da URL de forma dinâmica
     * Exemplos: 
     * - moreno.exemplo.com => moreno
     * - admin.sistema.br => admin  
     * - teste.localhost => teste
     */
    private function extractOuFromHost($host)
    {
        \Log::info('AuthController - Host: ' . $host);
        
        // Pegar apenas o primeiro subdomínio (antes do primeiro ponto)
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            $ou = strtolower($parts[0]);
            \Log::info('AuthController - OU extraída: ' . $ou);
            return $ou;
        }
        
        \Log::error('AuthController - Host inválido (sem subdomínio): ' . $host);
        return null;
    }

    /**
     * Obter o host da requisição
     */
    private function getRealHost(Request $request)
    {
        $host = $request->getHttpHost();
        return explode(':', $host)[0]; // Remove porta se houver
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'uid' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $host = $this->getRealHost($request);
        $ou = $this->extractOuFromHost($host);
        if (!$ou) {
            return back()->withErrors(['uid' => 'URL inválida para login.'])->onlyInput('uid');
        }

        // Buscar usuário - se OU for "admin", busca root, senão busca por OU
        if ($ou === 'admin') {
            // Para usuários root: buscar apenas pelo uid (estão na raiz do LDAP)
            $user = \App\Ldap\LdapUserModel::where('uid', $credentials['uid'])->first();
        } else {
            // Para outros usuários: buscar pelo uid e OU específica
            $user = \App\Ldap\LdapUserModel::where('uid', $credentials['uid'])
                ->where('ou', $ou)
                ->first();
        }

        if (!$user) {
            if ($ou === 'admin') {
                return back()->withErrors(['uid' => 'Usuário root não encontrado.'])->onlyInput('uid');
            } else {
                return back()->withErrors(['uid' => 'Usuário não encontrado para esta OU.'])->onlyInput('uid');
            }
        }

        // Verificar senha usando SSHA
        $storedPassword = $user->getFirstAttribute('userPassword');
        if (!\App\Utils\LdapUtils::verifySsha($credentials['password'], $storedPassword)) {
            return back()->withErrors(['uid' => 'Credenciais inválidas'])->onlyInput('uid');
        }

        // Login bem-sucedido
        Auth::login($user);
        $request->session()->regenerate();

        $role = $user->getFirstAttribute('employeeType') ?? 'user';
        $role = is_array($role) ? strtolower($role[0]) : strtolower($role);

        // Permissão root (mantém regra antiga)
        if ($role === 'root') {
            if ($host !== 'contasadmin.sei.pe.gov.br') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'uid' => 'O acesso a este usuário não pode ser feito por essa URL'
                ])->onlyInput('uid');
            }
        }

        if ($role === 'user') {
            return redirect('/password-change');
        }

        return redirect('/ldap-manager');
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