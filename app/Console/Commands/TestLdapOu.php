<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Ldap\OrganizationalUnit;

class TestLdapOu extends Command
{
    protected $signature = 'ldap:test-ou {name} {description?}';
    protected $description = 'Testa a criação de uma unidade organizacional LDAP';

    public function handle()
    {
        $name = $this->argument('name');
        $description = $this->argument('description') ?? 'Teste via comando';

        $this->info("Testando criação da OU: {$name}");

        try {
            $ou = new OrganizationalUnit();
            $ou->ou = $name;
            $ou->description = $description;

            $baseDn = config('ldap.connections.default.base_dn');
            $ou->setDn("ou={$name},{$baseDn}");

            $this->info("DN configurado: " . $ou->getDn());
            $this->info("Tentando salvar...");

            $ou->save();

            $this->info("✅ OU criada com sucesso!");
            $this->info("DN: " . $ou->getDn());
            $this->info("Nome: " . $ou->ou);
            $this->info("Descrição: " . $ou->description);

        } catch (\Exception $e) {
            $this->error("❌ Erro ao criar OU:");
            $this->error($e->getMessage());
            $this->error("Trace: " . $e->getTraceAsString());
        }
    }
} 