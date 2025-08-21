<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RoleResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

class IsRootOrMaster
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            throw new HttpException(403, 'Acesso restrito: somente root ou master');
        }

        $role = RoleResolver::resolve($user);
        if (!in_array($role, [RoleResolver::ROLE_ROOT, RoleResolver::ROLE_MASTER], true)) {
            throw new HttpException(403, 'Acesso restrito: somente root ou master');
        }

        return $next($request);
    }
}


