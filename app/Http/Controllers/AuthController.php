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
     * Obtém o host original, considerando proxies e HTTPS
     */
    private function getOriginalHost($request)
    {
        // Tentar várias formas de obter o host original
        $possibleHosts = [
            $request->header('X-Forwarded-Host'),      // Nginx, Apache
            $request->header('X-Original-Host'),       // Alguns proxies
            $request->header('X-Host'),                // Alguns load balancers
            $request->header('CF-Connecting-IP') ? $request->header('Host') : null, // Cloudflare
            $request->getHost(),                       // Padrão Laravel
        ];

        foreach ($possibleHosts as $host) {
            if ($host && $this->isValidHost($host)) {
                \Log::info('AuthController: Host encontrado', [
                    'host' => $host,
                    'method' => $this->getHostMethod($request, $host)
                ]);
                return strtolower(trim($host));
            }
        }

        // Fallback: usar o host padrão
        $defaultHost = $request->getHost();
        \Log::warning('AuthController: Usando host padrão como fallback', ['host' => $defaultHost]);
        return strtolower(trim($defaultHost));
    }

    /**
     * Verifica se o host é válido para o domínio esperado
     */
    private function isValidHost($host)
    {
        if (!$host || !is_string($host)) {
            return false;
        }

        // Verificar se é um dos domínios esperados
        return preg_match('/^(contasadmin|contas\.[a-z0-9-]+)\.sei\.pe\.gov\.br$/i', trim($host));
    }

    /**
     * Identifica qual método foi usado para obter o host (para debug)
     */
    private function getHostMethod($request, $host)
    {
        if ($request->header('X-Forwarded-Host') === $host) return 'X-Forwarded-Host';
        if ($request->header('X-Original-Host') === $host) return 'X-Original-Host';
        if ($request->header('X-Host') === $host) return 'X-Host';
        if ($request->getHost() === $host) return 'getHost()';
        return 'unknown';
    }

    /**
     * Busca robusta de usuário por OU
     * Tenta diferentes métodos para encontrar o usuário na OU especificada
     */
    private function findUserInOu($uid, $ou)
    {
        \Log::info('AuthController: Iniciando busca robusta de usuário', [
            'uid' => $uid,
            'ou' => $ou
        ]);

        // Garantir que LdapRecord está inicializado
        $this->ensureLdapRecordInitialized();

        $baseDn = config('ldap.connections.default.base_dn');

        // Método 1: Busca tradicional por atributo 'ou' (compatibilidade)
        try {
            $user = \App\Ldap\LdapUserModel::where('uid', $uid)
                ->where('ou', $ou)
                ->first();
            
            if ($user) {
                \Log::info('AuthController: Usuário encontrado via método 1 (atributo ou)', [
                    'dn' => $user->getDn()
                ]);
                return $user;
            }
        } catch (\Exception $e) {
            \Log::warning('AuthController: Método 1 falhou', ['error' => $e->getMessage()]);
        }

        // Método 2: Busca direta por DN construído
        try {
            $expectedDn = "uid={$uid},ou={$ou},{$baseDn}";
            $user = \App\Ldap\LdapUserModel::find($expectedDn);
            
            if ($user) {
                \Log::info('AuthController: Usuário encontrado via método 2 (DN direto)', [
                    'dn' => $user->getDn()
                ]);
                return $user;
            }
        } catch (\Exception $e) {
            \Log::warning('AuthController: Método 2 falhou', ['error' => $e->getMessage()]);
        }

        // Método 3: Busca em base específica da OU
        try {
            $user = \App\Ldap\LdapUserModel::in("ou={$ou},{$baseDn}")
                ->where('uid', $uid)
                ->first();
            
            if ($user) {
                \Log::info('AuthController: Usuário encontrado via método 3 (base específica)', [
                    'dn' => $user->getDn()
                ]);
                return $user;
            }
        } catch (\Exception $e) {
            \Log::warning('AuthController: Método 3 falhou', ['error' => $e->getMessage()]);
        }

        // Método 4: Busca geral e filtragem por DN
        try {
            $users = \App\Ldap\LdapUserModel::where('uid', $uid)->get();
            
            foreach ($users as $user) {
                $dn = $user->getDn();
                // Verificar se o DN contém a OU especificada
                if (stripos($dn, "ou={$ou},") !== false) {
                    \Log::info('AuthController: Usuário encontrado via método 4 (filtragem DN)', [
                        'dn' => $dn
                    ]);
                    return $user;
                }
            }
        } catch (\Exception $e) {
            \Log::warning('AuthController: Método 4 falhou', ['error' => $e->getMessage()]);
        }

        \Log::warning('AuthController: Usuário não encontrado em nenhum método', [
            'uid' => $uid,
            'ou' => $ou
        ]);
        
        return null;
    }

    /**
     * Garante que o LdapRecord Container está inicializado
     */
    private function ensureLdapRecordInitialized()
    {
        try {
            $connection = \LdapRecord\Container::getDefaultConnection();
            
            if (!$connection) {
                \Log::info('AuthController: Inicializando LdapRecord Container');
                
                $config = config('ldap.connections.default');
                \LdapRecord\Container::addConnection($config, 'default');
                \LdapRecord\Container::setDefaultConnection('default');
                
                \Log::info('AuthController: LdapRecord Container inicializado');
            }
        } catch (\Exception $e) {
            \Log::warning('AuthController: Erro ao inicializar LdapRecord', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extrai a OU do subdomínio da URL
     * Exemplo: contas.moreno.sei.pe.gov.br => moreno
     * Para contasadmin.sei.pe.gov.br => admin (usuário root)
     */
    private function extractOuFromHost($host)
    {
        // Log do host recebido para debug
        \Log::info('AuthController: Host recebido', ['host' => $host]);
        
        // Caso especial para usuários root
        if ($host === 'contasadmin.sei.pe.gov.br') {
            \Log::info('AuthController: Detectado usuário root');
            return 'admin';
        }
        
        // Para outras OUs: contas.moreno.sei.pe.gov.br => moreno
        if (preg_match('/contas\\.([a-z0-9-]+)\\.sei\\.pe\\.gov\\.br/i', $host, $matches)) {
            $ou = $matches[1];
            \Log::info('AuthController: OU extraída', ['ou' => $ou, 'host' => $host]);
            return $ou;
        }
        
        \Log::warning('AuthController: Não foi possível extrair OU do host', ['host' => $host]);
        return null;
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'uid' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $host = $this->getOriginalHost($request);
        $ou = $this->extractOuFromHost($host);
        if (!$ou) {
            return back()->withErrors(['uid' => 'URL inválida para login.'])->onlyInput('uid');
        }

        // Buscar usuário - lógica diferente para root vs outros usuários
        if ($ou === 'admin') {
            // Para usuários root: buscar apenas pelo uid (estão na raiz do LDAP)
            $user = \App\Ldap\LdapUserModel::where('uid', $credentials['uid'])->first();
        } else {
            // Para outros usuários: usar busca robusta por OU
            $user = $this->findUserInOu($credentials['uid'], $ou);
        }

        if (!$user) {
            if ($ou === 'admin') {
                return back()->withErrors(['uid' => 'Usuário root não encontrado.'])->onlyInput('uid');
            } else {
                return back()->withErrors(['uid' => "Usuário não encontrado para a OU '{$ou}'."])->onlyInput('uid');
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
            if ($ou !== 'admin') {
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
            $host = $this->getOriginalHost($request);
            
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