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
            $host = $request->getHost();
            
            // Permitir apenas acesso via contasadmin.sei.pe.gov.br
            if ($host !== 'contasadmin.sei.pe.gov.br') {
                // Lançar exceção que será capturada pela página de erro 403
                throw new AccessDeniedHttpException('Usuários root só podem acessar via contasadmin.sei.pe.gov.br');
            }
        }

        return $next($request);
    }
} 