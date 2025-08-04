<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QuickLdapTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'quick:ldap-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Teste rápido de conectividade LDAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Teste Rápido LDAP');
        $this->info('====================');

        $host = env('LDAP_HOST');
        $port = env('LDAP_PORT', 389);
        $username = env('LDAP_USERNAME');
        $password = env('LDAP_PASSWORD');

        $this->line("Host: {$host}:{$port}");
        $this->line("User: {$username}");

        // 1. Teste de conectividade TCP básica
        $this->info("\n1️⃣ Testando conectividade TCP...");
        $connection = @fsockopen($host, $port, $errno, $errstr, 10);
        if ($connection) {
            $this->info("✅ TCP conectado");
            fclose($connection);
        } else {
            $this->error("❌ TCP falhou: {$errstr}");
            return 1;
        }

        // 2. Teste LDAP direto com PHP
        $this->info("2️⃣ Testando LDAP direto...");
        
        if (!extension_loaded('ldap')) {
            $this->error("❌ Extensão LDAP não carregada");
            return 1;
        }

        $ldapConn = ldap_connect($host, $port);
        if (!$ldapConn) {
            $this->error("❌ ldap_connect falhou");
            return 1;
        }

        // Configurar opções
        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($ldapConn, LDAP_OPT_NETWORK_TIMEOUT, 10);

        $this->info("✅ ldap_connect sucesso");

        // 3. Teste de bind (autenticação)
        $this->info("3️⃣ Testando autenticação...");
        
        $bind = @ldap_bind($ldapConn, $username, $password);
        if ($bind) {
            $this->info("✅ Autenticação bem-sucedida");
        } else {
            $error = ldap_error($ldapConn);
            $this->error("❌ Autenticação falhou: {$error}");
            ldap_close($ldapConn);
            return 1;
        }

        // 4. Teste de busca básica
        $this->info("4️⃣ Testando busca básica...");
        
        $baseDn = env('LDAP_BASE_DN');
        $filter = "(objectClass=*)";
        
        $search = @ldap_search($ldapConn, $baseDn, $filter, [], 0, 1);
        if ($search) {
            $entries = ldap_get_entries($ldapConn, $search);
            $count = $entries['count'] ?? 0;
            $this->info("✅ Busca funcionando ({$count} resultados)");
        } else {
            $error = ldap_error($ldapConn);
            $this->warn("⚠️  Busca falhou: {$error}");
        }

        ldap_close($ldapConn);

        $this->info("\n🎉 Teste LDAP concluído com sucesso!");
        $this->line("💡 Se o teste passou, o problema pode estar na configuração do Laravel/LdapRecord");
        
        return 0;
    }
} 