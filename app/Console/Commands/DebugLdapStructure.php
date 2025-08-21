<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use LdapRecord\Connection;

class DebugLdapStructure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:ldap-structure {uid?} {--ou=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug da estrutura LDAP e busca de usuários';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $ou = $this->option('ou');

        $this->info('🔍 Debug da Estrutura LDAP');
        $this->info('=====================================');

        // Mostrar configuração atual
        $baseDn = config('ldap.connections.default.base_dn');
        $host = config('ldap.connections.default.hosts')[0];
        
        $this->info("🌐 Servidor LDAP: {$host}");
        $this->info("📂 Base DN: {$baseDn}");
        $this->info('');

        try {
            // 1. Verificar conexão LDAP
            $this->info('1️⃣ Verificando conexão...');
            
            try {
                // Tentar diferentes formas de obter a conexão
                $connection = Container::getDefaultConnection();
                
                if (!$connection) {
                    $this->line('🔄 Tentando inicializar conexão...');
                    // Forçar inicialização da conexão
                    $config = config('ldap.connections.default');
                    $newConnection = new Connection($config);
                    Container::addConnection($newConnection, 'default');
                    $connection = Container::getDefaultConnection();
                }
                
                // Tentar conectar explicitamente
                if (!$connection->isConnected()) {
                    $this->line('🔗 Estabelecendo conexão...');
                    $connection->connect();
                }
                
                if ($connection->isConnected()) {
                    $this->info('✅ Conectado ao LDAP');
                } else {
                    $this->warn('⚠️  Conexão não estabelecida, tentando operações...');
                }
                
            } catch (\Exception $e) {
                $this->error('❌ Erro na conexão: ' . $e->getMessage());
                $this->line('🔧 Tentando configuração manual...');
                
                // Configuração manual como fallback
                try {
                    $config = config('ldap.connections.default');
                    $manualConnection = new Connection($config);
                    Container::addConnection($manualConnection, 'default');
                    Container::setDefaultConnection('default');
                    $manualConnection->connect();
                    
                    if ($manualConnection->isConnected()) {
                        $this->info('✅ Conectado via configuração manual');
                        $connection = $manualConnection; // Usar a conexão manual
                    } else {
                        $this->error('❌ Falha na conexão manual');
                        return 1;
                    }
                } catch (\Exception $e2) {
                    $this->error('❌ Falha total na conexão: ' . $e2->getMessage());
                    return 1;
                }
            }

            // 2. Listar OUs disponíveis
            $this->info("\n2️⃣ Listando OUs disponíveis:");
            $this->info('────────────────────────────');
            try {
                $ous = OrganizationalUnit::all();
                foreach ($ous as $ouEntry) {
                    $ouName = $ouEntry->getFirstAttribute('ou');
                    $dn = $ouEntry->getDn();
                    $this->line("📁 OU: {$ouName} | DN: {$dn}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro ao listar OUs: " . $e->getMessage());
            }

            // 3. Se UID fornecido, buscar usuário
            if ($uid) {
                $this->info("\n3️⃣ Buscando usuário '{$uid}':");
                $this->info('─────────────────────────────────');
                
                // Busca simples (método atual)
                $this->line("🔍 Busca simples por UID...");
                $users = LdapUserModel::where('uid', $uid)->get();
                $userCount = is_array($users) ? count($users) : $users->count();
                $this->info("📊 Encontrados: " . $userCount . " usuários");
                
                foreach ($users as $user) {
                    $this->displayUserInfo($user, "Busca por UID");
                }

                // Se OU fornecida, testar busca com OU
                if ($ou) {
                    $this->line("\n🔍 Busca com OU '{$ou}'...");
                    $usersWithOu = LdapUserModel::where('uid', $uid)
                        ->where('ou', $ou)
                        ->get();
                    $userWithOuCount = is_array($usersWithOu) ? count($usersWithOu) : $usersWithOu->count();
                    $this->info("📊 Encontrados: " . $userWithOuCount . " usuários");
                    
                    foreach ($usersWithOu as $user) {
                        $this->displayUserInfo($user, "Busca por UID + OU");
                    }

                    // Busca por DN construído
                    $this->line("\n🔍 Busca por DN construído...");
                    $expectedDn = "uid={$uid},ou={$ou},{$baseDn}";
                    $this->line("🎯 DN esperado: {$expectedDn}");
                    
                    try {
                        $userByDn = LdapUserModel::find($expectedDn);
                        if ($userByDn) {
                            $this->displayUserInfo($userByDn, "Busca por DN");
                        } else {
                            $this->warn("⚠️  Usuário não encontrado pelo DN construído");
                        }
                    } catch (\Exception $e) {
                        $this->error("❌ Erro na busca por DN: " . $e->getMessage());
                    }
                }
            }

            // 4. Testar diferentes formas de busca
            $this->info("\n4️⃣ Testando diferentes métodos de busca:");
            $this->info('──────────────────────────────────────────');
            
            if ($uid && $ou) {
                $this->testSearchMethods($uid, $ou, $baseDn);
            } else {
                $this->line("💡 Para testar métodos de busca, forneça --uid e --ou");
                $this->line("   Exemplo: php artisan debug:ldap-structure joao --ou=ti");
            }

        } catch (\Exception $e) {
            $this->error("❌ Erro geral: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }

        $this->info("\n✅ Debug concluído!");
        return 0;
    }

    private function displayUserInfo($user, $method)
    {
        $this->line("  📋 {$method}:");
        $this->line("     DN: " . $user->getDn());
        $this->line("     UID: " . $user->getFirstAttribute('uid'));
        $this->line("     Nome: " . $user->getFirstAttribute('givenName') . ' ' . $user->getFirstAttribute('sn'));
        $this->line("     Email: " . $user->getFirstAttribute('mail'));
        $this->line("     OU (atributo): " . ($user->getFirstAttribute('ou') ?? 'não definido'));
        $this->line("     Employee Type: " . json_encode($user->getAttribute('employeeType')));
        $this->line("");
    }

    private function testSearchMethods($uid, $ou, $baseDn)
    {
        $methods = [
            "Método 1: where('uid')->where('ou')" => function() use ($uid, $ou) {
                return LdapUserModel::where('uid', $uid)->where('ou', $ou)->get();
            },
            "Método 2: apenas where('uid')" => function() use ($uid) {
                return LdapUserModel::where('uid', $uid)->get();
            },
            "Método 3: busca por DN direto" => function() use ($uid, $ou, $baseDn) {
                $dn = "uid={$uid},ou={$ou},{$baseDn}";
                $user = LdapUserModel::find($dn);
                return $user ? collect([$user]) : collect([]);
            },
            "Método 4: busca em base específica" => function() use ($uid, $ou, $baseDn) {
                return LdapUserModel::in("ou={$ou},{$baseDn}")->where('uid', $uid)->get();
            },
        ];

        foreach ($methods as $name => $method) {
            try {
                $this->line("🧪 {$name}");
                $result = $method();
                $resultCount = is_array($result) ? count($result) : $result->count();
                $this->info("   📊 Resultado: " . $resultCount . " usuário(s)");
                
                if ($resultCount > 0) {
                    $user = is_array($result) ? $result[0] : $result->first();
                    $dn = is_object($user) && method_exists($user, 'getDn') ? $user->getDn() : 'DN não disponível';
                    $ou = is_object($user) && method_exists($user, 'getFirstAttribute') ? ($user->getFirstAttribute('ou') ?? 'não definido') : 'não disponível';
                    $this->line("   ✅ DN encontrado: " . $dn);
                    $this->line("   📝 OU (atributo): " . $ou);
                } else {
                    $this->line("   ❌ Nenhum usuário encontrado");
                }
            } catch (\Exception $e) {
                $this->line("   ⚠️  Erro: " . $e->getMessage());
            }
            $this->line("");
        }
    }
} 