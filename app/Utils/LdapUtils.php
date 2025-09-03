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
        // Debug temporário
        \Log::info('SSHA Debug', [
            'password_length' => strlen($password),
            'hash' => $hash,
            'hash_length' => strlen($hash)
        ]);

        // Verificar se é um hash SSHA
        if (!preg_match('/^\{SSHA\}(.+)$/', $hash, $matches)) {
            \Log::warning('SSHA: Hash não está no formato SSHA', ['hash' => $hash]);
            return false;
        }

        // Decodificar o hash
        $decoded = base64_decode($matches[1]);
        if ($decoded === false) {
            \Log::warning('SSHA: Falha ao decodificar base64');
            return false;
        }

        \Log::info('SSHA: Hash decodificado', [
            'decoded_length' => strlen($decoded),
            'expected_length' => 24 // 20 bytes SHA1 + 4 bytes salt
        ]);

        // Extrair o salt (últimos 4 bytes)
        $salt = substr($decoded, -4);
        $hashWithoutSalt = substr($decoded, 0, -4);

        // Calcular o hash da senha + salt
        $calculatedHash = sha1($password . $salt, true);

        // Debug da comparação
        \Log::info('SSHA: Comparação de hashes', [
            'stored_hash_hex' => bin2hex($hashWithoutSalt),
            'calculated_hash_hex' => bin2hex($calculatedHash),
            'salt_hex' => bin2hex($salt),
            'match' => hash_equals($hashWithoutSalt, $calculatedHash)
        ]);

        // Comparar os hashes
        return hash_equals($hashWithoutSalt, $calculatedHash);
    }
}
