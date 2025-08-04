<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;
use App\Services\RoleResolver;

class DebugUserOu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:user-ou {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug problema de OU do usuário logado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para debug');
        }

        $this->info('🔍 Debug de OU do Usuário');
        $this->info('========================');
        $this->line("UID: {$uid}");

        try {
            // 1. Buscar usuário no LDAP
            $this->info("\n1️⃣ Buscando usuário no LDAP...");
            $user = LdapUserModel::where('uid', $uid)->first();
            
            if (!$user) {
                $this->error("❌ Usuário '{$uid}' não encontrado no LDAP");
                return;
            }
            
            $this->info("✅ Usuário encontrado: {$user->getCommonName()}");

            // 2. Verificar OUs
            $this->info("\n2️⃣ Verificando OUs do usuário...");
            $ous = $user->getFirstAttribute('ou');
            if (is_array($ous)) {
                $this->line("📍 OUs (array): " . implode(', ', $ous));
            } else {
                $this->line("📍 OU (string): {$ous}");
            }

            // 3. Verificar organizationalUnits (se existir)
            $orgUnits = $user->getAttribute('organizationalUnits');
            if ($orgUnits) {
                $this->line("🏢 organizationalUnits: " . json_encode($orgUnits, JSON_PRETTY_PRINT));
            }

            // 4. Verificar employeeType (papel)
            $employeeType = $user->getFirstAttribute('employeeType');
            if ($employeeType) {
                if (is_array($employeeType)) {
                    $this->line("👤 employeeType (array): " . implode(', ', $employeeType));
                } else {
                    $this->line("👤 employeeType (string): {$employeeType}");
                }
            }

            // 5. Verificar role via RoleResolver
            $this->info("\n3️⃣ Verificando role via RoleResolver...");
            $role = RoleResolver::resolve($user);
            $this->line("🎭 Role resolvida: {$role}");

            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $userOu = RoleResolver::getUserOu($user);
                $this->line("🏢 OU do admin (via RoleResolver): {$userOu}");
            }

            // 6. Simular o que o frontend vê
            $this->info("\n4️⃣ Simulando estrutura para frontend...");
            
            // Verificar se tem múltiplas OUs
            $allOus = [];
            $ouAttr = $user->getFirstAttribute('ou');
            $employeeTypes = $user->getAttribute('employeeType') ?: [];
            
            if (is_array($ouAttr)) {
                foreach ($ouAttr as $index => $ou) {
                    $role = isset($employeeTypes[$index]) ? $employeeTypes[$index] : 'user';
                    $allOus[] = ['ou' => $ou, 'role' => $role];
                }
            } else {
                $role = is_array($employeeTypes) && count($employeeTypes) > 0 ? $employeeTypes[0] : 'user';
                $allOus[] = ['ou' => $ouAttr, 'role' => $role];
            }

            $this->line("📋 Estrutura organizationalUnits simulada:");
            foreach ($allOus as $unit) {
                $this->line("   - OU: {$unit['ou']}, Role: {$unit['role']}");
            }

            // 7. Encontrar OU admin
            $this->info("\n5️⃣ Encontrando OU admin...");
            $adminOu = null;
            foreach ($allOus as $unit) {
                if ($unit['role'] === 'admin') {
                    $adminOu = $unit['ou'];
                    $this->info("✅ OU Admin encontrada: {$adminOu}");
                    break;
                }
            }

            if (!$adminOu && count($allOus) > 0) {
                $adminOu = $allOus[0]['ou'];
                $this->warn("⚠️  Não encontrou OU admin, usando primeira OU: {$adminOu}");
            }

            if (!$adminOu) {
                $this->error("❌ Nenhuma OU encontrada!");
            }

            // 8. Verificar autenticação mock
            $this->info("\n6️⃣ Verificando dados para autenticação...");
            $this->line("DN: " . $user->getDn());
            $this->line("Mail: " . $user->getFirstAttribute('mail'));
            $this->line("CN: " . $user->getCommonName());

        } catch (\Exception $e) {
            $this->error("❌ Erro durante debug: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }
} 