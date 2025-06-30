#!/bin/bash

echo "🧪 Testando o Gerenciador LDAP após correção..."
echo "=============================================="

BASE_URL="http://localhost"

echo "1. Testando API de usuários..."
response=$(curl -s "$BASE_URL/api/ldap/users")
echo "   $response"
echo ""

echo "2. Testando API de unidades organizacionais..."
response=$(curl -s "$BASE_URL/api/ldap/organizational-units")
echo "   $response"
echo ""

echo "3. Criando unidade organizacional de teste..."
response=$(curl -s -X POST "$BASE_URL/api/ldap/organizational-units" \
  -H "Content-Type: application/json" \
  -d '{"ou": "TI", "description": "Departamento de Tecnologia da Informação"}')
echo "   $response"
echo ""

echo "4. Verificando unidade criada..."
response=$(curl -s "$BASE_URL/api/ldap/organizational-units")
echo "   $response"
echo ""

echo "✅ Testes concluídos!"
echo "🌐 Acesse a interface em: $BASE_URL/ldap-manager" 