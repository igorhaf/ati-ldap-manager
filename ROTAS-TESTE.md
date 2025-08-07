# Rotas para Teste - Gerenciador LDAP

## 🌐 Rotas Disponíveis

### **1. Interface Web**
```
GET /ldap-manager
```
**Descrição**: Interface principal do gerenciador LDAP
**Teste**: Acesse http://localhost:8000/ldap-manager

---

### **2. API de Usuários**

#### **2.1 Listar Todos os Usuários**
```
GET /api/ldap/users
```
**Descrição**: Retorna todos os usuários cadastrados no LDAP
**Teste com cURL**:
```bash
curl -X GET http://localhost:8000/api/ldap/users
```

**Resposta esperada**:
```json
{
    "success": true,
    "data": [
        {
            "dn": "uid=joao.silva,ou=users,dc=example,dc=com",
            "uid": "joao.silva",
            "givenName": "João",
            "sn": "Silva",
            "cn": "João Silva",
            "fullName": "João Silva",
            "mail": ["joao.silva@empresa.com"],
            "employeeNumber": "12345",
            "organizationalUnits": ["TI"]
        }
    ],
    "message": "Usuários carregados com sucesso"
}
```

---

#### **2.2 Criar Novo Usuário**
```
POST /api/ldap/users
```
**Descrição**: Cria um novo usuário no LDAP
**Teste com cURL**:
```bash
curl -X POST http://localhost:8000/api/ldap/users \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $(curl -s http://localhost:8000/api/csrf-token)" \
  -d '{
    "uid": "maria.santos",
    "givenName": "Maria",
    "sn": "Santos",
    "employeeNumber": "67890",
    "mail": ["maria.santos@empresa.com", "maria@outro.com"],
    "userPassword": "senha123",
    "organizationalUnits": ["RH", "Administração"]
  }'
```

**Dados obrigatórios**:
- `uid`: Login do usuário (único)
- `givenName`: Nome
- `sn`: Sobrenome
- `employeeNumber`: CPF (único)
- `mail`: Array com pelo menos um email
- `userPassword`: Senha (mínimo 6 caracteres)

**Dados opcionais**:
- `organizationalUnits`: Array com organizações

---

#### **2.3 Buscar Usuário Específico**
```
GET /api/ldap/users/{uid}
```
**Descrição**: Busca um usuário específico pelo UID
**Teste com cURL**:
```bash
curl -X GET http://localhost:8000/api/ldap/users/joao.silva
```

**Resposta esperada**:
```json
{
    "success": true,
    "data": {
        "dn": "uid=joao.silva,ou=users,dc=example,dc=com",
        "uid": "joao.silva",
        "givenName": "João",
        "sn": "Silva",
        "cn": "João Silva",
        "fullName": "João Silva",
        "mail": ["joao.silva@empresa.com"],
        "employeeNumber": "12345",
        "organizationalUnits": ["TI"]
    },
    "message": "Usuário encontrado com sucesso"
}
```

---

#### **2.4 Atualizar Usuário**
```
PUT /api/ldap/users/{uid}
```
**Descrição**: Atualiza dados de um usuário existente
**Teste com cURL**:
```bash
curl -X PUT http://localhost:8000/api/ldap/users/joao.silva \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $(curl -s http://localhost:8000/api/csrf-token)" \
  -d '{
    "givenName": "João Pedro",
    "mail": ["joao.pedro@empresa.com", "joao@novo.com"],
    "organizationalUnits": ["TI", "Desenvolvimento"]
  }'
```

**Dados que podem ser atualizados**:
- `givenName`: Nome
- `sn`: Sobrenome
- `mail`: Array de emails
- `userPassword`: Nova senha
- `organizationalUnits`: Array de organizações

---

#### **2.5 Excluir Usuário**
```
DELETE /api/ldap/users/{uid}
```
**Descrição**: Remove um usuário do LDAP
**Teste com cURL**:
```bash
curl -X DELETE http://localhost:8000/api/ldap/users/joao.silva \
  -H "X-CSRF-TOKEN: $(curl -s http://localhost:8000/api/csrf-token)"
```

**Resposta esperada**:
```json
{
    "success": true,
    "message": "Usuário removido com sucesso"
}
```

---

### **3. API de Organizações**

#### **3.1 Listar Organizações**
```
GET /api/ldap/organizational-units
```
**Descrição**: Retorna todas as organizações
**Teste com cURL**:
```bash
curl -X GET http://localhost:8000/api/ldap/organizational-units
```

**Resposta esperada**:
```json
{
    "success": true,
    "data": [
        {
            "dn": "ou=TI,dc=example,dc=com",
            "ou": "TI",
            "description": "Departamento de Tecnologia da Informação"
        },
        {
            "dn": "ou=RH,dc=example,dc=com",
            "ou": "RH",
            "description": "Recursos Humanos"
        }
    ],
    "message": "Organizações carregadas com sucesso"
}
```

