# Gerenciador de Usuários LDAP

Um gerenciador completo de usuários LDAP desenvolvido com Laravel, Tailwind CSS e Vue.js.

## Funcionalidades

- ✅ **CRUD completo de usuários LDAP**
- ✅ **Gerenciamento de unidades organizacionais**
- ✅ **Interface moderna e responsiva**
- ✅ **Validação de dados**
- ✅ **Busca e filtros**
- ✅ **Notificações em tempo real**

## Atributos do Usuário LDAP

O sistema gerencia os seguintes atributos de usuário:

- **UID**: Login/Chave do usuário (obrigatório)
- **givenName**: Nome do usuário (obrigatório)
- **sn**: Sobrenome (obrigatório)
- **cn**: Nome completo (gerado automaticamente)
- **mail**: Múltiplos emails (obrigatório pelo menos um)
- **employeeNumber**: CPF (obrigatório)
- **userPassword**: Senha (obrigatório)
- **organizationalUnits**: Múltiplas unidades organizacionais (opcional)

## Configuração

### 1. Configuração do LDAP

Edite o arquivo `.env` e configure as variáveis do LDAP:

```env
# LDAP Configuration
LDAP_CONNECTION=default
LDAP_HOST=127.0.0.1
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin
LDAP_PORT=389
LDAP_BASE_DN=dc=example,dc=com
LDAP_TIMEOUT=5
LDAP_SSL=false
LDAP_TLS=false
LDAP_SASL=false
LDAP_LOGGING=true
LDAP_CACHE=false
```

### 2. Instalação de Dependências

```bash
# Instalar dependências PHP
composer install

# Instalar dependências Node.js
npm install

# Compilar assets
npm run build
```

### 3. Configuração do Banco de Dados

O sistema usa PostgreSQL para logs e sessões. Configure no `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=atildaplogs
DB_USERNAME=ati
DB_PASSWORD=123456
```

### 4. Executar Migrações

```bash
php artisan migrate
```

## Uso

### Acessar o Gerenciador

Acesse a URL: `http://localhost:8000/ldap-manager`

### API Endpoints

#### Usuários

- `GET /api/ldap/users` - Listar todos os usuários
- `POST /api/ldap/users` - Criar novo usuário
- `GET /api/ldap/users/{uid}` - Buscar usuário específico
- `PUT /api/ldap/users/{uid}` - Atualizar usuário
- `DELETE /api/ldap/users/{uid}` - Excluir usuário

#### Unidades Organizacionais

- `GET /api/ldap/organizational-units` - Listar todas as OUs
- `POST /api/ldap/organizational-units` - Criar nova OU

### Exemplo de Criação de Usuário

```json
{
    "uid": "joao.silva",
    "givenName": "João",
    "sn": "Silva",
    "employeeNumber": "12345",
    "mail": ["joao.silva@empresa.com", "joao@outro.com"],
    "userPassword": "senha123",
    "organizationalUnits": ["TI", "Desenvolvimento"]
}
```

### Exemplo de Criação de OU

```json
{
    "ou": "TI",
    "description": "Departamento de Tecnologia da Informação"
}
```

## Estrutura do Projeto

```
app/
├── Http/Controllers/
│   └── LdapUserController.php    # Controlador principal
├── Ldap/
│   ├── User.php                  # Modelo LDAP para usuários
│   └── OrganizationalUnit.php    # Modelo LDAP para OUs
resources/
├── views/
│   └── ldap-manager.blade.php    # Interface principal
└── js/
    └── app.js                    # Configuração Vue.js
config/
└── ldap.php                      # Configuração LDAP
routes/
└── web.php                       # Rotas da aplicação
```

## Desenvolvimento

### Executar em Modo de Desenvolvimento

```bash
# Terminal 1 - Servidor Laravel
php artisan serve

# Terminal 2 - Compilação de assets
npm run dev
```

### Testes

```bash
php artisan test
```

## Segurança

- Todas as operações LDAP são validadas
- Senhas são criptografadas no LDAP
- Validação de UID e CPF únicos
- Proteção CSRF em todas as operações

## Troubleshooting

### Erro de Conexão LDAP

1. Verifique se o servidor LDAP está rodando
2. Confirme as credenciais no `.env`
3. Teste a conexão com: `php artisan tinker`
   ```php
   use App\Ldap\LdapUserModel;
   LdapUserModel::all();
   ```

### Erro de Permissões

1. Verifique se o usuário LDAP tem permissões de escrita
2. Confirme se o DN base está correto
3. Verifique os logs em `storage/logs/laravel.log`

## Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## Licença

Este projeto está sob a licença MIT. 