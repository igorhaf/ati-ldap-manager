#!/bin/bash

echo "🔧 CORREÇÃO ERRO PROXY - HEADER_X_FORWARDED_ALL"
echo "================================================"
echo "🎯 Corrigindo erro na constante do Laravel"
echo "📅 Data: $(date)"
echo ""

# Verificar se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script no diretório do projeto!"
    exit 1
fi

echo "1️⃣ CORRIGINDO APPSERVICEPROVIDER"
echo "─────────────────────────────────"

# Backup do arquivo
sudo cp app/Providers/AppServiceProvider.php app/Providers/AppServiceProvider.php.backup.$(date +%Y%m%d_%H%M%S)
echo "✅ Backup criado"

# Corrigir a constante incorreta
if grep -q "HEADER_X_FORWARDED_ALL" app/Providers/AppServiceProvider.php; then
    echo "🔧 Corrigindo constante incorreta..."
    
    # Criar versão corrigida
    cat > app/Providers/AppServiceProvider.php << 'EOF'
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
EOF

    echo "✅ AppServiceProvider corrigido"
else
    echo "✅ AppServiceProvider já está correto"
fi

echo ""
echo "2️⃣ LIMPANDO CACHE PARA APLICAR CORREÇÃO"
echo "────────────────────────────────────────"

echo "🧹 Limpando cache de configuração..."
sudo ./vendor/bin/sail artisan config:clear

echo "🧹 Limpando cache geral..."
sudo ./vendor/bin/sail artisan cache:clear

echo "🧹 Limpando cache de views..."
sudo ./vendor/bin/sail artisan view:clear

echo ""
echo "3️⃣ TESTANDO CONFIGURAÇÃO"
echo "─────────────────────────"

# Testar se não há mais erros
echo "🧪 Testando comando artisan..."
if sudo ./vendor/bin/sail artisan --version >/dev/null 2>&1; then
    echo "✅ Comando artisan funcionando"
else
    echo "❌ Ainda há problemas com artisan"
fi

# Testar comando proxy:debug se existir
if sudo ./vendor/bin/sail artisan list | grep -q "proxy:debug"; then
    echo "🔍 Testando comando proxy:debug..."
    if sudo ./vendor/bin/sail artisan proxy:debug >/dev/null 2>&1; then
        echo "✅ Comando proxy:debug funcionando"
    else
        echo "⚠️  Comando proxy:debug com problemas"
    fi
else
    echo "ℹ️  Comando proxy:debug não encontrado"
fi

echo ""
echo "4️⃣ VERIFICANDO STATUS DA APLICAÇÃO"
echo "───────────────────────────────────"

# Testar aplicação
status=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "📡 Status HTTP: $status"

if [ "$status" = "200" ]; then
    echo "✅ Aplicação respondendo corretamente"
else
    echo "⚠️  Aplicação ainda com problemas (Status: $status)"
fi

echo ""
echo "✅ CORREÇÃO CONCLUÍDA!"
echo "====================="
echo ""

if [ "$status" = "200" ]; then
    echo "🎉 SUCESSO! Erro corrigido!"
    echo ""
    echo "🌐 Agora teste:"
    echo "   https://contas.gravata.sei.pe.gov.br"
    echo ""
    echo "🔍 Para debug detalhado:"
    echo "   sudo ./vendor/bin/sail artisan proxy:debug"
else
    echo "⚠️  APLICAÇÃO AINDA COM PROBLEMAS"
    echo ""
    echo "🔍 Para debug:"
    echo "   sudo ./vendor/bin/sail logs --tail=50"
    echo "   sudo ./vendor/bin/sail artisan --version"
fi

echo ""
echo "📋 ARQUIVOS MODIFICADOS:"
echo "   ✅ app/Providers/AppServiceProvider.php (corrigido)"
echo "   📋 Backup: app/Providers/AppServiceProvider.php.backup.*"
echo ""
echo "🔚 Script finalizado em $(date)" 