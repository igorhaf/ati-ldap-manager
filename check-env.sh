#!/bin/bash

echo "🔍 VERIFICAÇÃO DE CONFIGURAÇÃO DO AMBIENTE"
echo "==========================================="

# Verificar se .env existe
if [ ! -f ".env" ]; then
    echo "❌ PROBLEMA ENCONTRADO: Arquivo .env não existe!"
    echo ""
    echo "🔧 CORREÇÃO:"
    if [ -f ".env.example" ]; then
        echo "Copiando .env.example para .env..."
        cp .env.example .env
        echo "✅ Arquivo .env criado!"
        echo ""
        echo "⚠️  CONFIGURE AS VARIÁVEIS NECESSÁRIAS:"
        echo "   - APP_KEY (será gerado automaticamente)"
        echo "   - DB_* (configurações do banco)"
        echo "   - LDAP_* (configurações LDAP)"
    else
        echo "❌ Arquivo .env.example também não encontrado!"
        exit 1
    fi
fi

echo ""
echo "📋 VERIFICANDO VARIÁVEIS CRÍTICAS NO .env"
echo "───────────────────────────────────────"

# Verificar APP_KEY
if grep -q "^APP_KEY=$" .env || ! grep -q "^APP_KEY=" .env; then
    echo "⚠️  APP_KEY está vazia ou não configurada"
    echo "   Será gerada automaticamente..."
else
    echo "✅ APP_KEY configurada"
fi

# Verificar APP_ENV
if grep -q "^APP_ENV=local" .env; then
    echo "✅ APP_ENV=local (desenvolvimento)"
elif grep -q "^APP_ENV=production" .env; then
    echo "⚠️  APP_ENV=production (certifique-se que está correto)"
else
    echo "❌ APP_ENV não configurado adequadamente"
fi

# Verificar APP_DEBUG
if grep -q "^APP_DEBUG=true" .env; then
    echo "✅ APP_DEBUG=true (bom para debug)"
elif grep -q "^APP_DEBUG=false" .env; then
    echo "⚠️  APP_DEBUG=false (pode ocultar erros)"
else
    echo "❌ APP_DEBUG não configurado"
fi

# Verificar configurações do banco
echo ""
echo "📊 CONFIGURAÇÕES DO BANCO DE DADOS:"
echo "────────────────────────────────────"
grep "^DB_" .env | while read line; do
    key=$(echo $line | cut -d'=' -f1)
    value=$(echo $line | cut -d'=' -f2)
    if [ -z "$value" ]; then
        echo "❌ $key está vazio"
    else
        echo "✅ $key=$value"
    fi
done

# Verificar configurações LDAP
echo ""
echo "🔗 CONFIGURAÇÕES LDAP:"
echo "───────────────────────"
if grep -q "^LDAP_" .env; then
    grep "^LDAP_" .env | while read line; do
        key=$(echo $line | cut -d'=' -f1)
        value=$(echo $line | cut -d'=' -f2)
        if [ -z "$value" ]; then
            echo "❌ $key está vazio"
        else
            echo "✅ $key=$value"
        fi
    done
else
    echo "⚠️  Nenhuma configuração LDAP encontrada"
fi

echo ""
echo "📁 VERIFICANDO ESTRUTURA DE DIRETÓRIOS"
echo "────────────────────────────────────"

directories=("storage" "storage/app" "storage/framework" "storage/logs" "bootstrap/cache")

for dir in "${directories[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir existe"
        # Verificar permissões
        perms=$(stat -c "%a" "$dir" 2>/dev/null || echo "???")
        echo "   Permissões: $perms"
    else
        echo "❌ $dir não existe!"
        echo "   Criando diretório..."
        mkdir -p "$dir"
        chmod 775 "$dir"
        echo "   ✅ Criado com permissões 775"
    fi
done

echo ""
echo "🐳 VERIFICANDO DOCKER"
echo "──────────────────────"

if command -v docker &> /dev/null; then
    echo "✅ Docker instalado"
    if docker ps &> /dev/null; then
        echo "✅ Docker rodando"
    else
        echo "❌ Docker não está rodando ou sem permissões"
        echo "   Tente: sudo systemctl start docker"
    fi
else
    echo "❌ Docker não instalado"
fi

if command -v docker-compose &> /dev/null; then
    echo "✅ Docker Compose instalado"
else
    echo "❌ Docker Compose não encontrado"
fi

echo ""
echo "📋 RESUMO E PRÓXIMOS PASSOS"
echo "═══════════════════════════"
echo ""
echo "Para corrigir o erro 503, execute na ordem:"
echo ""
echo "1. Dar permissão aos scripts:"
echo "   chmod +x *.sh"
echo ""
echo "2. Executar correção rápida:"
echo "   ./fix-503.sh"
echo ""
echo "3. Se ainda não funcionar, diagnóstico completo:"
echo "   ./sail-diagnostics.sh"
echo ""
echo "4. Verificar logs em tempo real:"
echo "   ./vendor/bin/sail logs -f" 