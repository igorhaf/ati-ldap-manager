<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RoleResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IsRootUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || RoleResolver::resolve($user) !== RoleResolver::ROLE_ROOT) {
            throw new HttpException(403, 'Acesso restrito ao usuário root');
        }
        return $next($request);
    }
} 