<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RoleResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IsSelfAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            throw new HttpException(403, 'Não autenticado');
        }

        $role = RoleResolver::resolve($user);

        // Root, Master e OU Admin têm acesso ilimitado
        if (in_array($role, [RoleResolver::ROLE_ROOT, RoleResolver::ROLE_MASTER, RoleResolver::ROLE_OU_ADMIN])) {
            return $next($request);
        }

        // A rota deve conter o parâmetro UID e ser igual ao usuário autenticado
        $uidParam = $request->route('uid');
        if ($uidParam && $uidParam === $user->getFirstAttribute('uid')) {
            return $next($request);
        }

        throw new HttpException(403, 'Você só pode acessar seu próprio recurso');
    }
} 