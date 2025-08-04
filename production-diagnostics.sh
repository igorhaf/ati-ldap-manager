#!/bin/bash

echo "🔍 DIAGNÓSTICO PRODUÇÃO - ATI LDAP MANAGER"
echo "=========================================="
echo "🌐 Servidor: $(hostname)"
echo "📅 Data/Hora: $(date)"
echo "👤 Usuário: $(whoami)"
echo ""

# Verificar se está no diretório correto
echo "📁 VERIFICANDO DIRETÓRIO ATUAL"
echo "─────────────────────────────"
echo "PWD: $(pwd)"
if [ ! -f "composer.json" ]; then
    echo "❌ Não está no diretório do projeto!"
    echo "   Procurando projeto..."
    find /home -name "composer.json" -path "*/ati-ldap-manager/*" 2>/dev/null | head -5
    echo ""
    echo "💡 Navegue para o diretório do projeto antes de executar este script"
    exit 1
fi
echo "✅ Diretório correto detectado"

echo ""
echo "🐳 VERIFICANDO DOCKER EM PRODUÇÃO"
echo "──────────────────────────────────"
if command -v docker &> /dev/null; then
    echo "✅ Docker instalado"
    echo "📊 Versão: $(docker --version)"
    
    if sudo docker ps &> /dev/null; then
        echo "✅ Docker rodando com sudo"
        echo ""
        echo "🔄 CONTAINERS ATIVOS:"
        sudo docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
        echo ""
        echo "📦 CONTAINERS RELACIONADOS AO PROJETO:"
        sudo docker ps -a | grep -E "(ati|ldap|postgres|pgsql)" || echo "❌ Nenhum container relacionado encontrado"
    else
        echo "❌ Problemas com Docker ou permissões"
        echo "   Tentando com sudo..."
        sudo systemctl status docker | head -10
    fi
else
    echo "❌ Docker não encontrado"
fi

echo ""
echo "⚙️  VERIFICANDO SAIL/COMPOSER"
echo "─────────────────────────────"
if [ -f "vendor/bin/sail" ]; then
    echo "✅ Sail encontrado"
    echo "🔄 Status containers via Sail:"
    sudo ./vendor/bin/sail ps 2>/dev/null || echo "❌ Erro ao executar sail ps"
else
    echo "❌ Sail não encontrado"
    echo "📦 Verificando se vendor existe:"
    ls -la vendor/ 2>/dev/null || echo "❌ Diretório vendor não encontrado"
    echo ""
    echo "🔧 Tentando instalar dependências:"
    if command -v composer &> /dev/null; then
        echo "Composer encontrado, executando install..."
        sudo composer install --no-dev --optimize-autoloader
    else
        echo "❌ Composer não encontrado"
    fi
fi

echo ""
echo "📄 VERIFICANDO ARQUIVO .env"
echo "───────────────────────────"
if [ -f ".env" ]; then
    echo "✅ Arquivo .env existe"
    echo ""
    echo "🔍 CONFIGURAÇÕES CRÍTICAS:"
    echo "─────────────────────────"
    
    # Verificar APP_ENV
    app_env=$(grep "^APP_ENV=" .env | cut -d'=' -f2)
    echo "🌍 APP_ENV: $app_env"
    
    # Verificar APP_DEBUG  
    app_debug=$(grep "^APP_DEBUG=" .env | cut -d'=' -f2)
    echo "🐛 APP_DEBUG: $app_debug"
    
    # Verificar APP_KEY
    app_key=$(grep "^APP_KEY=" .env | cut -d'=' -f2)
    if [ -z "$app_key" ]; then
        echo "❌ APP_KEY está vazia!"
    else
        echo "🔑 APP_KEY: configurada (${#app_key} caracteres)"
    fi
    
    # Verificar APP_URL
    app_url=$(grep "^APP_URL=" .env | cut -d'=' -f2)
    echo "🌐 APP_URL: $app_url"
    
    echo ""
    echo "🗄️  CONFIGURAÇÕES DO BANCO:"
    grep "^DB_" .env | while read line; do
        key=$(echo $line | cut -d'=' -f1)
        value=$(echo $line | cut -d'=' -f2)
        if [[ "$key" == "DB_PASSWORD" ]]; then
            echo "🔒 $key: ***hidden***"
        else
            echo "📊 $key: $value"
        fi
    done
    
    echo ""
    echo "🔗 CONFIGURAÇÕES LDAP:"
    if grep -q "^LDAP_" .env; then
        grep "^LDAP_" .env | while read line; do
            key=$(echo $line | cut -d'=' -f1)
            value=$(echo $line | cut -d'=' -f2)
            if [[ "$key" == "LDAP_PASSWORD" ]]; then
                echo "🔒 $key: ***hidden***"
            else
                echo "🔗 $key: $value"
            fi
        done
    else
        echo "⚠️  Nenhuma configuração LDAP encontrada"
    fi
    
else
    echo "❌ Arquivo .env NÃO EXISTE!"
    echo ""
    if [ -f ".env.example" ]; then
        echo "📋 .env.example encontrado, copiando..."
        sudo cp .env.example .env
        echo "✅ .env criado, CONFIGURE AS VARIÁVEIS!"
    else
        echo "❌ .env.example também não encontrado"
    fi
