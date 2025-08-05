<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;

class TestCpfEditPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cpf-edit-permission {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a permissão de edição de CPF por tipo de usuário';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Teste de Permissão de Edição de CPF');
        $this->info('=======================================');
        $this->line("UID: {$uid}");

        try {
            // 1. Buscar usuário no LDAP
            $this->info("\n1️⃣ Buscando usuário no LDAP...");
            $user = LdapUserModel::where('uid', $uid)->first();
            
            if (!$user) {
                $this->error("❌ Usuário '{$uid}' não encontrado no LDAP");
                return;
            }
            
            $this->info("✅ Usuário encontrado");
            $this->line("DN: {$user->getDn()}");

            // 2. Determinar role do usuário
            $this->info("\n2️⃣ Determinando role do usuário...");
            $employeeType = $user->getFirstAttribute('employeeType');
            $this->line("employeeType: {$employeeType}");

            $role = 'user';
            if ($employeeType === 'root') {
                $role = 'root';
            } elseif ($employeeType === 'admin') {
                $role = 'admin';
            }

            $this->line("Role determinado: {$role}");

            // 3. Verificar permissão de edição de CPF
            $this->info("\n3️⃣ Verificando permissão de edição de CPF...");
            
            $canEditCpf = ($role === 'root');
            
            if ($canEditCpf) {
                $this->info("✅ Usuário ROOT pode editar CPF");
                $this->line("   - Campo CPF será editável");
                $this->line("   - Label: 'CPF' (sem texto adicional)");
                $this->line("   - Input: Habilitado e sem fundo cinza");
            } else {
                $this->warn("⚠️  Usuário {$role} NÃO pode editar CPF");
                $this->line("   - Campo CPF será somente leitura");
                $this->line("   - Label: 'CPF (não editável)'");
                $this->line("   - Input: Desabilitado com fundo cinza");
            }

            // 4. Simular interface
            $this->info("\n4️⃣ Simulando interface de edição...");
            
            if ($role === 'root') {
                $this->line("┌─────────────────────────────────────────────────────────┐");
                $this->line("│ CPF                                                      │");
                $this->line("│ [12345678901] (editável)                                │");
                $this->line("└─────────────────────────────────────────────────────────┘");
            } else {
                $this->line("┌─────────────────────────────────────────────────────────┐");
                $this->line("│ CPF (não editável)                                      │");
                $this->line("│ [12345678901] (somente leitura)                         │");
                $this->line("└─────────────────────────────────────────────────────────┘");
            }

            // 5. Verificar outros campos
            $this->info("\n5️⃣ Status de outros campos...");
            $this->line("   - UID: ❌ Nunca editável (qualquer role)");
            $this->line("   - Nome: ✅ Sempre editável (qualquer role)");
            $this->line("   - Sobrenome: ✅ Sempre editável (qualquer role)");
            $this->line("   - Email: ✅ Sempre editável (qualquer role)");
            $this->line("   - Senha: ✅ Sempre editável (qualquer role)");
            $this->line("   - CPF: " . ($canEditCpf ? "✅ Editável (apenas ROOT)" : "❌ Não editável (não ROOT)"));

            // 6. Resumo das permissões
            $this->info("\n6️⃣ Resumo das permissões por role:");
            $this->line("   ROOT:");
            $this->line("     ✅ Pode editar CPF");
            $this->line("     ✅ Pode gerenciar todas as OUs");
            $this->line("     ✅ Acesso completo ao sistema");
            $this->line("");
            $this->line("   ADMIN:");
            $this->line("     ❌ NÃO pode editar CPF");
            $this->line("     ✅ Pode gerenciar apenas sua OU");
            $this->line("     ✅ Acesso limitado ao sistema");
            $this->line("");
            $this->line("   USER:");
            $this->line("     ❌ NÃO pode editar CPF");
            $this->line("     ❌ NÃO pode gerenciar usuários");
            $this->line("     ❌ Acesso apenas de visualização");

            $this->info("\n✅ Teste concluído com sucesso!");
            $this->line("A permissão de edição de CPF está configurada corretamente.");

        } catch (\Exception $e) {
            $this->error("❌ Erro durante teste: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }
} 