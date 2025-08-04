#!/bin/bash

echo "🔧 CORREÇÃO RÁPIDA - ERRO 503 LARAVEL SAIL"
echo "==========================================="

# Verificar se está no diretório correto
if [ ! -f "docker-compose.yml" ]; then
    echo "❌ Execute este script no diretório raiz do projeto!"
    exit 1
fi

echo ""
echo "1️⃣ PARANDO TODOS OS CONTAINERS"
echo "───────────────────────────────"
./vendor/bin/sail down

echo ""
echo "2️⃣ LIMPANDO CACHE DO DOCKER"
echo "─────────────────────────────"
docker system prune -f

echo ""
echo "3️⃣ INICIANDO CONTAINERS"
echo "─────────────────────────"
./vendor/bin/sail up -d

echo ""
echo "4️⃣ AGUARDANDO CONTAINERS SUBIREM..."
echo "─────────────────────────────────────"
sleep 10

echo ""
echo "5️⃣ LIMPANDO CACHE DO LARAVEL"
echo "──────────────────────────────"
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan route:clear

echo ""
echo "6️⃣ CORRIGINDO PERMISSÕES"
echo "─────────────────────────"
./vendor/bin/sail exec laravel.test chmod -R 775 storage
./vendor/bin/sail exec laravel.test chmod -R 775 bootstrap/cache
./vendor/bin/sail exec laravel.test chown -R www-data:www-data storage
./vendor/bin/sail exec laravel.test chown -R www-data:www-data bootstrap/cache

echo ""
echo "7️⃣ VERIFICANDO MIGRAÇÕES"
echo "─────────────────────────"
./vendor/bin/sail artisan migrate:status

echo ""
echo "8️⃣ EXECUTANDO MIGRAÇÕES SE NECESSÁRIO"
echo "──────────────────────────────────────"
./vendor/bin/sail artisan migrate --force

echo ""
echo "9️⃣ GERANDO CHAVE DA APLICAÇÃO"
echo "──────────────────────────────"
./vendor/bin/sail artisan key:generate --force

echo ""
echo "🔟 TESTANDO APLICAÇÃO"
echo "──────────────────────"
echo "🌐 Status da aplicação:"
./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost

echo ""
echo ""
echo "✅ CORREÇÃO CONCLUÍDA!"
echo "===================="
echo ""
echo "🌐 Teste a aplicação em: http://localhost"
echo ""
echo "📋 Se ainda não funcionar, execute:"
echo "   chmod +x sail-diagnostics.sh"
echo "   ./sail-diagnostics.sh" 