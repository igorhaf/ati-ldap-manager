<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class ProxyDebug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proxy:debug';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug configurações de proxy reverso HTTPS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 DEBUG PROXY REVERSO HTTPS');
        $this->info('============================');
        $this->newLine();

        // Configurações do .env
        $this->info('📄 CONFIGURAÇÕES .env:');
        $this->info('─────────────────────');
        $this->line('APP_URL: ' . config('app.url'));
        $this->line('APP_ENV: ' . config('app.env'));
        $this->line('APP_DEBUG: ' . (config('app.debug') ? 'true' : 'false'));
        $this->line('TRUSTED_PROXIES: ' . env('TRUSTED_PROXIES', 'não definido'));
        $this->newLine();

        // Headers da requisição
        $this->info('🌐 HEADERS DE PROXY:');
        $this->info('──────────────────');
        $headers = [
            'HTTP_X_FORWARDED_PROTO' => 'X-Forwarded-Proto',
            'HTTP_X_FORWARDED_SSL' => 'X-Forwarded-SSL',
            'HTTP_X_FORWARDED_PORT' => 'X-Forwarded-Port',
            'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For',
            'HTTP_X_REAL_IP' => 'X-Real-IP',
            'HTTP_CF_VISITOR' => 'CF-Visitor',
            'HTTPS' => 'HTTPS',
            'SERVER_PORT' => 'SERVER_PORT',
        ];

        foreach ($headers as $serverKey => $displayName) {
            $value = $_SERVER[$serverKey] ?? 'não definido';
            $this->line("$displayName: $value");
        }
        $this->newLine();

        // Status da aplicação
        $this->info('⚙️ STATUS DA APLICAÇÃO:');
        $this->info('─────────────────────');
        
        $request = request();
        $this->line('Scheme detectado: ' . $request->getScheme());
        $this->line('Host detectado: ' . $request->getHost());
        $this->line('URL base: ' . $request->getSchemeAndHttpHost());
        $this->line('É HTTPS: ' . ($request->isSecure() ? 'SIM' : 'NÃO'));
        $this->line('Porta: ' . $request->getPort());
        $this->newLine();

        // URLs geradas pelo Laravel
        $this->info('🔗 URLs GERADAS PELO LARAVEL:');
        $this->info('────────────────────────────');
        $this->line('URL raiz: ' . url('/'));
        $this->line('URL login: ' . route('login'));
        $this->line('Asset URL: ' . asset('favicon.ico'));
        $this->line('Scheme forçado: ' . (URL::isValidUrl('https://example.com') ? 'detectado' : 'não detectado'));
        $this->newLine();

        // Detecção de proxy
        $this->info('🔍 DETECÇÃO DE PROXY:');
        $this->info('───────────────────');
        
        $isHttpsProxy = $this->isRequestFromHttpsProxy();
        $this->line('Proxy HTTPS detectado: ' . ($isHttpsProxy ? 'SIM' : 'NÃO'));
        
        if ($isHttpsProxy) {
            $this->line('✅ Proxy HTTPS está sendo detectado corretamente');
        } else {
            $this->error('❌ Proxy HTTPS NÃO está sendo detectado');
            $this->warn('Verifique se o proxy está enviando os headers corretos');
        }
        $this->newLine();

        // Verificações de segurança
        $this->info('🔒 VERIFICAÇÕES DE SEGURANÇA:');
        $this->info('────────────────────────────');
        
        // CSRF
        try {
            $csrfToken = csrf_token();
            $this->line('CSRF Token: ' . substr($csrfToken, 0, 20) . '...');
        } catch (\Exception $e) {
            $this->error('CSRF Token: ERRO - ' . $e->getMessage());
        }

        // Session
        if (session()->isStarted()) {
            $this->line('Session: ATIVA (ID: ' . substr(session()->getId(), 0, 10) . '...)');
        } else {
            $this->error('Session: NÃO ATIVA');
        }
        $this->newLine();

        // Recomendações
        $this->info('💡 RECOMENDAÇÕES:');
        $this->info('───────────────');
        
        if (!$isHttpsProxy) {
            $this->warn('1. Verifique se o proxy está enviando X-Forwarded-Proto: https');
            $this->warn('2. Configure o proxy para enviar headers corretos');
            $this->warn('3. Teste com: curl -H "X-Forwarded-Proto: https" http://localhost');
        }
        
        if (config('app.url') !== 'https://contas.gravata.sei.pe.gov.br') {
            $this->warn('4. Configure APP_URL=https://contas.gravata.sei.pe.gov.br no .env');
        }
        
        if (config('app.env') !== 'production') {
            $this->warn('5. Configure APP_ENV=production no .env');
        }
        
        if (config('app.debug')) {
            $this->warn('6. Configure APP_DEBUG=false no .env para produção');
        }
        
        $this->newLine();
        $this->info('✅ Debug concluído!');
    }

    /**
     * Verificar se a requisição veio de um proxy HTTPS
     * (mesma lógica do AppServiceProvider)
     */
    private function isRequestFromHttpsProxy(): bool
    {
        return (
            // Header X-Forwarded-Proto indica HTTPS
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            
            // Header X-Forwarded-SSL indica SSL ativo
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            
            // Header X-Forwarded-Port indica porta 443
            (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] === '443') ||
            
            // Cloudflare headers
            (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) ||
            
            // AWS Load Balancer
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            
            // Nginx proxy comum
            (isset($_SERVER['HTTP_X_REAL_IP']) && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );
    }
} 