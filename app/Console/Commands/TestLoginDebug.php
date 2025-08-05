<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\AuthController;
use App\Ldap\LdapUserModel;
use Illuminate\Http\Request;
use App\Utils\LdapUtils;

class TestLoginDebug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:login-debug {uid} {password} {host}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa o processo completo de login de um usuário';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        $password = $this->argument('password');
        $host = $this->argument('host');

        $this->info('🔐 Teste de Login Debug');
        $this->info('============================');
        $this->info("👤 UID: {$uid}");
        $this->info("🌐 Host: {$host}");
        $this->info('');

        try {
            // 1. Testar extração de OU
            $this->info('1️⃣ Testando extração de OU...');
            $ou = $this->extractOuFromHost($host);
            
            if (!$ou) {
                $this->error('❌ Não foi possível extrair OU do host');
                return 1;
            }
            
            $this->info("✅ OU extraída: {$ou}");
            
            if ($ou === 'admin') {
                $this->info("👑 Tipo: Usuário ROOT");
            } else {
                $this->info("👤 Tipo: Usuário de OU");
            }

            // 2. Testar busca de usuário
            $this->info("\n2️⃣ Testando busca de usuário...");
            
            if ($ou === 'admin') {
                $user = LdapUserModel::where('uid', $uid)->first();
                $this->info("🔍 Método: Busca simples por UID (usuário root)");
            } else {
                $user = $this->findUserInOu($uid, $ou);
                $this->info("🔍 Método: Busca robusta por OU");
            }

            if (!$user) {
                $this->error('❌ Usuário não encontrado');
                return 1;
            }

            $this->info("✅ Usuário encontrado!");
            $this->info("   DN: " . $user->getDn());
            $this->info("   Nome: " . $user->getFirstAttribute('givenName') . ' ' . $user->getFirstAttribute('sn'));
            $this->info("   Email: " . $user->getFirstAttribute('mail'));
            $this->info("   OU (atributo): " . ($user->getFirstAttribute('ou') ?? 'não definido'));

            // 3. Testar verificação de senha
            $this->info("\n3️⃣ Testando verificação de senha...");
            
            $storedPassword = $user->getFirstAttribute('userPassword');
            if (!$storedPassword) {
                $this->error('❌ Senha não encontrada no usuário');
                return 1;
            }

            $this->info("🔐 Hash armazenado: " . substr($storedPassword, 0, 20) . "...");
            
            if (LdapUtils::verifySsha($password, $storedPassword)) {
                $this->info("✅ Senha válida!");
            } else {
                $this->error("❌ Senha inválida!");
                return 1;
            }

            // 4. Verificar role
            $this->info("\n4️⃣ Verificando role do usuário...");
            
            // Simular o RoleResolver
            $dn = strtolower($user->getDn());
            if (str_contains($dn, 'cn=admin')) {
                $role = 'root';
                $this->info("👑 Role: ROOT");
            } else {
                $employeeType = $user->getAttribute('employeeType');
                if (is_array($employeeType)) {
                    $type = strtolower($employeeType[0] ?? 'user');
                } else {
                    $type = strtolower($employeeType ?: 'user');
                }
                
                if ($type === 'admin') {
                    $role = 'admin';
                    $this->info("🔧 Role: OU ADMIN");
                } else {
                    $role = 'user';
                    $this->info("👤 Role: USER");
                }
            }

            // 5. Verificar restrições de acesso
            $this->info("\n5️⃣ Verificando restrições de acesso...");
            
            if ($role === 'root' && $ou !== 'admin') {
                $this->error("❌ Usuário root tentando acessar por URL não-admin");
                return 1;
            }
            
            $this->info("✅ Todas as verificações passaram!");

            // 6. Resumo final
            $this->info("\n📋 Resumo do Login:");
            $this->info("──────────────────────");
            $this->info("✅ Host detectado: {$host}");
            $this->info("✅ OU extraída: {$ou}");
            $this->info("✅ Usuário encontrado: {$uid}");
            $this->info("✅ Senha válida");
            $this->info("✅ Role: {$role}");
            $this->info("✅ Acesso autorizado");
            
            $this->info("\n🎉 Login seria bem-sucedido!");

        } catch (\Exception $e) {
            $this->error("❌ Erro durante o teste: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    /**
     * Cópia do método do AuthController para teste
     */
    private function extractOuFromHost($host)
    {
        // Caso especial para usuários root
        if ($host === 'contas.sei.pe.gov.br') {
            return 'admin';
        }
        
        // Para outras OUs: contas.moreno.sei.pe.gov.br => moreno
        if (preg_match('/contas\\.([a-z0-9-]+)\\.sei\\.pe\\.gov\\.br/i', $host, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Cópia do método findUserInOu do AuthController para teste
     */
    private function findUserInOu($uid, $ou)
    {
        $baseDn = config('ldap.connections.default.base_dn');

        // Método 1: Busca tradicional por atributo 'ou'
        try {
            $user = LdapUserModel::where('uid', $uid)
                ->where('ou', $ou)
                ->first();
            
            if ($user) {
                $this->line("   ✅ Encontrado via método 1 (atributo ou)");
                return $user;
            }
        } catch (\Exception $e) {
            $this->line("   ⚠️  Método 1 falhou: " . $e->getMessage());
        }

        // Método 2: Busca direta por DN construído
        try {
            $expectedDn = "uid={$uid},ou={$ou},{$baseDn}";
            $user = LdapUserModel::find($expectedDn);
            
            if ($user) {
                $this->line("   ✅ Encontrado via método 2 (DN direto)");
                return $user;
            }
        } catch (\Exception $e) {
            $this->line("   ⚠️  Método 2 falhou: " . $e->getMessage());
        }

        // Método 3: Busca em base específica da OU
        try {
            $user = LdapUserModel::in("ou={$ou},{$baseDn}")
                ->where('uid', $uid)
                ->first();
            
            if ($user) {
                $this->line("   ✅ Encontrado via método 3 (base específica)");
                return $user;
            }
        } catch (\Exception $e) {
            $this->line("   ⚠️  Método 3 falhou: " . $e->getMessage());
        }

        // Método 4: Busca geral e filtragem por DN
        try {
            $users = LdapUserModel::where('uid', $uid)->get();
            
            foreach ($users as $user) {
                $dn = $user->getDn();
                if (stripos($dn, "ou={$ou},") !== false) {
                    $this->line("   ✅ Encontrado via método 4 (filtragem DN)");
                    return $user;
                }
            }
        } catch (\Exception $e) {
            $this->line("   ⚠️  Método 4 falhou: " . $e->getMessage());
        }

        return null;
    }
} 