fi

echo ""
echo "📁 VERIFICANDO PERMISSÕES"
echo "─────────────────────────"
directories=("storage" "storage/logs" "storage/framework" "bootstrap/cache")

for dir in "${directories[@]}"; do
    if [ -d "$dir" ]; then
        perms=$(stat -c "%a" "$dir" 2>/dev/null)
        owner=$(stat -c "%U:%G" "$dir" 2>/dev/null)
        echo "📂 $dir: $perms ($owner)"
        
        # Verificar se é writeable
        if [ -w "$dir" ]; then
            echo "   ✅ Writeable"
        else
            echo "   ❌ NÃO writeable - PROBLEMA!"
            echo "   🔧 Corrigindo: sudo chmod 775 $dir"
            sudo chmod -R 775 "$dir"
            sudo chown -R www-data:www-data "$dir" 2>/dev/null || echo "   ⚠️  Não foi possível alterar owner"
        fi
    else
        echo "❌ $dir não existe!"
        echo "   🔧 Criando: sudo mkdir -p $dir"
        sudo mkdir -p "$dir"
        sudo chmod 775 "$dir"
    fi
done

echo ""
echo "🔍 VERIFICANDO PROCESSO WEB"
echo "───────────────────────────"
echo "🌐 Processos na porta 80/8080/443:"
sudo netstat -tlnp | grep -E ":80|:8080|:443" || echo "❌ Nenhum processo nas portas web padrão"

echo ""
echo "🔄 Processos PHP/Apache/Nginx:"
ps aux | grep -E "(php|apache|nginx)" | grep -v grep || echo "❌ Nenhum processo web encontrado"

echo ""
echo "📊 VERIFICANDO RECURSOS DO SISTEMA"
echo "───────────────────────────────────"
echo "💾 Espaço em disco:"
df -h | grep -E "(/$|/var|/home)" | head -5

echo ""
echo "🧠 Memória:"
free -h

echo ""
echo "⚡ Load average:"
uptime

echo ""
echo "📋 LOGS DE ERRO CRÍTICOS"
echo "────────────────────────"

# Logs do Laravel
if [ -d "storage/logs" ]; then
    echo "🚨 ÚLTIMOS ERROS LARAVEL:"
    sudo find storage/logs -name "*.log" -exec tail -10 {} \; 2>/dev/null | grep -i error | tail -20 || echo "   ℹ️  Nenhum erro recente"
fi

# Logs do sistema
echo ""
echo "🚨 ÚLTIMOS ERROS DO SISTEMA:"
sudo tail -20 /var/log/syslog | grep -i error || echo "   ℹ️  Nenhum erro recente no syslog"

# Logs do Apache/Nginx
echo ""
echo "🚨 LOGS DO SERVIDOR WEB:"
sudo tail -20 /var/log/apache2/error.log 2>/dev/null || sudo tail -20 /var/log/nginx/error.log 2>/dev/null || echo "   ℹ️  Logs do servidor web não encontrados"

echo ""
echo "🌐 TESTANDO CONECTIVIDADE"
echo "─────────────────────────"

# Testar localhost
echo "🔗 Testando localhost:"
curl -s -o /dev/null -w "Status: %{http_code} | Tempo: %{time_total}s\n" http://localhost 2>/dev/null || echo "❌ Falha na conexão local"

# Testar IP interno
internal_ip=$(hostname -I | awk '{print $1}')
echo "🔗 Testando IP interno ($internal_ip):"
curl -s -o /dev/null -w "Status: %{http_code} | Tempo: %{time_total}s\n" http://$internal_ip 2>/dev/null || echo "❌ Falha na conexão por IP"

echo ""
echo "✅ DIAGNÓSTICO CONCLUÍDO!"
echo "========================"
echo ""
echo "📋 PRÓXIMOS PASSOS SUGERIDOS:"
echo ""

if ! sudo docker ps &> /dev/null; then
    echo "1. 🐳 Iniciar Docker:"
    echo "   sudo systemctl start docker"
    echo "   sudo ./vendor/bin/sail up -d"
    echo ""
fi

if [ ! -f ".env" ] || grep -q "^APP_KEY=$" .env; then
    echo "2. 🔑 Configurar .env e gerar chave:"
    echo "   sudo ./vendor/bin/sail artisan key:generate --force"
    echo ""
fi

echo "3. 🧹 Limpar cache:"
echo "   sudo ./vendor/bin/sail artisan config:clear"
echo "   sudo ./vendor/bin/sail artisan cache:clear"
echo ""

echo "4. 📊 Executar migrações:"
echo "   sudo ./vendor/bin/sail artisan migrate"
echo ""

echo "5. 📁 Corrigir permissões:"
echo "   sudo chmod -R 775 storage bootstrap/cache"
echo "   sudo chown -R www-data:www-data storage bootstrap/cache"
echo ""

echo "6. 🔄 Reiniciar containers:"
echo "   sudo ./vendor/bin/sail down"
echo "   sudo ./vendor/bin/sail up -d"
echo ""

echo "7. 📋 Verificar logs em tempo real:"
echo "   sudo ./vendor/bin/sail logs -f" 