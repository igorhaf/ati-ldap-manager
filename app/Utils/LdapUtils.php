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

    /**
     * Verifica se uma senha corresponde ao hash SSHA.
     *
     * @param string $password Senha em texto puro.
     * @param string $hash Hash SSHA no formato {SSHA}base64(...)
     * @return bool True se a senha corresponder ao hash.
     */
    public static function verifySsha(string $password, string $hash): bool
    {
        // Verificar se é um hash SSHA
        if (!preg_match('/^\{SSHA\}(.+)$/', $hash, $matches)) {
            return false;
        }

        // Decodificar o hash
        $decoded = base64_decode($matches[1]);
        if ($decoded === false) {
            return false;
        }

        // Extrair o salt (últimos 4 bytes)
        $salt = substr($decoded, -4);
        $hashWithoutSalt = substr($decoded, 0, -4);

        // Calcular o hash da senha + salt
        $calculatedHash = sha1($password . $salt, true);

        // Comparar os hashes
        return hash_equals($hashWithoutSalt, $calculatedHash);
    }
} 