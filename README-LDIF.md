# Funcionalidade LDIF - ATI LDAP Manager

## 🎯 Objetivo

Implementação de funcionalidade LDIF para permitir a criação do mesmo usuário em múltiplas Organizações com diferentes perfis e privilégios.

## ✨ Funcionalidades

### 1. **Geração de LDIF**
- Interface web para criar LDIFs interativamente
- API para geração programática
- Download automático de arquivos LDIF
- Suporte a múltiplas OUs por usuário

### 2. **Aplicação de LDIF**
- Aplicação via interface web (cola e aplica)
- Upload de arquivos LDIF
- Validação completa da sintaxe
- Relatórios detalhados de resultado

### 3. **Validações**
- UID único por OU
- CPF único globalmente
- Validação de OUs existentes
- Verificação de sintaxe LDIF

## 🚀 Como Usar

### Interface Web

1. **Acesse como usuário ROOT**
2. **Navegue para "Operações LDIF"**
3. **Escolha uma das opções:**
   - **Gerar LDIF**: Crie LDIF interativamente
   - **Aplicar LDIF**: Cole conteúdo LDIF
   - **Upload LDIF**: Faça upload de arquivo

### Linha de Comando

```bash
# Gerar LDIF de teste
php artisan ldif:test-generation joao.silva João Silva 12345 joao@empresa.com senha123 TI:user Financeiro:admin RH:user

# Aplicar LDIF de arquivo
php artisan ldif:test-apply exemplo-usuario-multiplas-ous.ldif
```

### API REST

```bash
# Gerar LDIF
curl -X POST /api/ldap/users/generate-ldif \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: token" \
  -d '{
    "uid": "joao.silva",
    "givenName": "João",
    "sn": "Silva",
    "employeeNumber": "12345",
    "mail": "joao@empresa.com",
    "userPassword": "senha123",
    "organizationalUnits": [
      {"ou": "TI", "role": "user"},
      {"ou": "Financeiro", "role": "admin"}
    ]
  }'

# Aplicar LDIF
curl -X POST /api/ldap/ldif/apply \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: token" \
  -d '{"ldif_content": "dn: uid=test..."}'

# Upload arquivo LDIF
curl -X POST /api/ldap/ldif/upload \
  -H "X-CSRF-TOKEN: token" \
  -F "ldif_file=@arquivo.ldif"
```

## 📁 Estrutura de Arquivos

```
├── app/
│   ├── Services/
│   │   └── LdifService.php          # Serviço principal LDIF
│   ├── Http/Controllers/
│   │   └── LdapUserController.php   # Métodos LDIF adicionados
│   └── Console/Commands/
│       ├── TestLdifGeneration.php   # Comando de teste
│       └── TestLdifApplication.php  # Comando de aplicação
├── routes/
│   └── api.php                      # Rotas LDIF adicionadas
├── resources/views/
│   └── ldap-manager.blade.php       # Interface web atualizada
├── exemplo-usuario-multiplas-ous.ldif        # Exemplo LDIF
├── LDIF_USUARIO_MULTIPLAS_OUS.md             # Documentação detalhada
└── README-LDIF.md                            # Este arquivo
```

## 🔧 Exemplos Práticos

### Exemplo 1: Usuário em 3 OUs

```ldif
# João Silva como usuário no TI
dn: uid=joao.silva,ou=TI,dc=empresa,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: joao.silva
givenName: João
sn: Silva
cn: João Silva
mail: joao.silva@empresa.com
employeeNumber: 12345
userPassword: {SSHA}hashedpassword
ou: TI
employeeType: user

# João Silva como admin no Financeiro
dn: uid=joao.silva,ou=Financeiro,dc=empresa,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: joao.silva
givenName: João
sn: Silva
cn: João Silva
mail: joao.silva@empresa.com
employeeNumber: 12345
userPassword: {SSHA}hashedpassword
ou: Financeiro
employeeType: admin

# João Silva como usuário no RH
dn: uid=joao.silva,ou=RH,dc=empresa,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: joao.silva
givenName: João
sn: Silva
cn: João Silva
mail: joao.silva@empresa.com
employeeNumber: 12345
userPassword: {SSHA}hashedpassword
ou: RH
employeeType: user
```

### Exemplo 2: Criação de OU + Usuário

