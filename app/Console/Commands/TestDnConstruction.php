<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utils\LdapDnUtils;

class TestDnConstruction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:dn-construction {uid} {ou}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa e debuga construção de Distinguished Names (DN)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $ou = $this->argument('ou');

        $this->info('🔍 Teste de Construção de DN');
        $this->info('============================');
        
        $this->line("UID fornecido: '{$uid}'");
        $this->line("OU fornecida: '{$ou}'");

        // 1. Informações básicas
        $baseDn = config('ldap.connections.default.base_dn');
        $this->info("\n1️⃣ Configuração Base:");
        $this->line("Base DN: {$baseDn}");

        // 2. Teste de caracteres problemáticos
        $this->info("\n2️⃣ Análise de Caracteres:");
        $this->analyzeString('UID', $uid);
        $this->analyzeString('OU', $ou);

        // 3. Construção DN original (problemática)
        $this->info("\n3️⃣ DN Original (pode estar problemático):");
        $originalDn = "uid={$uid},ou={$ou},{$baseDn}";
        $this->line("DN: {$originalDn}");
        $this->checkDnSyntax($originalDn);

        // 4. DN com escape de caracteres
        $this->info("\n4️⃣ DN com Escape de Caracteres:");
        $escapedUid = $this->escapeDnValue($uid);
        $escapedOu = $this->escapeDnValue($ou);
        $escapedDn = "uid={$escapedUid},ou={$escapedOu},{$baseDn}";
        $this->line("UID escapado: '{$escapedUid}'");
        $this->line("OU escapada: '{$escapedOu}'");
        $this->line("DN escapado: {$escapedDn}");
        $this->checkDnSyntax($escapedDn);

        // 5. DN normalizado
        $this->info("\n5️⃣ DN Normalizado:");
        $normalizedUid = $this->normalizeDnValue($uid);
        $normalizedOu = $this->normalizeDnValue($ou);
        $normalizedDn = "uid={$normalizedUid},ou={$normalizedOu},{$baseDn}";
        $this->line("UID normalizado: '{$normalizedUid}'");
        $this->line("OU normalizada: '{$normalizedOu}'");
        $this->line("DN normalizado: {$normalizedDn}");
        $this->checkDnSyntax($normalizedDn);

        // 6. Recomendações
        $this->info("\n6️⃣ Recomendações:");
        if ($this->hasProblematicChars($uid) || $this->hasProblematicChars($ou)) {
            $this->warn("⚠️  Caracteres problemáticos detectados!");
            $this->line("💡 Use o DN escapado ou normalizado");
        } else {
            $this->info("✅ Valores parecem seguros para DN");
        }

        // 7. Teste com classe utilitária
        $this->info("\n7️⃣ Usando LdapDnUtils (RECOMENDADO):");
        $utilsDn = LdapDnUtils::buildUserDn($uid, $ou, $baseDn);
        $this->line("DN com LdapDnUtils: {$utilsDn}");
        $this->checkDnSyntax($utilsDn);
        
        // Validações da classe utilitária
        $this->line("UID válido: " . (LdapDnUtils::isValidDnValue($uid) ? 'Sim' : 'Não'));
        $this->line("OU válida: " . (LdapDnUtils::isValidDnValue($ou) ? 'Sim' : 'Não'));
        $this->line("UID tem caracteres problemáticos: " . (LdapDnUtils::hasProblematicChars($uid) ? 'Sim' : 'Não'));
        $this->line("OU tem caracteres problemáticos: " . (LdapDnUtils::hasProblematicChars($ou) ? 'Sim' : 'Não'));

        // 8. Teste com ldap_escape (se disponível)
        if (function_exists('ldap_escape')) {
            $this->info("\n8️⃣ Usando ldap_escape do PHP:");
            $phpEscapedUid = ldap_escape($uid, '', LDAP_ESCAPE_DN);
            $phpEscapedOu = ldap_escape($ou, '', LDAP_ESCAPE_DN);
            $phpEscapedDn = "uid={$phpEscapedUid},ou={$phpEscapedOu},{$baseDn}";
            $this->line("DN com ldap_escape: {$phpEscapedDn}");
            $this->checkDnSyntax($phpEscapedDn);
        }

        return 0;
    }

    private function analyzeString($label, $value)
    {
        $this->line("  {$label}:");
        $this->line("    Comprimento: " . strlen($value));
        $this->line("    Tem espaços: " . (strpos($value, ' ') !== false ? 'Sim' : 'Não'));
        $this->line("    Tem vírgulas: " . (strpos($value, ',') !== false ? 'Sim' : 'Não'));
        $this->line("    Tem aspas: " . (strpos($value, '"') !== false ? 'Sim' : 'Não'));
        $this->line("    Tem caracteres especiais: " . ($this->hasProblematicChars($value) ? 'Sim' : 'Não'));
        
        if ($this->hasProblematicChars($value)) {
            $problematic = $this->getProblematicChars($value);
            $this->warn("    ⚠️  Caracteres problemáticos: " . implode(', ', $problematic));
        }
    }

    private function hasProblematicChars($value)
    {
        // Caracteres que podem causar problemas em DN
        $problematicChars = [',', '"', '\\', '/', '<', '>', ';', '=', '+', '#'];
        
        foreach ($problematicChars as $char) {
            if (strpos($value, $char) !== false) {
                return true;
            }
        }
        
        // Verificar espaços no início/fim
        return trim($value) !== $value;
    }

    private function getProblematicChars($value)
    {
        $problematicChars = [',', '"', '\\', '/', '<', '>', ';', '=', '+', '#'];
        $found = [];
        
        foreach ($problematicChars as $char) {
            if (strpos($value, $char) !== false) {
                $found[] = "'{$char}'";
            }
        }
        
        if (trim($value) !== $value) {
            $found[] = 'espaços início/fim';
        }
        
        return $found;
    }

    private function escapeDnValue($value)
    {
        // Escape de caracteres especiais para DN
        $escapeMap = [
            '\\' => '\\\\',
            ',' => '\\,',
            '"' => '\\"',
            '/' => '\\/',
            '<' => '\\<',
            '>' => '\\>',
            ';' => '\\;',
            '=' => '\\=',
            '+' => '\\+',
            '#' => '\\#'
        ];
        
        $escaped = $value;
        foreach ($escapeMap as $char => $replacement) {
            $escaped = str_replace($char, $replacement, $escaped);
        }
        
        // Remover espaços início/fim
        return trim($escaped);
    }

    private function normalizeDnValue($value)
    {
        // Normalização mais agressiva
        $normalized = trim($value);
        
        // Remover/substituir caracteres problemáticos
        $normalized = preg_replace('/[,"\/<>;=+#\\\\]/', '', $normalized);
        
        // Converter espaços múltiplos em único
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        
        // Remover espaços
        $normalized = trim($normalized);
        
        return $normalized;
    }

    private function checkDnSyntax($dn)
    {
        // Verificações básicas de sintaxe DN
        $issues = [];
        
        // Verificar se tem vírgulas não escapadas problemáticas
        if (preg_match('/[^\\\\],/', $dn)) {
            // Esta é uma verificação muito básica
        }
        
        // Verificar espaços suspeitos
        if (preg_match('/\s{2,}/', $dn)) {
            $issues[] = 'Espaços múltiplos detectados';
        }
        
        // Verificar se termina corretamente
        if (!preg_match('/dc=\w+/', $dn)) {
            $issues[] = 'DN não termina com componente dc válido';
        }
        
        if (empty($issues)) {
            $this->info("  ✅ Sintaxe parece válida");
        } else {
            foreach ($issues as $issue) {
                $this->warn("  ⚠️  {$issue}");
            }
        }
    }
} 