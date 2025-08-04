<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestBasicApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:basic-app';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa se a aplicação básica está funcionando';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Teste da Aplicação Básica');
        $this->info('============================');

        try {
            // 1. Teste básico do Laravel
            $this->info('1️⃣ Testando Laravel...');
            $this->line('   App Name: ' . config('app.name'));
            $this->line('   Environment: ' . config('app.env'));
            $this->line('   Debug: ' . (config('app.debug') ? 'true' : 'false'));
            $this->info('✅ Laravel funcionando');

            // 2. Teste de configuração LDAP
            $this->info('\n2️⃣ Testando configuração LDAP...');
            $ldapConfig = config('ldap.connections.default');
            $this->line('   Host: ' . ($ldapConfig['hosts'][0] ?? 'não definido'));
            $this->line('   Port: ' . ($ldapConfig['port'] ?? 'não definido'));
            $this->line('   Base DN: ' . ($ldapConfig['base_dn'] ?? 'não definido'));
            $this->info('✅ Configuração LDAP carregada');

            // 3. Teste de variáveis de ambiente
            $this->info('\n3️⃣ Testando variáveis de ambiente...');
            $envVars = ['LDAP_HOST', 'LDAP_USERNAME', 'LDAP_BASE_DN'];
            foreach ($envVars as $var) {
                $value = env($var);
                if ($value) {
                    $this->line("   ✅ {$var}: definido");
                } else {
                    $this->line("   ⚠️  {$var}: não definido");
                }
            }

            // 4. Teste básico de conectividade TCP
            $this->info('\n4️⃣ Testando conectividade TCP...');
            $host = $ldapConfig['hosts'][0] ?? null;
            $port = $ldapConfig['port'] ?? 389;
            
            if ($host) {
                $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                if ($connection) {
                    $this->info("✅ TCP conectado para {$host}:{$port}");
                    fclose($connection);
                } else {
                    $this->error("❌ TCP falhou para {$host}:{$port}: {$errstr}");
                }
            } else {
                $this->warn('⚠️  Host LDAP não configurado');
            }

            // 5. Verificar se extensão LDAP está carregada
            $this->info('\n5️⃣ Verificando extensão LDAP...');
            if (extension_loaded('ldap')) {
                $this->info('✅ Extensão LDAP carregada');
            } else {
                $this->error('❌ Extensão LDAP não encontrada');
            }

            // 6. Teste de rotas básicas
            $this->info('\n6️⃣ Verificando rotas...');
            try {
                $routes = \Route::getRoutes();
                $routeCount = $routes->count();
                $this->info("✅ {$routeCount} rotas carregadas");
                
                // Verificar algumas rotas importantes
                $importantRoutes = ['login', 'ldap'];
                foreach ($importantRoutes as $routeName) {
                    $hasRoute = collect($routes)->contains(function($route) use ($routeName) {
                        return str_contains($route->uri(), $routeName);
                    });
                    
                    if ($hasRoute) {
                        $this->line("   ✅ Rota '{$routeName}' encontrada");
                    } else {
                        $this->line("   ⚠️  Rota '{$routeName}' não encontrada");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️  Erro ao verificar rotas: " . $e->getMessage());
            }

            $this->info('\n🎉 Teste básico da aplicação concluído!');
            $this->line('💡 Próximos passos:');
            $this->line('   1. Execute: sudo ./vendor/bin/sail artisan test:simple-structure');
            $this->line('   2. Acesse a aplicação web para testar o login');

        } catch (\Exception $e) {
            $this->error("❌ Erro durante o teste: " . $e->getMessage());
            $this->error("   Arquivo: " . $e->getFile() . ':' . $e->getLine());
            return 1;
        }

        return 0;
    }
} 