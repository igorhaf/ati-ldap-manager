<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LdifService;

class TestLdifGeneration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ldif:test-generation {uid} {givenName} {sn} {employeeNumber} {mail} {password} {ous*}';

    /**
     * The console command description.
     */
    protected $description = 'Testa a geração de LDIF para um usuário em múltiplas OUs';

    protected LdifService $ldifService;

    public function __construct(LdifService $ldifService)
    {
        parent::__construct();
        $this->ldifService = $ldifService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userData = [
            'uid' => $this->argument('uid'),
            'givenName' => $this->argument('givenName'),
            'sn' => $this->argument('sn'),
            'employeeNumber' => $this->argument('employeeNumber'),
            'mail' => $this->argument('mail'),
            'userPassword' => $this->argument('password'),
        ];

        $ous = $this->argument('ous');
        $organizationalUnits = [];

        foreach ($ous as $ou) {
            // Formato: "OU:role" ou apenas "OU"
            if (strpos($ou, ':') !== false) {
                [$ouName, $role] = explode(':', $ou, 2);
                $organizationalUnits[] = ['ou' => $ouName, 'role' => $role];
            } else {
                $organizationalUnits[] = ['ou' => $ou, 'role' => 'user'];
            }
        }

        $this->info('Gerando LDIF para o usuário: ' . $userData['uid']);
        $this->info('OUs: ' . implode(', ', array_column($organizationalUnits, 'ou')));
        $this->newLine();

        try {
            $ldif = $this->ldifService->generateUserLdif($userData, $organizationalUnits);
            
            $this->line('LDIF Gerado:');
            $this->line('=' . str_repeat('=', 60));
            $this->line($ldif);
            $this->line('=' . str_repeat('=', 60));
            
            // Salvar em arquivo
            $filename = "test_user_{$userData['uid']}_" . date('Y-m-d_H-i-s') . ".ldif";
            file_put_contents(storage_path('app/' . $filename), $ldif);
            
            $this->info("LDIF salvo em: storage/app/{$filename}");

        } catch (\Exception $e) {
            $this->error('Erro ao gerar LDIF: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
} 