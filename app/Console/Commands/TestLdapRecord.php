<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LdapRecord\Connection;
use LdapRecord\Container;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;

class TestLdapRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ldap-record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa especificamente o LdapRecord/Laravel';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Teste LdapRecord/Laravel');
        $this->info('============================');

        try {
            // 1. Verificar configuração
            $this->info('1️⃣ Verificando configuração...');
            $config = config('ldap.connections.default');
            $this->line("Host: " . $config['hosts'][0]);
            $this->line("Base DN: " . $config['base_dn']);

            // 2. Inicializar Container manualmente
            $this->info("\n2️⃣ Inicializando Container...");
            
            // Limpar conexões existentes
            Container::flush();
            
            // Adicionar conexão
            Container::addConnection($config, 'default');
            Container::setDefaultConnection('default');
            
            $connection = Container::getDefaultConnection();
            $this->info("✅ Container inicializado");

            // 3. Testar conexão
            $this->info("\n3️⃣ Testando conexão...");
            $connection->connect();
            
            if ($connection->isConnected()) {
                $this->info("✅ Conexão estabelecida");
            } else {
                $this->warn("⚠️  Estado da conexão indefinido");
            }

            // 4. Testar autenticação
            $this->info("\n4️⃣ Testando autenticação...");
            $auth = $connection->auth()->attempt($config['username'], $config['password']);
            
            if ($auth) {
                $this->info("✅ Autenticação bem-sucedida");
            } else {
                $this->error("❌ Falha na autenticação");
                return 1;
            }

            // 5. Testar busca com Model
            $this->info("\n5️⃣ Testando busca com LdapUserModel...");
            
            try {
                $users = LdapUserModel::limit(1)->get();
                $this->info("✅ LdapUserModel funcionando ({$users->count()} resultados)");
                
                if ($users->count() > 0) {
                    $user = $users->first();
                    $this->line("   📋 Primeiro usuário:");
                    $this->line("      DN: " . $user->getDn());
                    $this->line("      UID: " . ($user->getFirstAttribute('uid') ?? 'não definido'));
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro no LdapUserModel: " . $e->getMessage());
            }

            // 6. Testar busca de OUs
            $this->info("\n6️⃣ Testando busca de OUs...");
            
            try {
                $ous = OrganizationalUnit::limit(3)->get();
                $this->info("✅ OrganizationalUnit funcionando ({$ous->count()} resultados)");
                
                foreach ($ous as $ou) {
                    $ouName = $ou->getFirstAttribute('ou');
                    $this->line("   📁 OU: {$ouName} | DN: " . $ou->getDn());
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro no OrganizationalUnit: " . $e->getMessage());
            }

            // 7. Testar busca raw
            $this->info("\n7️⃣ Testando busca raw...");
            
            try {
                $results = $connection->query()
                    ->setDn($config['base_dn'])
                    ->whereEquals('objectClass', 'organizationalUnit')
                    ->limit(3)
                    ->get();
                
                $this->info("✅ Busca raw funcionando ({$results->count()} resultados)");
                
                foreach ($results as $result) {
                    $this->line("   📄 " . $result->getDn());
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro na busca raw: " . $e->getMessage());
            }

            $this->info("\n🎉 Todos os testes LdapRecord passaram!");

        } catch (\Exception $e) {
            $this->error("❌ Erro geral: " . $e->getMessage());
            $this->error("   Classe: " . get_class($e));
            
            if ($e->getPrevious()) {
                $this->error("   Erro anterior: " . $e->getPrevious()->getMessage());
            }
            
            return 1;
        }

        return 0;
    }
} 