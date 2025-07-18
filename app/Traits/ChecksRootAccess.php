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
            $host = $request->getHost();
            
            if ($host !== 'contasadmin.sei.pe.gov.br') {
                if ($request->expectsJson()) {
                    abort(403, 'O acesso a este usuário não pode ser feito por essa URL');
                }
                
                abort(403, 'O acesso a este usuário não pode ser feito por essa URL');
            }
        }

        return true;
    }
} 