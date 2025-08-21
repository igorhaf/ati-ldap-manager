<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RoleResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IsOUAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Usuário root e master também passam
        $role = $user ? RoleResolver::resolve($user) : null;
        if ($role === RoleResolver::ROLE_ROOT || $role === RoleResolver::ROLE_MASTER) {
            return $next($request);
        }

        if ($role !== RoleResolver::ROLE_OU_ADMIN) {
            throw new HttpException(403, 'Acesso restrito a administradores de organização');
        }

        // Se a rota especificar uma OU (ex: route parameter ou query), validar que é a mesma do admin
        $targetOu = $request->route('ou') ?? $request->query('ou');
        if ($targetOu && $targetOu !== RoleResolver::getUserOu($user)) {
            throw new HttpException(403, 'Você só pode acessar recursos da sua própria organização');
        }

        return $next($request);
    }
} 