# Criação de Usuários via LDIF em Múltiplas OUs

Este documento explica como usar a funcionalidade LDIF para criar o mesmo usuário em diferentes Unidades Organizacionais (OUs).

## Visão Geral

A funcionalidade LDIF permite:
- Gerar arquivos LDIF para criar usuários em múltiplas OUs
- Aplicar arquivos LDIF no sistema
- Fazer upload de arquivos LDIF externos
- Criar o mesmo usuário com diferentes perfis em OUs distintas

## Como Usar

### 1. Geração de LDIF via Interface Web

1. Acesse o **Gerenciador LDAP** com um usuário **root**
2. Navegue até a seção **"Operações LDIF"**
3. Na aba **"Gerar LDIF"**:
   - Preencha os dados do usuário (UID, Nome, Sobrenome, etc.)
   - Adicione as OUs desejadas clicando em **"Adicionar OU"**
   - Para cada OU, defina o perfil (usuário ou administrador)
   - Clique em **"Gerar LDIF"** ou **"Gerar e Baixar"**

### 2. Estrutura do LDIF

```ldif
# Criação do usuário na primeira OU
dn: uid=usuario,ou=TI,dc=example,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: usuario
givenName: Nome
sn: Sobrenome
cn: Nome Sobrenome
mail: usuario@empresa.com
employeeNumber: 12345
userPassword: {SSHA}senhahasheada
ou: TI
employeeType: user

# Criação do mesmo usuário na segunda OU
dn: uid=usuario,ou=Financeiro,dc=example,dc=com
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson
uid: usuario
givenName: Nome
sn: Sobrenome
cn: Nome Sobrenome
mail: usuario@empresa.com
employeeNumber: 12345
userPassword: {SSHA}senhahasheada
ou: Financeiro
employeeType: admin
```

### 3. Aplicação de LDIF

#### Via Interface Web:

1. **Aplicar LDIF**: Cole o conteúdo LDIF na aba correspondente
2. **Upload LDIF**: Faça upload de um arquivo `.ldif` ou `.txt`

#### Via API:

```bash
# Aplicar LDIF via texto
curl -X POST /api/ldap/ldif/apply \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: token" \
  -d '{"ldif_content": "conteudo_ldif_aqui"}'

# Upload de arquivo LDIF
curl -X POST /api/ldap/ldif/upload \
  -H "X-CSRF-TOKEN: token" \
  -F "ldif_file=@arquivo.ldif"
```

## Vantagens desta Abordagem

### 1. Reutilização de Usuários
- O mesmo usuário pode atuar em diferentes departamentos
- Evita duplicação de dados pessoais
- Facilita a gestão de identidades

### 2. Perfis Diferenciados
- Usuário comum na OU "TI"
- Administrador na OU "Financeiro"
- Cada entrada pode ter diferentes privilégios

### 3. Flexibilidade
- LDIFs podem ser gerados automaticamente
- Arquivos podem ser editados manualmente
- Fácil migração e backup de dados

### 4. Consistência
- Mesma matrícula (`employeeNumber`) em todas as OUs
- Dados pessoais consistentes
- Senhas sincronizadas

## Validações do Sistema

O sistema realiza as seguintes validações:

1. **UID único por OU**: O mesmo UID não pode existir duas vezes na mesma OU
2. **Matrícula única global**: A matrícula deve ser única em todo o sistema
3. **OUs existentes**: As OUs devem existir antes da criação do usuário
4. **Formato LDIF**: Validação da sintaxe do arquivo LDIF

## Exemplo Prático

### Cenário: João Silva precisa acessar 3 departamentos

```ldif
# João como usuário comum no TI
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
userPassword: {SSHA}senha123hash
ou: TI
employeeType: user

# João como administrador no Financeiro
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
userPassword: {SSHA}senha123hash
ou: Financeiro
employeeType: admin

# João como usuário comum no RH
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
userPassword: {SSHA}senha123hash
ou: RH
employeeType: user
```

## Rotas da API

### Gerar LDIF
- **POST** `/api/ldap/users/generate-ldif`
- Parâmetros: dados do usuário + OUs
- Retorno: LDIF ou arquivo para download

### Aplicar LDIF
- **POST** `/api/ldap/ldif/apply`
- Parâmetro: `ldif_content`
- Retorno: resultados da aplicação

### Upload LDIF
- **POST** `/api/ldap/ldif/upload`
- Parâmetro: `ldif_file`
- Retorno: resultados do processamento

## Logs e Auditoria

Todas as operações LDIF são registradas nos logs do sistema:
- `create_user_ldif`: Criação de usuário via LDIF
- `create_ou_ldif`: Criação de OU via LDIF

## Limitações

1. **Tamanho de arquivo**: Máximo 2MB para upload
2. **Formatos aceitos**: `.ldif`, `.txt`
3. **Permissões**: Apenas usuários **root** podem usar esta funcionalidade
4. **Validação**: LDIFs inválidos são rejeitados com mensagens de erro detalhadas

## Resolução de Problemas

### Erro: "Usuário já existe na OU"
- Verifique se o UID já não está cadastrado na OU específica
- Use a interface de usuários para verificar existência

### Erro: "Matrícula já cadastrada"
- A matrícula deve ser única globalmente
- Consulte os usuários existentes antes de criar novos

### Erro: "OU não encontrada"
- Crie a OU primeiro antes de adicionar usuários
- Use a seção de OUs ou inclua a criação da OU no LDIF

### Erro: "LDIF inválido"
- Verifique a sintaxe do arquivo
- Certifique-se de que todas as linhas estão no formato correto
- Use o exemplo fornecido como referência 