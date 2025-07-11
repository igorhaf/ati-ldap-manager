<?php

namespace App\Utils;

class LdapUtils
{
    /**
     * Gera um hash SSHA compatível com OpenLDAP.
     *
     * @param string $password Senha em texto puro.
     * @return string Hash no formato {SSHA}base64(...)
     */
    public static function hashSsha(string $password): string
    {
        // 4 bytes de salt aleatório (32 bits) – suficiente para SSHA
        $salt = random_bytes(4);
        // SHA-1 binário da senha + salt seguido do salt
        $hash = sha1($password . $salt, true) . $salt;
        return '{SSHA}' . base64_encode($hash);
    }
} 