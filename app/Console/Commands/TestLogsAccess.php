<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OperationLog;

class TestLogsAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:test-access {ou?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa o acesso aos logs por OU';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ou = $this->argument('ou');

        $this->info('🔍 Testando acesso aos logs...');
        $this->info('==========================================');

        // Mostrar todos os logs se não especificar OU
        if (!$ou) {
            $logs = OperationLog::orderBy('created_at', 'desc')->get();
            $this->info("📊 Total de logs no sistema: {$logs->count()}");
            
            if ($logs->count() > 0) {
                $this->info("\n📋 Últimos 5 logs:");
                foreach ($logs->take(5) as $log) {
                    $this->line("- [{$log->id}] {$log->operation} | {$log->entity} | OU: {$log->ou} | {$log->created_at}");
                }
            }

            // Mostrar OUs disponíveis
            $ous = OperationLog::whereNotNull('ou')->distinct()->pluck('ou');
            $this->info("\n🏢 OUs com logs disponíveis:");
            foreach ($ous as $ouName) {
                $count = OperationLog::where('ou', $ouName)->count();
                $this->line("- {$ouName}: {$count} logs");
            }
        } else {
            // Mostrar logs específicos da OU
            $logs = OperationLog::where('ou', $ou)->orderBy('created_at', 'desc')->get();
            $this->info("📊 Logs para OU '{$ou}': {$logs->count()}");
            
            if ($logs->count() > 0) {
                $this->info("\n📋 Logs da OU {$ou}:");
                foreach ($logs->take(10) as $log) {
                    $this->line("- [{$log->id}] {$log->operation} | {$log->entity} | {$log->description} | {$log->created_at}");
                }
            } else {
                $this->warn("❌ Nenhum log encontrado para a OU '{$ou}'");
            }
        }

        $this->info("\n✅ Teste concluído!");
        return 0;
    }
} 