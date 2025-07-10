<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Authenticatable;

class RoleResolver
{
    public const ROLE_ROOT = 'root';
    public const ROLE_OU_ADMIN = 'ou_admin';
    public const ROLE_USER = 'user';

    /**
     * Determina o papel do usuário com base no DN.
     */
    public static function resolve(Authenticatable $user): string
    {
        $roleAttr = method_exists($user, 'getAttribute') ? $user->getAttribute('employeeType') : ($user->employeeType ?? []);
        $roles = collect((array) $roleAttr)->map(fn($v) => strtolower($v));

        if ($roles->contains(self::ROLE_ROOT)) {
            return self::ROLE_ROOT;
        }

        if ($roles->contains('admin')) {
            return self::ROLE_OU_ADMIN;
        }

        if ($user instanceof \LdapRecord\Models\Model) {
            $uid = $user->getFirstAttribute('uid');
            if ($uid) {
                $entries = \App\Ldap\LdapUserModel::where('uid', $uid)->get();
                foreach ($entries as $entry) {
                    $entryRoles = collect((array) ($entry->getAttribute('employeeType') ?? []))->map(fn ($v) => strtolower($v));
                    if ($entryRoles->contains(self::ROLE_ROOT)) {
                        return self::ROLE_ROOT;
                    }
                    if ($entryRoles->contains('admin')) {
                        return self::ROLE_OU_ADMIN;
                    }
                }
            }
        }

        return self::ROLE_USER;
    }

    /**
     * Obtém o nome da OU do usuário (se houver) a partir do DN.
     */
    public static function getUserOu(Authenticatable $user): ?string
    {
        // Tentar encontrar OU onde o usuário é admin, caso exista
        if (method_exists($user, 'getFirstAttribute')) {
            $uid = $user->getFirstAttribute('uid');
            if ($uid) {
                $entries = \App\Ldap\LdapUserModel::where('uid', $uid)->get();
                foreach ($entries as $entry) {
                    $entryRoles = collect((array) ($entry->getAttribute('employeeType') ?? []))->map(fn ($v) => strtolower($v));
                    if ($entryRoles->contains('admin')) {
                        return $entry->getFirstAttribute('ou');
                    }
                }
            }
        }

        $dn = method_exists($user, 'getDn') ? $user->getDn() : ($user->dn ?? '');
        if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
            return $matches[1];
        }
        return null;
    }
} 