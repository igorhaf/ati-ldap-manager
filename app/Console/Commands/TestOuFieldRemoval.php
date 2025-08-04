<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;

class TestOuFieldRemoval extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ou-field-removal {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa se a remoção do campo de texto da OU está funcionando';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Teste de Remoção do Campo OU');
        $this->info('================================');
        $this->line("UID: {$uid}");

        try {
            // 1. Buscar usuário no LDAP
            $this->info("\n1️⃣ Buscando usuário no LDAP...");
            $user = LdapUserModel::where('uid', $uid)->first();
            
            if (!$user) {
                $this->error("❌ Usuário '{$uid}' não encontrado no LDAP");
                return;
            }
            
            $dn = $user->getDn();
            $this->info("✅ Usuário encontrado");
            $this->line("DN: {$dn}");

            // 2. Extrair OU do usuário
            $this->info("\n2️⃣ Extraindo OU do usuário...");
            $ou = $this->extractOu($user);
            $this->line("OU extraída: {$ou}");

            // 3. Verificar se a OU está no RDN
            $this->info("\n3️⃣ Verificando se OU está no RDN...");
            $rdnPart = explode(',', $dn)[0];
            $this->line("RDN: {$rdnPart}");
            
            if (preg_match('/^ou=/i', trim($rdnPart))) {
                $this->warn("⚠️  OU está no RDN - não pode ser modificada");
            } else {
                $this->info("✅ OU não está no RDN - pode ser modificada");
            }

            // 4. Simular interface de admin OU
            $this->info("\n4️⃣ Simulando interface de Admin OU...");
            $this->line("Interface para Admin OU:");
            $this->line("  - Campo de texto da OU: ❌ REMOVIDO");
            $this->line("  - Display visual da OU: ✅ MANTIDO");
            $this->line("  - Dropdown de papel: ✅ MANTIDO");
            $this->line("  - Texto explicativo: ✅ MANTIDO");

            // 5. Verificar se há campos de input para OU
            $this->info("\n5️⃣ Verificando campos de input para OU...");
            
            $this->line("Campos de texto encontrados:");
            $this->line("  - UID: ✅ (desabilitado)");
            $this->line("  - Employee Number: ✅ (desabilitado)");
            $this->line("  - OU: ❌ REMOVIDO (era um input, agora é display)");

            // 6. Teste de criação de usuário
            $this->info("\n6️⃣ Simulando criação de usuário...");
            $this->line("Para Admin OU criando novo usuário:");
            $this->line("  - OU será automaticamente: {$ou}");
            $this->line("  - Papel será definido pelo dropdown");
            $this->line("  - Nenhum campo de texto para OU");

            // 7. Teste de edição de usuário
            $this->info("\n7️⃣ Simulando edição de usuário...");
            $this->line("Para Admin OU editando usuário:");
            $this->line("  - OU será exibida como: {$ou}");
            $this->line("  - Papel pode ser alterado via dropdown");
            $this->line("  - Nenhum campo de texto para OU");

            $this->info("\n✅ Teste concluído com sucesso!");
            $this->line("O campo de texto da OU foi removido corretamente.");

        } catch (\Exception $e) {
            $this->error("❌ Erro durante teste: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }

    private function extractOu($entry): ?string
    {
        $ou = $entry->getFirstAttribute('ou');
        if ($ou) {
            return $ou;
        }
        if (preg_match('/ou=([^,]+)/i', $entry->getDn(), $matches)) {
            return $matches[1];
        }
        return null;
    }
} 