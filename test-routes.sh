#!/bin/bash

BASE_URL="http://localhost:8000"

echo "🔍 Testando rotas do Gerenciador LDAP..."
echo "========================================"
echo ""

# Função para obter CSRF token
get_csrf_token() {
    # Primeiro, vamos tentar obter o token da página principal
    TOKEN=$(curl -s "$BASE_URL/ldap-manager" | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)
    
    if [ -z "$TOKEN" ]; then
        # Se não encontrar, vamos tentar da página inicial
        TOKEN=$(curl -s "$BASE_URL" | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)
    fi
    
    echo $TOKEN
}

CSRF_TOKEN=$(get_csrf_token)

if [ -z "$CSRF_TOKEN" ]; then
    echo "⚠️  Não foi possível obter o CSRF token. Alguns testes podem falhar."
    echo "   Isso é normal se o servidor não estiver rodando ou se não houver sessão ativa."
    echo ""
fi

# Função para testar uma rota
test_route() {
    local method=$1
    local endpoint=$2
    local data=$3
    local description=$4
    
    echo "🧪 $description"
    echo "   $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -X GET "$BASE_URL$endpoint")
    elif [ "$method" = "POST" ]; then
        if [ -n "$CSRF_TOKEN" ]; then
            response=$(curl -s -X POST "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
                -d "$data")
        else
            response=$(curl -s -X POST "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -d "$data")
        fi
    elif [ "$method" = "PUT" ]; then
        if [ -n "$CSRF_TOKEN" ]; then
            response=$(curl -s -X PUT "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
                -d "$data")
        else
            response=$(curl -s -X PUT "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -d "$data")
        fi
    elif [ "$method" = "DELETE" ]; then
        if [ -n "$CSRF_TOKEN" ]; then
            response=$(curl -s -X DELETE "$BASE_URL$endpoint" \
                -H "X-CSRF-TOKEN: $CSRF_TOKEN")
        else
            response=$(curl -s -X DELETE "$BASE_URL$endpoint")
        fi
    fi
    
    # Verificar se a resposta contém sucesso
    if echo "$response" | grep -q '"success":true'; then
        echo "   ✅ Sucesso"
    elif echo "$response" | grep -q '"success":false'; then
        echo "   ❌ Falha"
        echo "   Resposta: $response"
    else
        echo "   ⚠️  Resposta inesperada"
        echo "   Resposta: $response"
    fi
    echo ""
}

# Teste 1: Listar usuários
test_route "GET" "/api/ldap/users" "" "Listando todos os usuários"

# Teste 2: Listar unidades organizacionais
test_route "GET" "/api/ldap/organizational-units" "" "Listando unidades organizacionais"

# Teste 3: Criar unidade organizacional de teste
test_route "POST" "/api/ldap/organizational-units" '{"ou": "Teste", "description": "Unidade de teste para validação"}' "Criando unidade organizacional de teste"

# Teste 4: Criar usuário de teste
test_route "POST" "/api/ldap/users" '{
    "uid": "teste.user",
    "givenName": "Usuário",
    "sn": "Teste",
    "employeeNumber": "99999",
    "mail": ["teste@empresa.com"],
    "userPassword": "senha123",
    "organizationalUnits": ["Teste"]
}' "Criando usuário de teste"

# Teste 5: Buscar usuário criado
test_route "GET" "/api/ldap/users/teste.user" "" "Buscando usuário específico"

# Teste 6: Atualizar usuário
test_route "PUT" "/api/ldap/users/teste.user" '{"givenName": "Usuário Atualizado"}' "Atualizando dados do usuário"

# Teste 7: Buscar usuário após atualização
test_route "GET" "/api/ldap/users/teste.user" "" "Verificando dados atualizados"

# Teste 8: Tentar criar usuário duplicado (deve falhar)
test_route "POST" "/api/ldap/users" '{
    "uid": "teste.user",
    "givenName": "Usuário Duplicado",
    "sn": "Teste",
    "employeeNumber": "99999",
    "mail": ["duplicado@empresa.com"],
    "userPassword": "senha123"
}' "Tentando criar usuário duplicado (deve falhar)"

# Teste 9: Tentar criar usuário com dados inválidos (deve falhar)
test_route "POST" "/api/ldap/users" '{
    "uid": "teste.invalido",
    "givenName": "",
    "sn": "",
    "employeeNumber": "",
    "mail": [],
    "userPassword": "123"
}' "Tentando criar usuário com dados inválidos (deve falhar)"

# Teste 10: Excluir usuário de teste
test_route "DELETE" "/api/ldap/users/teste.user" "" "Excluindo usuário de teste"

# Teste 11: Verificar se usuário foi excluído
test_route "GET" "/api/ldap/users/teste.user" "" "Verificando se usuário foi excluído (deve falhar)"

echo "✅ Testes concluídos!"
echo ""
echo "📊 Resumo:"
echo "   - Testes de leitura (GET): 4"
echo "   - Testes de criação (POST): 3"
echo "   - Testes de atualização (PUT): 1"
echo "   - Testes de exclusão (DELETE): 1"
echo "   - Testes de validação: 2"
echo ""
echo "🌐 Para acessar a interface web:"
echo "   $BASE_URL/ldap-manager"
echo ""
echo "📝 Para mais detalhes, consulte o arquivo ROTAS-TESTE.md" 