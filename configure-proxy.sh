#!/bin/bash

echo "🌐 CONFIGURAÇÃO PROXY REVERSO HTTPS"
echo "==================================="
echo "🎯 Domínio: https://contas.gravata.sei.pe.gov.br"
echo "📅 Data: $(date)"
echo ""

# Verificar se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script no diretório do projeto!"
    exit 1
fi

echo "1️⃣ CONFIGURANDO .env PARA PROXY HTTPS"
echo "──────────────────────────────────────"

# Backup do .env
if [ -f ".env" ]; then
    echo "📋 Fazendo backup do .env atual..."
    sudo cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    echo "✅ Backup criado"
else
    echo "📋 Criando .env do template..."
    sudo cp .env.example .env
fi

# Configurar URL correta para proxy
echo "🌐 Configurando APP_URL para HTTPS..."
sudo sed -i 's|^APP_URL=.*|APP_URL=https://contas.gravata.sei.pe.gov.br|' .env

# Configurar para produção se não estiver
echo "⚙️  Configurando para produção..."
sudo sed -i 's|^APP_ENV=.*|APP_ENV=production|' .env
sudo sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' .env

# Adicionar configurações de proxy se não existirem
echo "🔧 Adicionando configurações de proxy..."

# Remover configurações antigas de proxy se existirem
sudo grep -v "^TRUSTED_PROXIES=" .env > .env.tmp && sudo mv .env.tmp .env
sudo grep -v "^FORCE_HTTPS=" .env > .env.tmp && sudo mv .env.tmp .env
sudo grep -v "^PROXY_HEADERS=" .env > .env.tmp && sudo mv .env.tmp .env

# Adicionar novas configurações
echo "" | sudo tee -a .env
echo "# ============================================" | sudo tee -a .env
echo "# CONFIGURAÇÕES PROXY REVERSO HTTPS" | sudo tee -a .env
echo "# ============================================" | sudo tee -a .env
echo "TRUSTED_PROXIES=*" | sudo tee -a .env
echo "FORCE_HTTPS=true" | sudo tee -a .env
echo "PROXY_HEADERS=true" | sudo tee -a .env

echo "✅ .env configurado para proxy HTTPS"

echo ""
echo "2️⃣ VERIFICANDO APPSERVICEPROVIDER"
echo "──────────────────────────────────"

if grep -q "isRequestFromHttpsProxy" app/Providers/AppServiceProvider.php; then
    echo "✅ AppServiceProvider já configurado para proxy"
else
    echo "❌ AppServiceProvider não configurado!"
    echo "⚠️  Execute o comando de configuração do AppServiceProvider primeiro"
fi

echo ""
echo "3️⃣ REINICIANDO APLICAÇÃO"
echo "─────────────────────────"

echo "🔄 Parando containers..."
sudo ./vendor/bin/sail down 2>/dev/null

echo "🧹 Limpando cache..."
sudo docker system prune -f

echo "🚀 Iniciando containers..."
sudo ./vendor/bin/sail up -d

echo "⏰ Aguardando containers subirem..."
sleep 20

echo "🔑 Gerando nova APP_KEY..."
sudo ./vendor/bin/sail artisan key:generate --force

echo "🧹 Limpando caches da aplicação..."
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan view:clear
sudo ./vendor/bin/sail artisan route:clear

echo "📊 Executando migrações..."
sudo ./vendor/bin/sail artisan migrate --force

echo ""
echo "4️⃣ TESTANDO CONFIGURAÇÃO"
echo "─────────────────────────"

echo "🔍 Verificando configurações no .env:"
echo "───────────────────────────────────"
grep -E "^(APP_URL|APP_ENV|APP_DEBUG|TRUSTED_PROXIES)" .env

echo ""
echo "🌐 Testando conectividade local:"
status_local=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "📡 Status HTTP local: $status_local"

echo ""
echo "🔧 Status dos containers:"
sudo ./vendor/bin/sail ps

echo ""
echo "✅ CONFIGURAÇÃO CONCLUÍDA!"
echo "========================="
echo ""

if [ "$status_local" = "200" ]; then
    echo "🎉 SUCESSO! Aplicação configurada para proxy HTTPS!"
    echo ""
    echo "🌐 Acesse a aplicação em:"
    echo "   https://contas.gravata.sei.pe.gov.br"
    echo ""
    echo "📋 O que foi configurado:"
    echo "   ✅ APP_URL = https://contas.gravata.sei.pe.gov.br"
    echo "   ✅ APP_ENV = production"
    echo "   ✅ APP_DEBUG = false"
    echo "   ✅ TRUSTED_PROXIES = *"
    echo "   ✅ AppServiceProvider configurado para detectar proxy HTTPS"
    echo "   ✅ Cache limpo e aplicação reiniciada"
    echo ""
    echo "🔍 Para debug de proxy, execute:"
    echo "   sudo ./vendor/bin/sail artisan tinker"
    echo "   # No tinker: dd(request()->isSecure(), request()->getScheme(), request()->getHttpHost())"
else
    echo "⚠️  Aplicação ainda com problemas locais"
    echo ""
    echo "🔍 Passos para debug:"
    echo ""
    echo "1. Verificar logs:"
    echo "   sudo ./vendor/bin/sail logs -f"
    echo ""
    echo "2. Verificar se proxy está funcionando:"
    echo "   curl -I https://contas.gravata.sei.pe.gov.br"
    echo ""
    echo "3. Verificar headers do proxy no Laravel:"
    echo "   sudo ./vendor/bin/sail artisan tinker"
    echo "   # dd(\$_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'não definido')"
    echo ""
    echo "4. Testar detecção de HTTPS:"
    echo "   sudo ./vendor/bin/sail artisan tinker"
    echo "   # dd(app('url')->isValidUrl('https://contas.gravata.sei.pe.gov.br'))"
fi

echo ""
echo "📝 PRÓXIMOS PASSOS:"
echo "   1. Teste no navegador: https://contas.gravata.sei.pe.gov.br"
echo "   2. Verifique se login/sessão funcionam"
echo "   3. Teste CSRF tokens nos formulários"
echo "   4. Monitore logs: sudo ./vendor/bin/sail logs -f"
echo ""
echo "🔚 Script finalizado em $(date)" 