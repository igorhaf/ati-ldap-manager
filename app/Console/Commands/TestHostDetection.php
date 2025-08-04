<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestHostDetection extends Command
{
    protected $signature = 'test:host-detection {host?}';
    protected $description = 'Testa a detecção de OU a partir de um host simulado';

    public function handle()
    {
        $testHost = $this->argument('host');
        
        if (!$testHost) {
            $this->info('🧪 Testando URLs dinâmicas...');
            $hosts = [
                'admin.empresa.com',
                'moreno.empresa.com', 
                'teste.localhost',
                'contabilidade.sistema.br',
                'rh.plataforma.net',
                'localhost', // Sem subdomínio
                'apenas-dominio.com' // Sem subdomínio
            ];
        } else {
            $hosts = [$testHost];
        }

        $this->info('🔍 Testando extração de OU...');
        $this->info('================================');

        foreach ($hosts as $host) {
            $ou = $this->extractOuFromHost($host);
            $status = $ou ? '✅' : '❌';
            
            $this->line("{$status} Host: {$host}");
            $this->line("    OU: " . ($ou ?: 'NULL'));
            $this->line('');
        }

        $this->info('✅ Teste concluído!');
        $this->info('💡 Para testar um host específico: php artisan test:host-detection "contas.exemplo.sei.pe.gov.br"');
        
        return 0;
    }

    /**
     * Método copiado do AuthController para teste
     */
    private function extractOuFromHost($host)
    {
        // Pegar apenas o primeiro subdomínio (antes do primeiro ponto)
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            return strtolower($parts[0]);
        }
        
        return null;
    }
} 