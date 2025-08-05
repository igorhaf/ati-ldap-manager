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
    protected $signature = 'test:cpf-unique-validation {cpf} {--exclude-uid=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a validação de CPF único no sistema';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cpf = $this->argument('cpf');
        $excludeUid = $this->option('exclude-uid');

        $this->info('🧪 Teste de Validação de CPF Único');
        $this->info('=====================================');
        $this->info("CPF: {$cpf}");
        if ($excludeUid) {
            $this->info("Excluir UID: {$excludeUid}");
        }
        $this->newLine();

        try {
            // 1. Buscar usuários com este CPF
            $this->info('1️⃣ Buscando usuários com este CPF...');
            $existingUsers = LdapUserModel::where('employeeNumber', $cpf)->get();
            
            if ($existingUsers->isEmpty()) {
                $this->info('✅ Nenhum usuário encontrado com este CPF');
                $this->info('✅ CPF está disponível para uso');
                return 0;
            }

            $this->warn("⚠️  Encontrados {$existingUsers->count()} usuário(s) com este CPF:");
            $this->newLine();

            // 2. Mostrar usuários encontrados
            foreach ($existingUsers as $index => $user) {
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

            // 3. Testar lógica de exclusão (se especificado)
            if ($excludeUid) {
                $this->info('3️⃣ Testando lógica de exclusão...');
                
                $filteredUsers = $existingUsers->reject(function($user) use ($excludeUid) {
                    return $user->getFirstAttribute('uid') === $excludeUid;
                });

                if ($filteredUsers->isEmpty()) {
                    $this->info("✅ Após excluir UID '{$excludeUid}', CPF fica disponível");
                    $this->info('✅ Edição seria permitida');
                } else {
                    $this->error("❌ Mesmo excluindo UID '{$excludeUid}', ainda há conflito:");
                    foreach ($filteredUsers as $user) {
                        $uid = $user->getFirstAttribute('uid');
                        $name = trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? ''));
                        $this->error("   - {$name} (UID: {$uid})");
                    }
                    $this->error('❌ Edição seria bloqueada');
                }
            } else {
                $this->error('❌ CPF já está em uso');
                $this->error('❌ Criação seria bloqueada');
            }

            // 4. Testar mensagem de erro que seria exibida
            $this->newLine();
            $this->info('4️⃣ Mensagem de erro que seria exibida:');
            
            $conflictUser = $existingUsers->first();
            $conflictName = trim(($conflictUser->getFirstAttribute('givenName') ?? '') . ' ' . ($conflictUser->getFirstAttribute('sn') ?? ''));
            $conflictUid = $conflictUser->getFirstAttribute('uid');
            
            // Obter todas as OUs dos usuários
            $conflictOus = $existingUsers->map(function($user) {
                $dn = $user->getDn();
                if (preg_match('/ou=([^,]+)/i', $dn, $matches)) {
                    return $matches[1];
                }
                return 'Não definida';
            })->unique()->values()->toArray();
            
            $conflictOusStr = implode(', ', $conflictOus);
            
            $errorMessage = "CPF {$cpf} já está cadastrado para o usuário '{$conflictName}' (UID: {$conflictUid}) na(s) OU(s): {$conflictOusStr}";
            
            $this->line("📝 \"{$errorMessage}\"");

            return 1;

        } catch (\Exception $e) {
            $this->error('❌ Erro durante o teste: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
} 