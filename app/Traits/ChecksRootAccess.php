<?php

namespace App\Traits;

use Illuminate\Http\Request;
use App\Services\RoleResolver;

trait ChecksRootAccess
{
    /**
     * Verificar se usuário root pode acessar a URL atual
     */
    protected function checkRootAccess(Request $request)
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
                    abort(403, 'O acesso a este usuário não pode ser feito por essa URL');
                }
                
                abort(403, 'O acesso a este usuário não pode ser feito por essa URL');
            }
        }

        return true;
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
                \Log::info('ChecksRootAccess: Host encontrado', [
                    'host' => $host,
                    'method' => $this->getHostMethod($request, $host)
                ]);
                return strtolower(trim($host));
            }
        }

        // Fallback: usar o host padrão
        $defaultHost = $request->getHost();
        \Log::warning('ChecksRootAccess: Usando host padrão como fallback', ['host' => $defaultHost]);
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