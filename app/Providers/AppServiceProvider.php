<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ============================================
        // CONFIGURAÇÃO PARA PROXY REVERSO HTTPS
        // ============================================
        
        // Detectar se a requisição veio através de proxy HTTPS
        if ($this->isRequestFromHttpsProxy()) {
            // Forçar Laravel a tratar como HTTPS
            URL::forceScheme('https');
            
            // Configurar headers para proxies confiáveis
            request()->server->set('HTTPS', 'on');
            request()->server->set('SERVER_PORT', 443);
        }
        
        // Configurar proxies confiáveis se definidos
        if (env('TRUSTED_PROXIES')) {
            $proxies = explode(',', env('TRUSTED_PROXIES'));
            request()->setTrustedProxies($proxies, 
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX
            );
        }
    }
    
    /**
     * Verificar se a requisição veio de um proxy HTTPS
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
