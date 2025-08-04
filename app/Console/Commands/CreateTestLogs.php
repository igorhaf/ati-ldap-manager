<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OperationLog;

class CreateTestLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:create-test {ou} {--count=5}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria logs de teste para uma OU específica';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ou = $this->argument('ou');
        $count = $this->option('count');

        $this->info("🔄 Criando {$count} logs de teste para OU '{$ou}'...");

        $operations = [
            ['operation' => 'create_user', 'entity' => 'User', 'description' => 'Usuário criado via interface'],
            ['operation' => 'update_user', 'entity' => 'User', 'description' => 'Usuário atualizado'],
            ['operation' => 'delete_user', 'entity' => 'User', 'description' => 'Usuário removido'],
            ['operation' => 'update_password', 'entity' => 'User', 'description' => 'Senha alterada'],
            ['operation' => 'create_user_ldif', 'entity' => 'User', 'description' => 'Usuário criado via LDIF'],
        ];

        for ($i = 1; $i <= $count; $i++) {
            $operation = $operations[array_rand($operations)];
            
            OperationLog::create([
                'operation' => $operation['operation'],
                'entity' => $operation['entity'],
                'entity_id' => "test.user{$i}",
                'ou' => $ou,
                'description' => $operation['description'] . " (teste #{$i})",
            ]);

            $this->line("✅ Log {$i}/{$count} criado");
        }

        $this->info("\n🎉 {$count} logs de teste criados com sucesso para OU '{$ou}'!");
        
        // Mostrar resumo
        $totalLogs = OperationLog::where('ou', $ou)->count();
        $this->info("📊 Total de logs na OU '{$ou}': {$totalLogs}");

        return 0;
    }
} 