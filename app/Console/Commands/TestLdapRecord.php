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
            $connection = new Connection($config);
            Container::addConnection($connection, 'default');
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
                $userCount = is_array($users) ? count($users) : $users->count();
                $this->info("✅ LdapUserModel funcionando ({$userCount} resultados)");
                
                if ($userCount > 0) {
                    $user = is_array($users) ? $users[0] : $users->first();
                    $this->line("   📋 Primeiro usuário:");
                    $dn = is_object($user) && method_exists($user, 'getDn') ? $user->getDn() : 'DN não disponível';
                    $uid = is_object($user) && method_exists($user, 'getFirstAttribute') ? ($user->getFirstAttribute('uid') ?? 'não definido') : 'não disponível';
                    $this->line("      DN: " . $dn);
                    $this->line("      UID: " . $uid);
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro no LdapUserModel: " . $e->getMessage());
            }

            // 6. Testar busca de OUs
            $this->info("\n6️⃣ Testando busca de OUs...");
            
            try {
                $ous = OrganizationalUnit::limit(3)->get();
                $ouCount = is_array($ous) ? count($ous) : $ous->count();
                $this->info("✅ OrganizationalUnit funcionando ({$ouCount} resultados)");
                
                foreach ($ous as $ou) {
                    $ouName = is_object($ou) && method_exists($ou, 'getFirstAttribute') ? $ou->getFirstAttribute('ou') : 'não disponível';
                    $dn = is_object($ou) && method_exists($ou, 'getDn') ? $ou->getDn() : 'DN não disponível';
                    $this->line("   📁 OU: {$ouName} | DN: " . $dn);
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
                
                $count = is_array($results) ? count($results) : $results->count();
                $this->info("✅ Busca raw funcionando ({$count} resultados)");
                
                foreach ($results as $result) {
                    $dn = is_object($result) && method_exists($result, 'getDn') ? $result->getDn() : (string)$result;
                    $this->line("   📄 " . $dn);
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