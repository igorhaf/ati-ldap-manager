<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\LdapUserModel;

class DebugEmployeeNumber extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:employee-number {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debuga o problema do employeeNumber não aparecer no modal de edição';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Debug do Employee Number');
        $this->info('==========================');
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

            // 2. Verificar todos os atributos do usuário
            $this->info("\n2️⃣ Verificando todos os atributos...");
            $this->line("Atributos disponíveis:");
            
            $attributes = $user->getAttributes();
            foreach ($attributes as $attr => $values) {
                $value = is_array($values) ? implode(', ', $values) : $values;
                $this->line("  - {$attr}: {$value}");
            }

            // 3. Verificar employeeNumber especificamente
            $this->info("\n3️⃣ Verificando employeeNumber...");
            $employeeNumber = $user->getFirstAttribute('employeeNumber');
            $this->line("employeeNumber (getFirstAttribute): " . ($employeeNumber ?: 'NULL'));
            
            $employeeNumberArray = $user->getAttribute('employeeNumber');
            $this->line("employeeNumber (getAttribute): " . (is_array($employeeNumberArray) ? implode(', ', $employeeNumberArray) : ($employeeNumberArray ?: 'NULL')));

            // 4. Simular o que o método index() retorna
            $this->info("\n4️⃣ Simulando retorno do método index()...");
            $indexData = [
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
            
            $this->line("Dados que seriam retornados pelo index():");
            foreach ($indexData as $key => $value) {
                if (is_array($value)) {
                    $this->line("  - {$key}: " . json_encode($value));
                } else {
                    $this->line("  - {$key}: " . ($value ?: 'NULL'));
                }
            }

            // 5. Simular o que o método show() retorna
            $this->info("\n5️⃣ Simulando retorno do método show()...");
            $showData = [
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
            
            $this->line("Dados que seriam retornados pelo show():");
            foreach ($showData as $key => $value) {
                if (is_array($value)) {
                    $this->line("  - {$key}: " . json_encode($value));
                } else {
                    $this->line("  - {$key}: " . ($value ?: 'NULL'));
                }
            }

            // 6. Verificar se o atributo existe no schema
            $this->info("\n6️⃣ Verificando schema LDAP...");
            $this->line("Verificando se employeeNumber está definido no schema...");
            
            // Tentar buscar todos os usuários que têm employeeNumber
            $usersWithEmployeeNumber = LdapUserModel::where('employeeNumber', '*')->get();
            $this->line("Usuários com employeeNumber: " . $usersWithEmployeeNumber->count());
            
            if ($usersWithEmployeeNumber->count() > 0) {
                $this->line("Exemplos de employeeNumber encontrados:");
                foreach ($usersWithEmployeeNumber->take(3) as $u) {
                    $this->line("  - {$u->getFirstAttribute('uid')}: {$u->getFirstAttribute('employeeNumber')}");
                }
            }

            // 7. Teste de API
            $this->info("\n7️⃣ Testando API endpoints...");
            $this->line("Para testar via API, execute:");
            $this->line("curl -X GET 'http://localhost/api/ldap/users' -H 'Accept: application/json'");
            $this->line("curl -X GET 'http://localhost/api/ldap/users/{$uid}' -H 'Accept: application/json'");

            // 8. Diagnóstico
            $this->info("\n8️⃣ Diagnóstico...");
            if ($employeeNumber) {
                $this->info("✅ employeeNumber existe no LDAP: {$employeeNumber}");
                $this->line("O problema pode estar no frontend ou na forma como os dados são processados.");
            } else {
                $this->warn("⚠️  employeeNumber não existe no LDAP");
                $this->line("O usuário pode não ter o atributo employeeNumber definido.");
                $this->line("Verifique se o schema LDAP inclui este atributo.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Erro durante debug: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }
} 