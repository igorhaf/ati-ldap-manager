#!/bin/bash

echo "🧪 Testando API do Gerenciador LDAP..."
echo "======================================"

# Aguardar um pouco para o servidor inicializar
sleep 2

# Teste 1: Verificar se o servidor está respondendo
echo "1. Testando se o servidor está respondendo..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 | grep -q "200"; then
    echo "   ✅ Servidor respondendo"
else
    echo "   ❌ Servidor não está respondendo"
    exit 1
fi

# Teste 2: Testar rota de usuários
echo "2. Testando GET /api/ldap/users..."
response=$(curl -s http://localhost:8000/api/ldap/users)
if echo "$response" | grep -q '"success":true'; then
    echo "   ✅ API de usuários funcionando"
elif echo "$response" | grep -q '"success":false'; then
    echo "   ⚠️  API respondendo mas com erro:"
    echo "   $response"
else
    echo "   ❌ Erro na API de usuários:"
    echo "   $response"
fi

# Teste 3: Testar rota de unidades organizacionais
echo "3. Testando GET /api/ldap/organizational-units..."
response=$(curl -s http://localhost:8000/api/ldap/organizational-units)
if echo "$response" | grep -q '"success":true'; then
    echo "   ✅ API de unidades organizacionais funcionando"
elif echo "$response" | grep -q '"success":false'; then
    echo "   ⚠️  API respondendo mas com erro:"
    echo "   $response"
else
    echo "   ❌ Erro na API de unidades organizacionais:"
    echo "   $response"
fi

echo ""
echo "🌐 Interface web disponível em: http://localhost:8000/ldap-manager"
echo "📝 Para mais testes, execute: ./test-routes.sh" 