<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;

class TestEmployeeNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:employee-number {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa se o atributo employeeNumber está sendo retornado corretamente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Teste do Atributo Employee Number');
        $this->info('===================================');
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

            // 2. Verificar todos os atributos
            $this->info("\n2️⃣ Verificando todos os atributos...");
            $attributes = $user->getAttributes();
            
            $this->line("Atributos disponíveis:");
            foreach ($attributes as $attr => $values) {
                $value = is_array($values) ? implode(', ', $values) : $values;
                $this->line("  - {$attr}: {$value}");
            }

            // 3. Verificar employeeNumber especificamente
            $this->info("\n3️⃣ Verificando employeeNumber especificamente...");
            $employeeNumber = $user->getFirstAttribute('employeeNumber');
            
            if ($employeeNumber) {
                $this->info("✅ employeeNumber encontrado: {$employeeNumber}");
            } else {
                $this->warn("⚠️  employeeNumber NÃO encontrado");
            }

            // 4. Verificar outros atributos importantes
            $this->info("\n4️⃣ Verificando outros atributos importantes...");
            $importantAttrs = ['uid', 'givenName', 'sn', 'mail', 'cn', 'ou'];
            
            foreach ($importantAttrs as $attr) {
                $value = $user->getFirstAttribute($attr);
                if ($value) {
                    $this->info("✅ {$attr}: {$value}");
                } else {
                    $this->warn("⚠️  {$attr}: NÃO encontrado");
                }
            }

            // 5. Simular resposta da API
            $this->info("\n5️⃣ Simulando resposta da API...");
            $apiResponse = [
                'dn' => $user->getDn(),
                'uid' => $user->getFirstAttribute('uid'),
                'givenName' => $user->getFirstAttribute('givenName'),
                'sn' => $user->getFirstAttribute('sn'),
                'cn' => $user->getFirstAttribute('cn'),
                'fullName' => trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? '')),
                'mail' => $user->getFirstAttribute('mail'),
                'employeeNumber' => $user->getFirstAttribute('employeeNumber'),
                'organizationalUnits' => $user->getAttribute('ou') ?? [],
            ];

            $this->line("Resposta simulada da API:");
            foreach ($apiResponse as $key => $value) {
                if (is_array($value)) {
                    $this->line("  - {$key}: " . json_encode($value));
                } else {
                    $this->line("  - {$key}: " . ($value ?: 'null'));
                }
            }

            // 6. Verificar se employeeNumber está vazio ou null
            $this->info("\n6️⃣ Análise do employeeNumber...");
            if ($employeeNumber === null) {
                $this->error("❌ employeeNumber é NULL");
            } elseif ($employeeNumber === '') {
                $this->warn("⚠️  employeeNumber está vazio (string vazia)");
            } elseif (trim($employeeNumber) === '') {
                $this->warn("⚠️  employeeNumber contém apenas espaços em branco");
            } else {
                $this->info("✅ employeeNumber tem valor: '{$employeeNumber}'");
            }

            // 7. Verificar se o problema pode ser no frontend
            $this->info("\n7️⃣ Verificando possível problema no frontend...");
            if ($employeeNumber) {
                $this->line("✅ Backend está retornando employeeNumber");
                $this->line("🔍 Verifique se o frontend está carregando corretamente");
                $this->line("🔍 Verifique se o v-model está funcionando");
            } else {
                $this->line("❌ Backend NÃO está retornando employeeNumber");
                $this->line("🔍 Verifique se o atributo existe no LDAP");
                $this->line("🔍 Verifique se o schema LDAP inclui employeeNumber");
            }

        } catch (\Exception $e) {
            $this->error("❌ Erro durante teste: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }
} 