<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;
use App\Services\RoleResolver;

class TestOuAdminRestriction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ou-admin-restriction {adminUid} {targetOu}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa as restrições de criação de usuários para admin de OU';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $adminUid = $this->argument('adminUid');
        $targetOu = $this->argument('targetOu');

        $this->info('🧪 Testando restrições para Admin de OU...');
        $this->info('==========================================');

        // Buscar o usuário admin
        $adminUser = LdapUserModel::where('uid', $adminUid)->first();
        if (!$adminUser) {
            $this->error("❌ Usuário admin '{$adminUid}' não encontrado!");
            return 1;
        }

        // Verificar se é admin de OU
        $role = RoleResolver::resolve($adminUser);
        $adminOu = RoleResolver::getUserOu($adminUser);

        $this->info("👤 Usuário: {$adminUid}");
        $this->info("🎭 Papel: {$role}");
        $this->info("🏢 OU do Admin: {$adminOu}");
        $this->info("🎯 OU Alvo: {$targetOu}");

        if ($role !== RoleResolver::ROLE_OU_ADMIN) {
            $this->warn("⚠️ Usuário não é admin de OU. Papel atual: {$role}");
            return 1;
        }

        // Testar cenários
        $this->info("\n📋 Cenários de Teste:");
        $this->info("─────────────────────");

        // Cenário 1: Criação na própria OU
        if (strtolower($targetOu) === strtolower($adminOu)) {
            $this->info("✅ Cenário 1: Criação na própria OU");
            $this->line("   - Resultado esperado: PERMITIDO");
            $this->line("   - Admin '{$adminUid}' pode criar usuários na OU '{$targetOu}'");
        } else {
            $this->info("❌ Cenário 2: Criação em OU diferente");
            $this->line("   - Resultado esperado: NEGADO");
            $this->line("   - Admin '{$adminUid}' NÃO pode criar usuários na OU '{$targetOu}'");
            $this->line("   - Só pode criar na OU '{$adminOu}'");
        }

        // Mostrar exemplo de payload de teste
        $this->info("\n🔧 Payload de Teste (via cURL):");
        $this->info("──────────────────────────────");
        
        $testPayload = [
            'uid' => 'test.user.ou',
            'givenName' => 'Teste',
            'sn' => 'OU',
            'mail' => 'teste.ou@empresa.com',
            'userPassword' => 'senha123',
            'employeeNumber' => '99999',
            'organizationalUnits' => [
                ['ou' => $targetOu, 'role' => 'user']
            ]
        ];

        $this->line('curl -X POST /api/ldap/users \\');
        $this->line('  -H "Content-Type: application/json" \\');
        $this->line('  -H "X-CSRF-TOKEN: SEU_TOKEN" \\');
        $this->line("  -d '" . json_encode($testPayload, JSON_PRETTY_PRINT) . "'");

        // Resultado esperado
        if (strtolower($targetOu) === strtolower($adminOu)) {
            $this->info("\n✅ Resultado Esperado: HTTP 201 - Usuário criado com sucesso");
        } else {
            $this->info("\n❌ Resultado Esperado: HTTP 403 - Acesso negado");
            $this->line("   Mensagem: \"Acesso negado: você só pode criar usuários na OU '{$adminOu}'\"");
        }

        // Mostrar usuários da OU do admin
        $this->info("\n👥 Usuários atuais na OU '{$adminOu}':");
        $this->info("────────────────────────────────");
        
        $usersInOu = LdapUserModel::where('ou', $adminOu)->get();
        if ($usersInOu->count() > 0) {
            foreach ($usersInOu as $user) {
                $userRole = $user->getAttribute('employeeType');
                $roleDisplay = is_array($userRole) ? $userRole[0] : $userRole;
                $this->line("- {$user->getFirstAttribute('uid')} ({$roleDisplay})");
            }
        } else {
            $this->line("(Nenhum usuário encontrado)");
        }

        $this->info("\n✅ Teste de restrições concluído!");
        return 0;
    }
} 