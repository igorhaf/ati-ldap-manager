<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\LdapUserController;

class TestEmployeeNumberApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:employee-number-api {uid?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a API para verificar se employeeNumber está sendo retornado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        if (!$uid) {
            $uid = $this->ask('Digite o UID do usuário para testar');
        }

        $this->info('🔍 Teste da API - Employee Number');
        $this->info('==================================');
        $this->line("UID: {$uid}");

        try {
            // Simular autenticação
            $this->info("\n1️⃣ Simulando autenticação...");
            $user = \App\Ldap\LdapUserModel::where('uid', 'admin')->first();
            if (!$user) {
                $this->error("❌ Usuário admin não encontrado para autenticação");
                return;
            }
            
            auth()->login($user);
            $this->info("✅ Autenticado como: " . auth()->user()->getFirstAttribute('uid'));

            // 2. Testar método index()
            $this->info("\n2️⃣ Testando método index()...");
            $controller = new LdapUserController();
            $request = new Request();
            
            $response = $controller->index();
            $data = json_decode($response->getContent(), true);
            
            if ($data['success']) {
                $this->info("✅ API index() funcionando");
                $this->line("Total de usuários: " . count($data['data']));
                
                // Procurar o usuário específico
                $targetUser = collect($data['data'])->firstWhere('uid', $uid);
                if ($targetUser) {
                    $this->info("✅ Usuário encontrado na lista:");
                    $this->line("  - UID: {$targetUser['uid']}");
                    $this->line("  - Nome: {$targetUser['givenName']}");
                    $this->line("  - Sobrenome: {$targetUser['sn']}");
                    $this->line("  - Email: {$targetUser['mail']}");
                    $this->line("  - Employee Number: " . ($targetUser['employeeNumber'] ?: 'NULL'));
                    $this->line("  - OUs: " . json_encode($targetUser['organizationalUnits']));
                } else {
                    $this->warn("⚠️  Usuário não encontrado na lista");
                }
            } else {
                $this->error("❌ API index() falhou: " . $data['message']);
            }

            // 3. Testar método show()
            $this->info("\n3️⃣ Testando método show()...");
            $response = $controller->show($uid);
            $data = json_decode($response->getContent(), true);
            
            if ($data['success']) {
                $this->info("✅ API show() funcionando");
                $userData = $data['data'];
                $this->line("Dados do usuário:");
                $this->line("  - UID: {$userData['uid']}");
                $this->line("  - Nome: {$userData['givenName']}");
                $this->line("  - Sobrenome: {$userData['sn']}");
                $this->line("  - Email: {$userData['mail']}");
                $this->line("  - Employee Number: " . ($userData['employeeNumber'] ?: 'NULL'));
                $this->line("  - OUs: " . json_encode($userData['organizationalUnits']));
            } else {
                $this->error("❌ API show() falhou: " . $data['message']);
            }

            // 4. Comparar dados
            $this->info("\n4️⃣ Comparando dados...");
            $indexUser = collect($data['success'] ? [$data['data']] : [])->firstWhere('uid', $uid);
            
            if ($indexUser && $data['success']) {
                $this->line("Comparação index() vs show():");
                $this->line("  - employeeNumber index(): " . ($indexUser['employeeNumber'] ?: 'NULL'));
                $this->line("  - employeeNumber show(): " . ($data['data']['employeeNumber'] ?: 'NULL'));
                
                if ($indexUser['employeeNumber'] === $data['data']['employeeNumber']) {
                    $this->info("✅ Dados consistentes entre index() e show()");
                } else {
                    $this->warn("⚠️  Dados inconsistentes entre index() e show()");
                }
            }

            // 5. Teste de frontend simulado
            $this->info("\n5️⃣ Simulando frontend...");
            $this->line("Dados que chegariam no frontend:");
            $this->line("JSON que seria enviado para openEditUserModal():");
            $this->line(json_encode($data['success'] ? $data['data'] : null, JSON_PRETTY_PRINT));

            // 6. Diagnóstico final
            $this->info("\n6️⃣ Diagnóstico final...");
            if ($data['success'] && $data['data']['employeeNumber']) {
                $this->info("✅ employeeNumber está sendo retornado pela API");
                $this->line("O problema pode estar no frontend ou na forma como os dados são processados.");
                $this->line("Verifique o console do navegador para ver os logs de debug.");
            } else {
                $this->warn("⚠️  employeeNumber não está sendo retornado pela API");
                $this->line("Verifique se o usuário tem o atributo employeeNumber no LDAP.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Erro durante teste: " . $e->getMessage());
            $this->error("Arquivo: " . $e->getFile() . ':' . $e->getLine());
        }

        return 0;
    }
} 