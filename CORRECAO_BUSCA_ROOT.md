# Correção da Busca de Usuários Root

## ✅ **Problema Identificado**

Usuários root não conseguiam fazer login e recebiam a mensagem **"Usuário não encontrado para esta OU"**, mesmo sendo usuários root que estão na raiz do LDAP (sem OU específica).

## 🔍 **Causa do Problema**

O sistema estava tentando buscar usuários root com a restrição `ou=admin`, mas usuários root estão na raiz do LDAP e não possuem uma OU específica.

### **Busca Original (Incorreta):**
```php
// Para contasadmin.sei.pe.gov.br, extraía OU como "admin"
$ou = 'admin';

// Buscava usuário com uid E ou=admin
$user = LdapUserModel::where('uid', $credentials['uid'])
    ->where('ou', $ou)  // ❌ Usuários root não têm ou=admin
    ->first();
```

### **Resultado:**
- Usuários root não eram encontrados
- Sistema retornava "Usuário não encontrado para esta OU"
- Login falhava mesmo com credenciais corretas

## 🔧 **Correção Implementada**

### **Nova Lógica de Busca:**

```php
// Buscar usuário - lógica diferente para root vs outros usuários
if ($host === 'contasadmin.sei.pe.gov.br') {
    // Para usuários root: buscar apenas pelo uid (estão na raiz do LDAP)
    $user = \App\Ldap\LdapUserModel::where('uid', $credentials['uid'])->first();
} else {
    // Para outros usuários: buscar pelo uid e OU específica
    $user = \App\Ldap\LdapUserModel::where('uid', $credentials['uid'])
        ->where('ou', $ou)
        ->first();
}
```

### **Mensagens de Erro Específicas:**
```php
if (!$user) {
    if ($host === 'contasadmin.sei.pe.gov.br') {
        return back()->withErrors(['uid' => 'Usuário root não encontrado.'])->onlyInput('uid');
    } else {
        return back()->withErrors(['uid' => 'Usuário não encontrado para esta OU.'])->onlyInput('uid');
    }
}
```

## 🎯 **Como Funciona Agora**

### **1. Login de Usuários Root:**
1. Usuário acessa `contasadmin.sei.pe.gov.br/login`
2. Sistema identifica que é URL de root
3. **Busca apenas pelo `uid`** (sem restrição de OU)
4. Encontra usuário na raiz do LDAP
5. Verifica se é usuário root
6. Permite login se estiver via URL correta

### **2. Login de Admins de OU:**
1. Usuário acessa `contas.moreno.sei.pe.gov.br/login`
2. Sistema extrai OU como "moreno"
3. **Busca pelo `uid` E `ou=moreno`**
4. Encontra usuário específico da OU
5. Verifica permissões de admin
6. Permite login se tiver permissões

## 🚀 **Benefícios da Correção**

### **1. Funcionalidade Restaurada**
- ✅ Usuários root podem fazer login normalmente
- ✅ Sistema encontra usuários na raiz do LDAP
- ✅ Fluxo de autenticação funcionando

### **2. Segurança Mantida**
- ✅ Restrição de URL para usuários root preservada
- ✅ Verificação de permissões funcionando
- ✅ Isolamento de usuários por OU mantido

### **3. Mensagens Claras**
- ✅ Erro específico para usuários root não encontrados
- ✅ Erro específico para usuários de OU não encontrados
- ✅ Melhor experiência do usuário

## 📁 **Arquivo Modificado**

- `app/Http/Controllers/AuthController.php`
  - Função `login()`: Lógica de busca diferenciada para root vs outros usuários
  - Mensagens de erro específicas por tipo de usuário

## 🧪 **Testando a Correção**

### **Cenários de Teste:**

1. **Usuário Root via URL Correta:**
   - URL: `contasadmin.sei.pe.gov.br/login`
   - Usuário: admin (na raiz do LDAP)
   - Senha: [senha do admin]
   - **Resultado Esperado**: ✅ Login bem-sucedido

2. **Usuário Root Inexistente:**
   - URL: `contasadmin.sei.pe.gov.br/login`
   - Usuário: inexistente
   - Senha: qualquer
   - **Resultado Esperado**: ❌ "Usuário root não encontrado"

3. **Admin de OU via URL Correta:**
   - URL: `contas.moreno.sei.pe.gov.br/login`
   - Usuário: [admin da OU moreno]
   - Senha: [senha do admin]
   - **Resultado Esperado**: ✅ Login bem-sucedido

4. **Usuário de OU Inexistente:**
   - URL: `contas.moreno.sei.pe.gov.br/login`
   - Usuário: inexistente
   - Senha: qualquer
   - **Resultado Esperado**: ❌ "Usuário não encontrado para esta OU"

## 🔒 **Estrutura LDAP Esperada**

### **Usuários Root (Raiz do LDAP):**
```
dc=example,dc=com
├── uid=admin,dc=example,dc=com
│   ├── uid: admin
│   ├── employeeType: root
│   └── userPassword: {SSHA}...
```

### **Usuários de OU:**
```
dc=example,dc=com
├── ou=moreno,dc=example,dc=com
│   ├── uid=usuario1,ou=moreno,dc=example,dc=com
│   │   ├── uid: usuario1
│   │   ├── ou: moreno
│   │   ├── employeeType: admin
│   │   └── userPassword: {SSHA}...
```

## ✅ **Status Final**

- ✅ **Busca de usuários root corrigida** (apenas por uid)
- ✅ **Busca de usuários de OU mantida** (uid + ou)
- ✅ **Mensagens de erro específicas** por tipo de usuário
- ✅ **Segurança preservada** com restrições de acesso
- ✅ **Compatibilidade mantida** com estrutura LDAP existente
- ✅ **Commit automático** realizado

A correção está **completa e funcional**! Agora usuários root podem fazer login normalmente, sendo buscados apenas pelo `uid` na raiz do LDAP, sem restrição de OU. 🎉 