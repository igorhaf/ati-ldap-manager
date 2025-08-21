<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;

class TestNamingViolation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:naming-violation {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa problemas de Naming Violation no LDAP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Teste de Naming Violation');
        $this->info('===========================');
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

            // 2. Analisar o RDN
            $this->info("\n2️⃣ Analisando RDN (Relative Distinguished Name)...");
            $rdnPart = explode(',', $dn)[0];
            $this->line("RDN: {$rdnPart}");

            // Extrair atributo e valor do RDN
            if (preg_match('/^([^=]+)=(.+)$/', trim($rdnPart), $matches)) {
                $rdnAttribute = trim($matches[1]);
                $rdnValue = trim($matches[2]);
                $this->line("Atributo RDN: {$rdnAttribute}");
                $this->line("Valor RDN: {$rdnValue}");

                // 3. Verificar se atributos comuns estão no RDN
                $this->info("\n3️⃣ Verificando atributos que podem causar Naming Violation...");
                
                $commonAttributes = ['cn', 'uid', 'ou', 'dc'];
                foreach ($commonAttributes as $attr) {
                    $isInRdn = $this->isAttributeInRdn($user, $attr);
                    if ($isInRdn) {
                        $this->error("❌ {$attr} está no RDN - NÃO PODE ser modificado");
                    } else {
                        $this->info("✅ {$attr} não está no RDN - PODE ser modificado");
                    }
                }

                // 4. Simular atualização do CN
                $this->info("\n4️⃣ Simulando atualização do CN...");
                $currentCn = $user->getFirstAttribute('cn');
                $currentGivenName = $user->getFirstAttribute('givenName');
                $currentSn = $user->getFirstAttribute('sn');
                
                $this->line("CN atual: {$currentCn}");
                $this->line("givenName atual: {$currentGivenName}");
                $this->line("sn atual: {$currentSn}");

                $newCn = trim("{$currentGivenName} {$currentSn}");
                $this->line("CN calculado: {$newCn}");

                if ($this->isAttributeInRdn($user, 'cn')) {
                    $this->warn("⚠️  Atualização do CN seria ignorada (está no RDN)");
                } else {
                    $this->info("✅ Atualização do CN seria permitida");
                }

                // 5. Testar método setSafeAttribute
                $this->info("\n5️⃣ Testando método setSafeAttribute...");
                $result = $this->setSafeAttribute($user, 'cn', $newCn);
                if ($result) {
                    $this->info("✅ setSafeAttribute retornou true (atributo seria atualizado)");
                } else {
                    $this->warn("⚠️  setSafeAttribute retornou false (atributo ignorado)");
                }

                // 6. Verificar outros atributos problemáticos
                $this->info("\n6️⃣ Outros atributos que podem causar problemas...");
                $this->checkAttribute($user, 'objectClass', 'Classe do objeto LDAP');
                $this->checkAttribute($user, 'entryDN', 'DN da entrada');
                $this->checkAttribute($user, 'entryUUID', 'UUID da entrada');
                
            } else {
                $this->error("❌ Não foi possível analisar o RDN");
            }

        } catch (\Exception $e) {
            $this->error("❌ Erro durante teste: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }

    private function isAttributeInRdn($entry, $attributeName): bool
    {
        $dn = $entry->getDn();
        if (!$dn) {
            return false;
        }

        // Extrair o RDN (primeira parte do DN)
        $rdnPart = explode(',', $dn)[0];
        
        // Verificar se o atributo está no RDN
        return preg_match("/^{$attributeName}=/i", trim($rdnPart));
    }

    private function setSafeAttribute($entry, $attributeName, $value): bool
    {
        if ($this->isAttributeInRdn($entry, $attributeName)) {
            $this->line("🛡️  Método setSafeAttribute bloqueou modificação de '{$attributeName}' (está no RDN)");
            return false;
        }

        $this->line("✅ Método setSafeAttribute permitiria modificação de '{$attributeName}'");
        return true;
    }

    private function checkAttribute($entry, $attributeName, $description)
    {
        $value = $entry->getFirstAttribute($attributeName);
        $isInRdn = $this->isAttributeInRdn($entry, $attributeName);
        
        $status = $isInRdn ? "❌ RDN" : "✅ Safe";
        $this->line("   {$status} {$attributeName}: {$description}");
        if ($value) {
            $this->line("         Valor: {$value}");
        }
    }
} 