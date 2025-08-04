#!/bin/bash

echo "🔍 DIAGNÓSTICO LARAVEL SAIL - ERRO 503"
echo "======================================"

# Verificar se está no diretório correto
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Arquivo docker-compose.yml não encontrado!"
    echo "Execute este script no diretório raiz do projeto."
    exit 1
fi

echo ""
echo "1️⃣ VERIFICANDO STATUS DOS CONTAINERS"
echo "──────────────────────────────────"
./vendor/bin/sail ps

echo ""
echo "2️⃣ VERIFICANDO LOGS DO LARAVEL"
echo "───────────────────────────────"
echo "📋 Últimos 20 logs do Laravel:"
./vendor/bin/sail logs laravel.test --tail=20

echo ""
echo "3️⃣ VERIFICANDO LOGS DO NGINX/WEB"
echo "──────────────────────────────────"
echo "📋 Últimos 10 logs do servidor web:"
./vendor/bin/sail logs --tail=10

echo ""
echo "4️⃣ VERIFICANDO SAÚDE DA APLICAÇÃO"
echo "──────────────────────────────────"
echo "🌐 Testando resposta HTTP:"
./vendor/bin/sail exec laravel.test curl -I http://localhost || echo "❌ Falha na conexão local"

echo ""
echo "5️⃣ VERIFICANDO CONFIGURAÇÃO LARAVEL"
echo "────────────────────────────────────"
echo "🔧 Status do config cache:"
./vendor/bin/sail artisan config:show app.env || echo "❌ Erro ao ler configuração"

echo ""
echo "6️⃣ VERIFICANDO BANCO DE DADOS"
echo "──────────────────────────────"
echo "📊 Testando conexão com banco:"
./vendor/bin/sail artisan migrate:status 2>/dev/null || echo "❌ Problemas com banco de dados"

echo ""
echo "7️⃣ VERIFICANDO PERMISSÕES"
echo "──────────────────────────"
echo "📁 Permissões dos diretórios críticos:"
./vendor/bin/sail exec laravel.test ls -la storage/
./vendor/bin/sail exec laravel.test ls -la bootstrap/cache/

echo ""
echo "8️⃣ VERIFICANDO SPACE DISPONÍVEL"
echo "─────────────────────────────────"
echo "💾 Espaço em disco:"
./vendor/bin/sail exec laravel.test df -h

echo ""
echo "9️⃣ VERIFICANDO PROCESSOS PHP"
echo "─────────────────────────────"
echo "🔄 Processos PHP ativos:"
./vendor/bin/sail exec laravel.test ps aux | grep php

echo ""
echo "🔟 VERIFICANDO LOGS DE ERRO PHP"
echo "────────────────────────────────"
echo "🚨 Últimos erros PHP:"
./vendor/bin/sail exec laravel.test tail -20 /var/log/apache2/error.log 2>/dev/null || echo "⚠️ Log de erro não encontrado (normal se usando nginx)"

echo ""
echo "✅ DIAGNÓSTICO CONCLUÍDO!"
echo "======================="
echo ""
echo "📋 POSSÍVEIS SOLUÇÕES:"
echo ""
echo "1. Se containers não estão rodando:"
echo "   ./vendor/bin/sail up -d"
echo ""
echo "2. Se há problemas de cache:"
echo "   ./vendor/bin/sail artisan config:clear"
echo "   ./vendor/bin/sail artisan cache:clear"
echo "   ./vendor/bin/sail artisan view:clear"
echo ""
echo "3. Se há problemas de migração:"
echo "   ./vendor/bin/sail artisan migrate"
echo ""
echo "4. Se há problemas de permissão:"
echo "   ./vendor/bin/sail exec laravel.test chmod -R 775 storage bootstrap/cache"
echo ""
echo "5. Restart completo:"
echo "   ./vendor/bin/sail down"
echo "   ./vendor/bin/sail up -d" 