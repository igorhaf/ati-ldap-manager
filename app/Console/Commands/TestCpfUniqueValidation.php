<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;
use App\Http\Controllers\LdapUserController;
use Illuminate\Http\Request;

class TestCpfUniqueValidation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cpf-unique-validation {cpf} {ou} {--exclude-uid=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a validação de CPF único por OU';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cpf = $this->argument('cpf');
        $targetOu = $this->argument('ou');
        $excludeUid = $this->option('exclude-uid');

        $this->info('🧪 Teste de Validação de CPF Único por OU');
        $this->info('=========================================');
        $this->info("CPF: {$cpf}");
        $this->info("OU: {$targetOu}");
        if ($excludeUid) {
            $this->info("Excluir UID: {$excludeUid}");
        }
        $this->newLine();

        try {
            // 1. Buscar todos os usuários com este CPF
            $this->info('1️⃣ Buscando usuários com este CPF em todas as OUs...');
            $allUsersWithCpf = LdapUserModel::where('employeeNumber', $cpf)->get();
            
            if ($allUsersWithCpf->isEmpty()) {
                $this->info('✅ Nenhum usuário encontrado com este CPF em qualquer OU');
                $this->info('✅ CPF está disponível para uso na OU especificada');
                return 0;
            }

            $this->warn("⚠️  Encontrados {$allUsersWithCpf->count()} usuário(s) com este CPF em todas as OUs:");
            $this->newLine();

            // 2. Mostrar todos os usuários encontrados
            foreach ($allUsersWithCpf as $index => $user) {
                $uid = $user->getFirstAttribute('uid');
                $givenName = $user->getFirstAttribute('givenName') ?? '';
                $sn = $user->getFirstAttribute('sn') ?? '';
                $fullName = trim($givenName . ' ' . $sn);
                $dn = $user->getDn();
                
                // Extrair OU do DN
                $ou = 'Não definida';
                if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
                    $ou = $matches[1];
                }

                $this->info("   👤 Usuário " . ($index + 1) . ":");
                $this->info("      UID: {$uid}");
                $this->info("      Nome: {$fullName}");
                $this->info("      OU: {$ou}");
                $this->info("      DN: {$dn}");
                $this->newLine();
            }

            // 3. Filtrar apenas usuários na OU especificada
            $this->info('2️⃣ Filtrando usuários na OU especificada...');
            $usersInTargetOu = $allUsersWithCpf->filter(function($user) use ($targetOu) {
                $dn = $user->getDn();
                if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
                    $userOu = $matches[1];
                    return strtolower($userOu) === strtolower($targetOu);
                }
                return false;
            });

            if ($usersInTargetOu->isEmpty()) {
                $this->info("✅ Nenhum usuário encontrado com este CPF na OU '{$targetOu}'");
                $this->info('✅ CPF está disponível para uso nesta OU');
                return 0;
            }

            $this->warn("⚠️  Encontrados {$usersInTargetOu->count()} usuário(s) com este CPF na OU '{$targetOu}':");
            foreach ($usersInTargetOu as $user) {
                $uid = $user->getFirstAttribute('uid');
                $fullName = trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? ''));
                $this->warn("   - {$fullName} (UID: {$uid})");
            }

            // 4. Testar lógica de exclusão (se especificado)
            if ($excludeUid) {
                $this->info('3️⃣ Testando lógica de exclusão...');
                
                $filteredUsers = $usersInTargetOu->reject(function($user) use ($excludeUid) {
                    return $user->getFirstAttribute('uid') === $excludeUid;
                });

                if ($filteredUsers->isEmpty()) {
                    $this->info("✅ Após excluir UID '{$excludeUid}', CPF fica disponível na OU '{$targetOu}'");
                    $this->info('✅ Edição seria permitida');
                } else {
                    $this->error("❌ Mesmo excluindo UID '{$excludeUid}', ainda há conflito na OU '{$targetOu}':");
                    foreach ($filteredUsers as $user) {
                        $uid = $user->getFirstAttribute('uid');
                        $name = trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? ''));
                        $this->error("   - {$name} (UID: {$uid})");
                    }
                    $this->error('❌ Edição seria bloqueada');
                }
            } else {
                $this->error("❌ CPF já está em uso na OU '{$targetOu}'");
                $this->error('❌ Criação seria bloqueada');
            }

            // 5. Mostrar análise de outras OUs
            $this->newLine();
            $this->info('4️⃣ Análise de outras OUs:');
            $otherOus = $allUsersWithCpf->filter(function($user) use ($targetOu) {
                $dn = $user->getDn();
                if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
                    $userOu = $matches[1];
                    return strtolower($userOu) !== strtolower($targetOu);
                }
                return false;
            });

            if ($otherOus->isNotEmpty()) {
                $this->info("✅ CPF também existe em outras OUs (isso é permitido):");
                foreach ($otherOus as $user) {
                    $uid = $user->getFirstAttribute('uid');
                    $name = trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? ''));
                    $dn = $user->getDn();
                    $ou = 'Não definida';
                    if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
                        $ou = $matches[1];
                    }
                    $this->info("   - {$name} (UID: {$uid}) na OU: {$ou}");
                }
            }

            // 6. Testar mensagem de erro que seria exibida
            $this->newLine();
            $this->info('5️⃣ Mensagem de erro que seria exibida:');
            
            $conflictUser = $usersInTargetOu->first();
            $conflictName = trim(($conflictUser->getFirstAttribute('givenName') ?? '') . ' ' . ($conflictUser->getFirstAttribute('sn') ?? ''));
            $conflictUid = $conflictUser->getFirstAttribute('uid');
            
            $errorMessage = "CPF {$cpf} já está cadastrado para o usuário '{$conflictName}' (UID: {$conflictUid}) na(s) OU(s): {$targetOu}. Não é possível ter usuários diferentes com o mesmo CPF na mesma OU.";
            
            $this->line("📝 \"{$errorMessage}\"");

            return 1;

        } catch (\Exception $e) {
            $this->error('❌ Erro durante o teste: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
} 