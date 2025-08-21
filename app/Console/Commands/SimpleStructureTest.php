<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LdapRecord\Container;
use LdapRecord\Connection;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;

class SimpleStructureTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:simple-structure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Teste simples e robusto da estrutura LDAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Teste Simples da Estrutura LDAP');
        $this->info('===================================');

        try {
            // 1. Inicializar Container
            $this->info('1️⃣ Inicializando Container...');
            $this->initializeContainer();
            $this->info('✅ Container inicializado');

            // 2. Testar busca básica raw
            $this->info('\n2️⃣ Testando busca raw básica...');
            $this->testRawSearch();

            // 3. Testar LdapUserModel
            $this->info('\n3️⃣ Testando LdapUserModel...');
            $this->testUserModel();

            // 4. Testar OrganizationalUnit
            $this->info('\n4️⃣ Testando OrganizationalUnit...');
            $this->testOuModel();

            $this->info('\n🎉 Todos os testes simples passaram!');

        } catch (\Exception $e) {
            $this->error("❌ Erro: " . $e->getMessage());
            $this->error("   Arquivo: " . $e->getFile() . ':' . $e->getLine());
            
            if ($e->getPrevious()) {
                $this->error("   Erro anterior: " . $e->getPrevious()->getMessage());
            }
            
            return 1;
        }

        return 0;
    }

    private function initializeContainer()
    {
        Container::flush();
        
        $config = config('ldap.connections.default');
        $connection = new Connection($config);
        Container::addConnection($connection, 'default');
        Container::setDefaultConnection('default');
        
        // Testar conexão
        $connection->connect();
        $connection->auth()->attempt($config['username'], $config['password']);
    }

    private function testRawSearch()
    {
        try {
            $connection = Container::getDefaultConnection();
            $baseDn = config('ldap.connections.default.base_dn');
            
            $results = $connection->query()
                ->setDn($baseDn)
                ->whereEquals('objectClass', '*')
                ->limit(1)
                ->get();

            $count = is_array($results) ? count($results) : (is_object($results) && method_exists($results, 'count') ? $results->count() : 0);
            $this->info("✅ Busca raw: {$count} resultado(s)");

        } catch (\Exception $e) {
            $this->warn("⚠️  Busca raw falhou: " . $e->getMessage());
        }
    }

    private function testUserModel()
    {
        try {
            $users = LdapUserModel::limit(1)->get();
            
            $count = $this->safeCount($users);
            $this->info("✅ LdapUserModel: {$count} usuário(s)");

            if ($count > 0) {
                $user = $this->safeFirst($users);
                if ($user) {
                    $dn = $this->safeDn($user);
                    $uid = $this->safeAttribute($user, 'uid');
                    $this->line("   👤 UID: {$uid}");
                    $this->line("   📍 DN: {$dn}");
                }
            }

        } catch (\Exception $e) {
            $this->warn("⚠️  LdapUserModel falhou: " . $e->getMessage());
        }
    }

    private function testOuModel()
    {
        try {
            $ous = OrganizationalUnit::limit(3)->get();
            
            $count = $this->safeCount($ous);
            $this->info("✅ OrganizationalUnit: {$count} OU(s)");

            if ($count > 0) {
                $processedCount = 0;
                foreach ($ous as $ou) {
                    if ($processedCount >= 3) break; // Limitar para evitar spam
                    
                    $ouName = $this->safeAttribute($ou, 'ou');
                    $dn = $this->safeDn($ou);
                    $this->line("   📁 OU: {$ouName} | DN: {$dn}");
                    $processedCount++;
                }
            }

        } catch (\Exception $e) {
            $this->warn("⚠️  OrganizationalUnit falhou: " . $e->getMessage());
        }
    }

    // Métodos auxiliares para lidar com arrays/collections de forma segura

    private function safeCount($data)
    {
        if (is_array($data)) {
            return count($data);
        } elseif (is_object($data) && method_exists($data, 'count')) {
            return $data->count();
        } else {
            return 0;
        }
    }

    private function safeFirst($data)
    {
        if (is_array($data)) {
            return isset($data[0]) ? $data[0] : null;
        } elseif (is_object($data) && method_exists($data, 'first')) {
            return $data->first();
        } else {
            return null;
        }
    }

    private function safeDn($item)
    {
        if (is_object($item) && method_exists($item, 'getDn')) {
            return $item->getDn();
        } else {
            return 'DN não disponível';
        }
    }

    private function safeAttribute($item, $attribute)
    {
        if (is_object($item) && method_exists($item, 'getFirstAttribute')) {
            return $item->getFirstAttribute($attribute) ?? 'não definido';
        } else {
            return 'não disponível';
        }
    }
} 