---

#### **3.2 Criar Nova Unidade Organizacional**
```
POST /api/ldap/organizational-units
```
**Descrição**: Cria uma nova unidade organizacional
**Teste com cURL**:
```bash
curl -X POST http://localhost:8000/api/ldap/organizational-units \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $(curl -s http://localhost:8000/api/csrf-token)" \
  -d '{
    "ou": "Marketing",
    "description": "Departamento de Marketing"
  }'
```

**Dados obrigatórios**:
- `ou`: Nome da unidade organizacional

**Dados opcionais**:
- `description`: Descrição da unidade

---

## 🧪 Script de Teste Automatizado

Crie um arquivo `test-routes.sh` para testar todas as rotas automaticamente:

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"
CSRF_TOKEN=$(curl -s -c cookies.txt "$BASE_URL" | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)

echo "🔍 Testando rotas do Gerenciador LDAP..."
echo "========================================"

# Teste 1: Listar usuários
echo "1. Testando GET /api/ldap/users"
curl -s -X GET "$BASE_URL/api/ldap/users" | jq '.'
echo ""

# Teste 2: Listar organizações
echo "2. Testando GET /api/ldap/organizational-units"
curl -s -X GET "$BASE_URL/api/ldap/organizational-units" | jq '.'
echo ""

# Teste 3: Criar unidade organizacional
echo "3. Testando POST /api/ldap/organizational-units"
curl -s -X POST "$BASE_URL/api/ldap/organizational-units" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
  -d '{"ou": "Teste", "description": "Unidade de teste"}' | jq '.'
echo ""

# Teste 4: Criar usuário
echo "4. Testando POST /api/ldap/users"
curl -s -X POST "$BASE_URL/api/ldap/users" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
  -d '{
    "uid": "teste.user",
    "givenName": "Usuário",
    "sn": "Teste",
    "employeeNumber": "99999",
    "mail": ["teste@empresa.com"],
    "userPassword": "senha123",
    "organizationalUnits": ["Teste"]
  }' | jq '.'
echo ""

# Teste 5: Buscar usuário criado
echo "5. Testando GET /api/ldap/users/teste.user"
curl -s -X GET "$BASE_URL/api/ldap/users/teste.user" | jq '.'
echo ""

# Teste 6: Atualizar usuário
echo "6. Testando PUT /api/ldap/users/teste.user"
curl -s -X PUT "$BASE_URL/api/ldap/users/teste.user" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" \
  -d '{"givenName": "Usuário Atualizado"}' | jq '.'
echo ""

# Teste 7: Excluir usuário
echo "7. Testando DELETE /api/ldap/users/teste.user"
curl -s -X DELETE "$BASE_URL/api/ldap/users/teste.user" \
  -H "X-CSRF-TOKEN: $CSRF_TOKEN" | jq '.'
echo ""

echo "✅ Testes concluídos!"
```

## 🛠️ Ferramentas para Teste

### **1. cURL**
Para testar as APIs via linha de comando

### **2. Postman**
Para testes mais avançados com interface gráfica

### **3. Insomnia**
Alternativa ao Postman

### **4. Thunder Client (VS Code)**
Extensão do VS Code para testes de API

## 📋 Checklist de Testes

- [ ] **Interface Web**: Acessar http://localhost:8000/ldap-manager
- [ ] **Listar Usuários**: GET /api/ldap/users
- [ ] **Listar OUs**: GET /api/ldap/organizational-units
- [ ] **Criar OU**: POST /api/ldap/organizational-units
- [ ] **Criar Usuário**: POST /api/ldap/users
- [ ] **Buscar Usuário**: GET /api/ldap/users/{uid}
- [ ] **Atualizar Usuário**: PUT /api/ldap/users/{uid}
- [ ] **Excluir Usuário**: DELETE /api/ldap/users/{uid}

## ⚠️ Observações Importantes

1. **CSRF Token**: Todas as operações POST/PUT/DELETE requerem o token CSRF
2. **Validação**: Os dados são validados antes de serem salvos no LDAP
3. **Unicidade**: UID e employeeNumber devem ser únicos
4. **Senhas**: Mínimo de 6 caracteres
5. **Emails**: Pelo menos um email é obrigatório
6. **LDAP**: Certifique-se de que o servidor LDAP está rodando

## 🚀 Como Executar os Testes

1. **Inicie o servidor**:
   ```bash
   php artisan serve
   ```

2. **Execute os testes**:
   ```bash
   # Teste manual com cURL
   curl -X GET http://localhost:8000/api/ldap/users
   
   # Ou use o script automatizado
   chmod +x test-routes.sh
   ./test-routes.sh
   ```

3. **Verifique as respostas**: Todas devem retornar `"success": true` 