```ldif
# Criar nova OU
dn: ou=Desenvolvimento,dc=empresa,dc=com
objectClass: top
objectClass: organizationalUnit
ou: Desenvolvimento
description: Equipe de Desenvolvimento

# Criar usuário na nova OU
dn: uid=dev.usuario,ou=Desenvolvimento,dc=empresa,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: dev.usuario
givenName: Desenvolvedor
sn: Usuário
cn: Desenvolvedor Usuário
mail: dev@empresa.com
employeeNumber: 67890
userPassword: {SSHA}senhadev
ou: Desenvolvimento
employeeType: admin
```

## ⚙️ Comandos de Teste

### Gerar LDIF via comando

```bash
# Sintaxe
php artisan ldif:test-generation {uid} {givenName} {sn} {employeeNumber} {mail} {password} {ous*}

# Exemplo
php artisan ldif:test-generation maria.santos Maria Santos 54321 maria@empresa.com senha456 TI Vendas:admin Marketing:user
```

### Aplicar LDIF via comando

```bash
# Aplicar arquivo do storage/app/
php artisan ldif:test-apply exemplo-usuario-multiplas-ous.ldif

# Aplicar arquivo com caminho completo
php artisan ldif:test-apply /caminho/completo/arquivo.ldif
```

## 📊 Logs e Auditoria

Todas as operações LDIF são registradas:

```php
// Logs criados
'create_user_ldif' => 'Usuário criado via LDIF'
'create_ou_ldif'   => 'OU criada via LDIF'
```

Consulte logs em:
- `GET /api/ldap/logs` (via API)
- Interface web na seção de logs

## 🚨 Validações e Erros

### Validações Implementadas

1. **UID único por OU**: Evita duplicatas na mesma OU
2. **CPF único**: Um CPF por todo o sistema
3. **Formato LDIF**: Validação de sintaxe
4. **OUs existentes**: Verifica se as OUs existem

### Erros Comuns

| Erro | Causa | Solução |
|------|-------|---------|
| "Usuário já existe na OU" | UID duplicado na OU | Use UID diferente ou OU diferente |
| "CPF já cadastrado" | employeeNumber duplicado | Use CPF único |
| "OU não encontrada" | OU não existe | Crie a OU primeiro |
| "LDIF inválido" | Sintaxe incorreta | Verifique formato do arquivo |

## 🔒 Segurança

- **Acesso restrito**: Apenas usuários ROOT
- **Validação CSRF**: Proteção contra ataques
- **Sanitização**: Limpeza de dados de entrada
- **Logs de auditoria**: Rastreamento de operações

## 📈 Vantagens da Abordagem LDIF

### 1. **Flexibilidade**
- Usuários em múltiplas OUs
- Perfis diferentes por OU
- Edição manual de LDIFs

### 2. **Consistência**
- Mesmo CPF em todas as OUs
- Dados pessoais sincronizados
- Senhas unificadas

### 3. **Portabilidade**
- Arquivos LDIF padrão
- Backup e migração fáceis
- Interoperabilidade

### 4. **Auditoria**
- Logs detalhados
- Rastreamento de mudanças
- Controle de acesso

## 🧪 Testando a Implementação

### 1. **Teste via Interface**

1. Acesse `/ldap-manager`
2. Faça login como ROOT
3. Navegue para "Operações LDIF"
4. Teste cada aba:
   - Gerar LDIF
   - Aplicar LDIF
   - Upload LDIF

### 2. **Teste via Comando**

```bash
# Teste de geração
php artisan ldif:test-generation teste.user Teste User 99999 teste@test.com senha123 TI RH:admin

# Verificar arquivo gerado
ls storage/app/test_user_*.ldif

# Teste de aplicação
php artisan ldif:test-apply test_user_teste.user_*.ldif
```

### 3. **Teste via API**

```bash
# Teste com curl
curl -X POST http://localhost/api/ldap/users/generate-ldif \
  -H "Content-Type: application/json" \
  -d '{"uid":"api.test","givenName":"API","sn":"Test","employeeNumber":"88888","mail":"api@test.com","userPassword":"senha","organizationalUnits":[{"ou":"TI","role":"user"}]}'
```

## 📚 Documentação Adicional

- **Documentação completa**: `LDIF_USUARIO_MULTIPLAS_OUS.md`
- **Arquivo de exemplo**: `exemplo-usuario-multiplas-ous.ldif`
- **Schema LDAP**: `ldap-schema.ldif`

## 🔄 Próximos Passos

1. **Testes em ambiente de produção**
2. **Otimizações de performance**
3. **Interface aprimorada**
4. **Integração com Active Directory**
5. **Backup automático de LDIFs**

---

**Desenvolvido para o ATI LDAP Manager**  
**Versão**: 1.0  
**Data**: 2024 