<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RoleResolver;
use App\Ldap\LdapUserModel;

class TestRootAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:root-access {uid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa se um usuário tem acesso root e verifica a restrição de URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $uid = $this->argument('uid');
        
        $this->info("Testando acesso root para o usuário: {$uid}");
        
        try {
            // Buscar usuário no LDAP
            $user = LdapUserModel::where('uid', $uid)->first();
            
            if (!$user) {
                $this->error("Usuário {$uid} não encontrado no LDAP");
                return 1;
            }
            
            $this->info("Usuário encontrado: " . $user->getDn());
            
            // Verificar role
            $role = RoleResolver::resolve($user);
            $this->info("Role do usuário: {$role}");
            
            if ($role === RoleResolver::ROLE_ROOT) {
                $this->warn("⚠️  ATENÇÃO: Este usuário é ROOT!");
                $this->info("O acesso a este usuário não pode ser feito por essa URL");
                
                // Mostrar todas as entradas do usuário
                $entries = LdapUserModel::where('uid', $uid)->get();
                $this->info("Entradas encontradas: " . $entries->count());
                
                foreach ($entries as $entry) {
                    $ou = $entry->getFirstAttribute('ou');
                    $employeeType = $entry->getAttribute('employeeType');
                    $this->line("  - OU: {$ou}, employeeType: " . json_encode($employeeType));
                }
            } else {
                $this->info("✅ Usuário não é root, pode acessar normalmente");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Erro ao testar usuário: " . $e->getMessage());
            return 1;
        }
    }
} 