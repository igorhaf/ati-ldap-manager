<?php

namespace App\Utils;

class LdapDnUtils
{
    /**
     * Escapa um valor para uso seguro em DN
     */
    public static function escapeDnValue($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Remove espaços no início e fim
        $value = trim($value);

        // Se a função ldap_escape está disponível, use ela
        if (function_exists('ldap_escape')) {
            return ldap_escape($value, '', LDAP_ESCAPE_DN);
        }

        // Escape manual de caracteres especiais para DN
        $escapeMap = [
            '\\' => '\\\\',  // Barra invertida deve ser escapada primeiro
            ',' => '\\,',    // Vírgula
            '"' => '\\"',    // Aspas
            '/' => '\\/',    // Barra
            '<' => '\\<',    // Menor que
            '>' => '\\>',    // Maior que
            ';' => '\\;',    // Ponto e vírgula
            '=' => '\\=',    // Igual
            '+' => '\\+',    // Mais
            '#' => '\\#'     // Hash
        ];

        $escaped = $value;
        foreach ($escapeMap as $char => $replacement) {
            $escaped = str_replace($char, $replacement, $escaped);
        }

        return $escaped;
    }

    /**
     * Constrói um DN de usuário de forma segura
     */
    public static function buildUserDn($uid, $ou, $baseDn)
    {
        $safeUid = self::escapeDnValue($uid);
        $safeOu = self::escapeDnValue($ou);
        
        return "uid={$safeUid},ou={$safeOu},{$baseDn}";
    }

    /**
     * Constrói um DN de OU de forma segura
     */
    public static function buildOuDn($ou, $baseDn)
    {
        $safeOu = self::escapeDnValue($ou);
        
        return "ou={$safeOu},{$baseDn}";
    }

    /**
     * Valida se um valor é seguro para uso em DN
     */
    public static function isValidDnValue($value)
    {
        if (empty($value)) {
            return false;
        }

        // Verificar se não tem apenas espaços
        if (trim($value) === '') {
            return false;
        }

        // Verificar comprimento razoável
        if (strlen($value) > 255) {
            return false;
        }

        // Verificar caracteres de controle
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Normaliza um valor para uso em DN (remove/substitui caracteres problemáticos)
     */
    public static function normalizeDnValue($value)
    {
        if (empty($value)) {
            return $value;
        }

        // Remove espaços no início e fim
        $normalized = trim($value);

        // Remove caracteres de controle
        $normalized = preg_replace('/[\x00-\x1F\x7F]/', '', $normalized);

        // Converte espaços múltiplos em único espaço
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Para uma abordagem mais conservadora, remove caracteres problemáticos
        // ao invés de escapá-los (opcional)
        // $normalized = preg_replace('/[,"\/<>;=+#\\\\]/', '', $normalized);

        return trim($normalized);
    }

    /**
     * Verifica se um valor contém caracteres problemáticos para DN
     */
    public static function hasProblematicChars($value)
    {
        if (empty($value)) {
            return false;
        }

        // Lista de caracteres problemáticos em DN
        $problematicChars = [',', '"', '\\', '/', '<', '>', ';', '=', '+', '#'];
        
        foreach ($problematicChars as $char) {
            if (strpos($value, $char) !== false) {
                return true;
            }
        }

        // Verificar espaços no início/fim
        return trim($value) !== $value;
    }

    /**
     * Obtém lista de caracteres problemáticos encontrados no valor
     */
    public static function getProblematicChars($value)
    {
        $problematicChars = [',', '"', '\\', '/', '<', '>', ';', '=', '+', '#'];
        $found = [];
        
        foreach ($problematicChars as $char) {
            if (strpos($value, $char) !== false) {
                $found[] = $char;
            }
        }
        
        if (trim($value) !== $value) {
            $found[] = 'espaços início/fim';
        }
        
        return $found;
    }
} 