<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestHostDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:host-detection {url}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa a detecção de host e extração de OU';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');

        $this->info('🔍 Testando detecção de host...');
        $this->info('==========================================');

        // Parse da URL
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? null;

        if (!$host) {
            $this->error('❌ URL inválida!');
            return 1;
        }

        $this->info("🌐 URL informada: {$url}");
        $this->info("🏠 Host extraído: {$host}");

        // Testar extração de OU
        $ou = $this->extractOuFromHost($host);
        
        if ($ou) {
            $this->info("✅ OU detectada: {$ou}");
            
            if ($ou === 'admin') {
                $this->info("👑 Tipo: Usuário ROOT");
            } else {
                $this->info("👤 Tipo: Admin de OU '{$ou}'");
            }
        } else {
            $this->error("❌ Não foi possível extrair OU do host");
        }

        // Testar validação de host
        $this->info("\n🔍 Validação de Host:");
        $this->info("──────────────────────");
        
        if ($this->isValidHost($host)) {
            $this->info("✅ Host válido para o sistema");
        } else {
            $this->error("❌ Host inválido - não pertence ao domínio esperado");
        }

        // Mostrar exemplos válidos
        $this->info("\n📝 Exemplos de URLs válidas:");
        $this->info("────────────────────────────");
        $this->line("- https://contas.sei.pe.gov.br (ROOT)");
        $this->line("- https://contas.moreno.sei.pe.gov.br (OU: moreno)");
        $this->line("- https://contas.ti.sei.pe.gov.br (OU: ti)");
        $this->line("- https://contas.rh.sei.pe.gov.br (OU: rh)");

        // Simulação de headers de proxy
        $this->info("\n🔧 Headers de Proxy que seriam testados:");
        $this->info("────────────────────────────────────────");
        $this->line("- X-Forwarded-Host: {$host}");
        $this->line("- X-Original-Host: {$host}");
        $this->line("- X-Host: {$host}");
        $this->line("- Host: {$host}");

        $this->info("\n✅ Teste concluído!");
        return 0;
    }

    /**
     * Extrai a OU do subdomínio da URL
     */
    private function extractOuFromHost($host)
    {
        // Caso especial para usuários root
        if ($host === 'contas.sei.pe.gov.br') {
            return 'admin';
        }
        
        // Para outras OUs: contas.moreno.sei.pe.gov.br => moreno
        if (preg_match('/contas\\.([a-z0-9-]+)\\.sei\\.pe\\.gov\\.br/i', $host, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Verifica se o host é válido para o domínio esperado
     */
    private function isValidHost($host)
    {
        if (!$host || !is_string($host)) {
            return false;
        }

        // Verificar se é um dos domínios esperados
        return preg_match('/^(contasadmin|contas\.[a-z0-9-]+)\.sei\.pe\.gov\.br$/i', trim($host));
    }
} 