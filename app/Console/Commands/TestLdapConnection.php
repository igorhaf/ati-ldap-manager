<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LdapRecord\Connection;
use LdapRecord\Container;

class TestLdapConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ldap-connection {--detailed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa e diagnostica problemas de conexão LDAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $detailed = $this->option('detailed');

        $this->info('🔍 Diagnóstico de Conexão LDAP');
        $this->info('=====================================');

        // 1. Mostrar configuração atual
        $this->showCurrentConfig();

        // 2. Teste básico de conectividade de rede
        $this->testNetworkConnectivity();

        // 3. Teste de conexão LDAP
        $this->testLdapConnection();

        // 4. Se detailed, mostrar mais informações
        if ($detailed) {
            $this->detailedDiagnostics();
        }

        $this->info("\n✅ Diagnóstico concluído!");
        return 0;
    }

    private function showCurrentConfig()
    {
        $this->info("\n1️⃣ Configuração Atual:");
        $this->info("─────────────────────────");

        $config = config('ldap.connections.default');
        
        $this->line("🌐 Host: " . ($config['hosts'][0] ?? 'não definido'));
        $this->line("🔌 Porta: " . ($config['port'] ?? 'não definido'));
        $this->line("👤 Username: " . ($config['username'] ?? 'não definido'));
        $this->line("🔐 Password: " . (empty($config['password']) ? 'não definido' : '***definido***'));
        $this->line("📂 Base DN: " . ($config['base_dn'] ?? 'não definido'));
        $this->line("🔒 SSL: " . ($config['use_ssl'] ? 'Habilitado' : 'Desabilitado'));
        $this->line("🔐 TLS: " . ($config['use_tls'] ? 'Habilitado' : 'Desabilitado'));
        $this->line("⏱️ Timeout: " . ($config['timeout'] ?? 'não definido') . 's');

        // Verificar variáveis de ambiente
        $this->line("\n📋 Variáveis de Ambiente:");
        $envVars = [
            'LDAP_HOST', 'LDAP_PORT', 'LDAP_USERNAME', 'LDAP_PASSWORD',
            'LDAP_BASE_DN', 'LDAP_SSL', 'LDAP_TLS', 'LDAP_TIMEOUT'
        ];

        foreach ($envVars as $var) {
            $value = env($var);
            if ($value !== null) {
                if (str_contains($var, 'PASSWORD')) {
                    $this->line("   {$var}: ***definido***");
                } else {
                    $this->line("   {$var}: {$value}");
                }
            } else {
                $this->line("   {$var}: <fg=yellow>não definido</>");
            }
        }
    }

    private function testNetworkConnectivity()
    {
        $this->info("\n2️⃣ Teste de Conectividade de Rede:");
        $this->info("───────────────────────────────────");

        $host = config('ldap.connections.default.hosts')[0] ?? null;
        $port = config('ldap.connections.default.port', 389);

        if (!$host) {
            $this->error("❌ Host não configurado");
            return;
        }

        // Teste de ping (se disponível)
        $this->line("🏓 Testando ping para {$host}...");
        $pingResult = shell_exec("ping -c 1 -W 1 {$host} 2>/dev/null");
        if ($pingResult && str_contains($pingResult, '1 received')) {
            $this->info("✅ Host está acessível via ping");
        } else {
            $this->warn("⚠️  Host não responde ao ping (pode ser firewall)");
        }

        // Teste de porta TCP
        $this->line("🔌 Testando conexão TCP para {$host}:{$port}...");
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($connection) {
            $this->info("✅ Porta {$port} está acessível");
            fclose($connection);
        } else {
            $this->error("❌ Não é possível conectar na porta {$port}");
            $this->error("   Erro: {$errstr} (código: {$errno})");
            return;
        }

        // Se SSL/TLS habilitado, testar porta segura
        $useSSL = config('ldap.connections.default.use_ssl', false);
        $useTLS = config('ldap.connections.default.use_tls', false);
        
        if ($useSSL && $port !== 636) {
            $this->warn("⚠️  SSL habilitado mas porta não é 636 (padrão LDAPS)");
        }
    }

    private function testLdapConnection()
    {
        $this->info("\n3️⃣ Teste de Conexão LDAP:");
        $this->info("──────────────────────────");

        try {
            // Tentar estabelecer conexão
            $this->line("🔗 Tentando estabelecer conexão LDAP...");
            
            $connection = Container::getDefaultConnection();
            
            // Teste básico de conexão
            if ($connection->isConnected()) {
                $this->info("✅ Conexão LDAP estabelecida com sucesso");
            } else {
                // Tentar conectar explicitamente
                $this->line("🔄 Tentando conectar explicitamente...");
                $connection->connect();
                $this->info("✅ Conexão LDAP estabelecida");
            }

            // Teste de autenticação
            $this->line("🔐 Testando autenticação...");
            $username = config('ldap.connections.default.username');
            $password = config('ldap.connections.default.password');
            
            if ($connection->auth()->attempt($username, $password)) {
                $this->info("✅ Autenticação bem-sucedida");
            } else {
                $this->error("❌ Falha na autenticação");
                return;
            }

            // Teste básico de busca
            $this->line("🔍 Testando busca básica...");
            $baseDn = config('ldap.connections.default.base_dn');
            
            $results = $connection->query()
                ->setDn($baseDn)
                ->whereEquals('objectClass', '*')
                ->limit(1)
                ->get();

            if ($results->count() > 0) {
                $this->info("✅ Busca básica funcionando");
                $this->line("   Encontrados: " . $results->count() . " resultado(s)");
            } else {
                $this->warn("⚠️  Busca retornou 0 resultados (pode ser normal)");
            }

        } catch (\LdapRecord\Auth\BindException $e) {
            $this->error("❌ Erro de autenticação LDAP:");
            $this->error("   " . $e->getMessage());
            $this->line("💡 Verifique username e password no .env");
            
        } catch (\LdapRecord\ConnectionException $e) {
            $this->error("❌ Erro de conexão LDAP:");
            $this->error("   " . $e->getMessage());
            $this->line("💡 Verifique host, porta, SSL/TLS");
            
        } catch (\Exception $e) {
            $this->error("❌ Erro geral:");
            $this->error("   " . $e->getMessage());
            $this->error("   Classe: " . get_class($e));
        }
    }

    private function detailedDiagnostics()
    {
        $this->info("\n4️⃣ Diagnósticos Detalhados:");
        $this->info("───────────────────────────");

        // Verificar extensões PHP
        $this->line("🔧 Extensões PHP:");
        $extensions = ['ldap', 'openssl', 'mbstring'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->info("   ✅ {$ext}");
            } else {
                $this->error("   ❌ {$ext} (necessário)");
            }
        }

        // Informações do PHP LDAP
        if (extension_loaded('ldap')) {
            $this->line("\n📋 Informações LDAP:");
            $this->line("   Versão: " . (function_exists('ldap_get_option') ? 'Disponível' : 'Não disponível'));
        }

        // Verificar certificados SSL (se SSL/TLS habilitado)
        $useSSL = config('ldap.connections.default.use_ssl', false);
        $useTLS = config('ldap.connections.default.use_tls', false);
        
        if ($useSSL || $useTLS) {
            $this->line("\n🔒 Verificação SSL/TLS:");
            $host = config('ldap.connections.default.hosts')[0];
            $port = $useSSL ? 636 : 389;
            
            $context = stream_context_create([
                "ssl" => [
                    "capture_peer_cert" => true,
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ]
            ]);
            
            $stream = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
            if ($stream) {
                $this->info("   ✅ Conexão SSL/TLS estabelecida");
                $params = stream_context_get_params($stream);
                if (isset($params['options']['ssl']['peer_certificate'])) {
                    $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
                    $this->line("   📜 Certificado válido até: " . date('Y-m-d H:i:s', $cert['validTo_time_t']));
                }
                fclose($stream);
            } else {
                $this->error("   ❌ Falha na conexão SSL/TLS: {$errstr}");
            }
        }

        // Sugestões de troubleshooting
        $this->info("\n💡 Dicas de Troubleshooting:");
        $this->info("─────────────────────────────");
        $this->line("• Verifique se o firewall permite conexão na porta LDAP");
        $this->line("• Para LDAPS (SSL), use porta 636");
        $this->line("• Para LDAP com TLS, use porta 389 + TLS=true");
        $this->line("• Verifique se as credenciais estão corretas");
        $this->line("• Teste com ldapsearch: ldapsearch -H ldap://host:port -D 'username' -W");
        $this->line("• Verifique logs do servidor LDAP");
        $this->line("• Considere aumentar LDAP_TIMEOUT se a rede for lenta");
    }
} 