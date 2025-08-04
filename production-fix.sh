#!/bin/bash

echo "🔧 CORREÇÃO PRODUÇÃO - ATI LDAP MANAGER"
echo "======================================="
echo "🌐 Servidor: $(hostname)"
echo "📅 Data/Hora: $(date)"
echo ""

# Verificar se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script no diretório do projeto!"
    echo "💡 Procure por: find /home -name composer.json -path '*ati-ldap-manager*'"
    exit 1
fi

echo "1️⃣ PARANDO CONTAINERS ANTIGOS"
echo "──────────────────────────────"
sudo ./vendor/bin/sail down 2>/dev/null || echo "⚠️  Sail down falhou (containers podem não estar rodando)"
sudo docker stop $(sudo docker ps -q) 2>/dev/null || echo "⚠️  Nenhum container para parar"

echo ""
echo "2️⃣ LIMPANDO SISTEMA DOCKER"
echo "───────────────────────────"
sudo docker system prune -f
sudo docker volume prune -f

echo ""
echo "3️⃣ VERIFICANDO/INSTALANDO DEPENDÊNCIAS"
echo "───────────────────────────────────────"
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependências do Composer..."
    if command -v composer &> /dev/null; then
        sudo composer install --no-dev --optimize-autoloader
    else
        echo "❌ Composer não encontrado! Instale primeiro:"
        echo "   curl -sS https://getcomposer.org/installer | php"
        echo "   sudo mv composer.phar /usr/local/bin/composer"
        exit 1
    fi
else
    echo "✅ Dependências já instaladas"
fi

echo ""
echo "4️⃣ CONFIGURANDO ARQUIVO .env"
echo "─────────────────────────────"
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "📋 Copiando .env.example para .env..."
        sudo cp .env.example .env
        echo "✅ .env criado"
    else
        echo "❌ .env.example não encontrado!"
        exit 1
    fi
else
    echo "✅ .env já existe"
fi

# Verificar se APP_KEY está configurada
if grep -q "^APP_KEY=$" .env || ! grep -q "^APP_KEY=" .env; then
    echo "🔑 APP_KEY não configurada, configurando para produção..."
    
    # Configurar para produção primeiro
    sudo sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
    sudo sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
    
    echo "⚠️  CONFIGURAÇÃO CRÍTICA: .env deve ter estas configurações para PRODUÇÃO:"
    echo ""
    echo "APP_ENV=production"
    echo "APP_DEBUG=false"
    echo "APP_URL=http://10.238.124.200  # ou o domínio correto"
    echo ""
    echo "# Banco de dados:"
    echo "DB_CONNECTION=pgsql"
    echo "DB_HOST=pgsql"
    echo "DB_PORT=5432"
    echo "DB_DATABASE=atildaplogs"
    echo "DB_USERNAME=ati"
    echo "DB_PASSWORD=123456"
    echo ""
    echo "# LDAP - configure conforme seu ambiente:"
    echo "LDAP_CONNECTION=default"
    echo "LDAP_HOST=SEU_SERVIDOR_LDAP"
    echo "LDAP_USERNAME=cn=admin,dc=exemplo,dc=com"
    echo "LDAP_PASSWORD=SUA_SENHA_LDAP"
    echo "LDAP_BASE_DN=dc=exemplo,dc=com"
    echo ""
    echo "⏸️  SCRIPT PAUSADO - Configure o .env acima e pressione ENTER para continuar..."
    read -p ""
fi

echo ""
echo "5️⃣ CORRIGINDO PERMISSÕES"
echo "─────────────────────────"
directories=("storage" "storage/app" "storage/framework" "storage/logs" "bootstrap/cache")

for dir in "${directories[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "📁 Criando $dir..."
        sudo mkdir -p "$dir"
    fi
    
    echo "🔧 Corrigindo permissões: $dir"
    sudo chmod -R 775 "$dir"
    sudo chown -R www-data:www-data "$dir" 2>/dev/null || \
    sudo chown -R $USER:$USER "$dir" 2>/dev/null || \
    echo "   ⚠️  Não foi possível alterar owner"
done

echo ""
echo "6️⃣ INICIANDO CONTAINERS"
echo "────────────────────────"
echo "🐳 Iniciando Docker Compose..."
sudo ./vendor/bin/sail up -d

echo ""
echo "7️⃣ AGUARDANDO CONTAINERS SUBIREM..."
echo "────────────────────────────────────"
sleep 15

echo "📊 Status dos containers:"
sudo ./vendor/bin/sail ps

echo ""
echo "8️⃣ CONFIGURANDO APLICAÇÃO"
echo "──────────────────────────"

# Gerar chave da aplicação
echo "🔑 Gerando chave da aplicação..."
sudo ./vendor/bin/sail artisan key:generate --force

# Limpar caches
echo "🧹 Limpando caches..."
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan view:clear
sudo ./vendor/bin/sail artisan route:clear

# Executar migrações
echo "📊 Executando migrações..."
sudo ./vendor/bin/sail artisan migrate --force

echo ""
echo "9️⃣ OTIMIZAÇÕES PARA PRODUÇÃO"
echo "─────────────────────────────"
echo "⚡ Otimizando configuração..."
sudo ./vendor/bin/sail artisan config:cache
sudo ./vendor/bin/sail artisan route:cache
sudo ./vendor/bin/sail artisan view:cache

echo ""
echo "🔟 TESTANDO APLICAÇÃO"
echo "─────────────────────"

# Aguardar mais um pouco
sleep 5

echo "🌐 Testando conectividade..."

# Testar localhost
echo "🔗 Teste localhost:"
status_local=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "   Status: $status_local"

# Testar pelo IP externo
echo "🔗 Teste IP externo (10.238.124.200):"
status_external=$(curl -s -o /dev/null -w "%{http_code}" http://10.238.124.200 2>/dev/null)
echo "   Status: $status_external"

echo ""
echo "✅ CORREÇÃO CONCLUÍDA!"
echo "====================="
echo ""

# Verificar status final
if [ "$status_local" = "200" ] || [ "$status_external" = "200" ]; then
    echo "🎉 SUCESSO! Aplicação respondendo corretamente!"
    echo ""
    echo "🌐 Acesse a aplicação em:"
    echo "   http://10.238.124.200"
    echo ""
    echo "📋 Para monitorar logs:"
    echo "   sudo ./vendor/bin/sail logs -f"
else
    echo "⚠️  APLICAÇÃO AINDA NÃO ESTÁ RESPONDENDO"
    echo ""
    echo "🔍 Próximos passos para debug:"
    echo ""
    echo "1. Verificar logs em tempo real:"
    echo "   sudo ./vendor/bin/sail logs -f"
    echo ""
    echo "2. Verificar containers:"
    echo "   sudo ./vendor/bin/sail ps"
    echo ""
    echo "3. Verificar configuração .env:"
    echo "   cat .env | grep -E '^(APP_|DB_|LDAP_)'"
    echo ""
    echo "4. Testar dentro do container:"
    echo "   sudo ./vendor/bin/sail exec laravel.test curl -I http://localhost"
    echo ""
    echo "5. Verificar processos dentro do container:"
    echo "   sudo ./vendor/bin/sail exec laravel.test ps aux"
    echo ""
    echo "6. Executar diagnóstico completo:"
    echo "   chmod +x production-diagnostics.sh"
    echo "   ./production-diagnostics.sh"
fi

echo ""
echo "📊 STATUS FINAL DOS CONTAINERS:"
sudo ./vendor/bin/sail ps

echo ""
echo "🔚 Script finalizado em $(date)" 