<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\RoleResolver;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RestrictRootAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se o usuário está autenticado
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $role = RoleResolver::resolve($user);

        // Se o usuário é root, verificar se está acessando pela URL correta
        if ($role === RoleResolver::ROLE_ROOT) {
            $host = $this->getOriginalHost($request);
            
                    // Permitir apenas acesso via contas.sei.pe.gov.br
        if ($host !== 'contas.sei.pe.gov.br') {
                // Lançar exceção que será capturada pela página de erro 403
                throw new AccessDeniedHttpException('Usuários root só podem acessar via contas.sei.pe.gov.br');
            }
        }

        return $next($request);
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
                \Log::info('RestrictRootAccess: Host encontrado', [
                    'host' => $host,
                    'method' => $this->getHostMethod($request, $host)
                ]);
                return strtolower(trim($host));
            }
        }

        // Fallback: usar o host padrão
        $defaultHost = $request->getHost();
        \Log::warning('RestrictRootAccess: Usando host padrão como fallback', ['host' => $defaultHost]);
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
} 