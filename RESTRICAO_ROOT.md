# Restrição de Acesso para Usuários Root

## Visão Geral

O sistema implementa uma restrição de segurança que impede usuários com perfil **root** de acessarem o sistema através de URLs que não sejam `contas.sei.pe.gov.br`.

## Como Funciona

### 1. **Detecção de Usuário Root**
- O sistema verifica se o usuário possui `employeeType` com valor "root" em qualquer uma de suas entradas LDAP
- Esta verificação é feita através do `RoleResolver::resolve()`

### 2. **Verificação de URL**
- Durante o login e em todas as requisições autenticadas, o sistema verifica o host da requisição
- Se o usuário é root e não está acessando via `contas.sei.pe.gov.br`, o acesso é negado

### 3. **Comportamento**
- **Durante o Login**: Se um usuário root tentar fazer login via URL incorreta, o login é rejeitado com mensagem de erro
- **Após o Login**: Se um usuário root já logado tentar acessar recursos via URL incorreta, recebe erro 403
- **API**: Requisições JSON retornam erro 403 com mensagem explicativa
- **Web**: Requisições web redirecionam para página de erro 403 personalizada

## Implementação Técnica

### Trait: `ChecksRootAccess`
```php
// Verifica se usuário root está acessando pela URL correta
if ($role === RoleResolver::ROLE_ROOT) {
    $host = $request->getHost();
    if ($host !== 'contas.sei.pe.gov.br') {
        abort(403, 'O acesso a este usuário não pode ser feito por essa URL');
    }
}
```

### AuthController
```php
// Verificação adicional durante o login
if ($role === RoleResolver::ROLE_ROOT) {
    $host = $request->getHost();
    if ($host !== 'contas.sei.pe.gov.br') {
        Auth::logout();
        return back()->withErrors(['uid' => 'O acesso a este usuário não pode ser feito por essa URL']);
    }
}
```

## Rotas Protegidas

A verificação é aplicada nas seguintes rotas:

### Web Routes
- `/ldap-manager` - Gerenciador principal
- `/password-change` - Troca de senha

### API Routes
- `/api/ldap/*` - Todas as rotas da API LDAP

## Página de Erro Personalizada

Quando um usuário root tenta acessar pela URL incorreta, é exibida uma página de erro 403 (`resources/views/errors/403.blade.php`) que:

- Explica a restrição de forma genérica
- Fornece link direto para a URL correta
- Permite fazer logout
- Tem design consistente com o sistema

## Testando a Funcionalidade

### Comando Artisan
```bash
# Testar se um usuário é root
php artisan test:root-access {uid}
```

### Exemplo de Uso
```bash
php artisan test:root-access admin
```

### Saída Esperada
```
Testando acesso root para o usuário: admin
Usuário encontrado: uid=admin,ou=TI,dc=example,dc=com
Role do usuário: root
⚠️  ATENÇÃO: Este usuário é ROOT!
O acesso a este usuário não pode ser feito por essa URL
Entradas encontradas: 1
  - OU: TI, employeeType: ["root"]
```

## Configuração

### Variáveis de Ambiente
A URL permitida está hardcoded no trait. Para torná-la configurável, adicione ao `.env`:

```env
ROOT_ACCESS_URL=contas.sei.pe.gov.br
```

E modifique o trait para usar:
```php
$allowedHost = config('app.root_access_url', 'contas.sei.pe.gov.br');
```

## Segurança

### Benefícios
- **Isolamento**: Usuários root só acessam através de URL específica
- **Auditoria**: Facilita rastreamento de ações administrativas
- **Controle**: Impede acesso acidental de usuários root via URLs não autorizadas

### Considerações
- A restrição é baseada no host da requisição
- Usuários root ainda podem acessar via IP se o host não for verificado
- Para maior segurança, considere implementar verificação de certificado SSL

## Troubleshooting

### Problema: Usuário root não consegue acessar
**Solução**: Verificar se está acessando via `contas.sei.pe.gov.br`

### Problema: Erro 403 inesperado
**Solução**: Verificar se o usuário tem `employeeType` com valor "root" no LDAP

### Problema: Verificação não está funcionando
**Solução**: Verificar se o trait está sendo usado nos controladores corretos 