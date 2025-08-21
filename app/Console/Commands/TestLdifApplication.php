<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LdifService;

class TestLdifApplication extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ldif:test-apply {file}';

    /**
     * The console command description.
     */
    protected $description = 'Testa a aplicação de um arquivo LDIF';

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
        $filePath = $this->argument('file');
        
        // Verificar se é caminho absoluto ou relativo ao storage
        if (!file_exists($filePath)) {
            $storagePath = storage_path('app/' . $filePath);
            if (file_exists($storagePath)) {
                $filePath = $storagePath;
            } else {
                $this->error("Arquivo não encontrado: {$filePath}");
                return 1;
            }
        }

        $this->info("Aplicando LDIF do arquivo: {$filePath}");

        try {
            $ldifContent = file_get_contents($filePath);
            
            if (empty($ldifContent)) {
                $this->error('Arquivo LDIF está vazio');
                return 1;
            }

            $this->info('Conteúdo do arquivo:');
            $this->line('=' . str_repeat('=', 60));
            $this->line($ldifContent);
            $this->line('=' . str_repeat('=', 60));
            $this->newLine();

            if (!$this->confirm('Deseja aplicar este LDIF?', false)) {
                $this->info('Operação cancelada pelo usuário.');
                return 0;
            }

            $results = $this->ldifService->applyLdif($ldifContent);

            $this->info('Resultados da aplicação:');
            $this->newLine();

            $successCount = 0;
            $errorCount = 0;

            foreach ($results as $result) {
                if ($result['success']) {
                    $this->info("✅ {$result['dn']}: {$result['message']}");
                    $successCount++;
                } else {
                    $this->error("❌ {$result['dn']}: {$result['message']}");
                    $errorCount++;
                }
            }

            $this->newLine();
            $this->info("Resumo: {$successCount} sucessos, {$errorCount} erros de " . count($results) . " entradas");

            return $errorCount > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error('Erro ao aplicar LDIF: ' . $e->getMessage());
            return 1;
        }
    }
} 