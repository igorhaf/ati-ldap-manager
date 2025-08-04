# Correção: LDAP Invalid DN Syntax

## 🔍 **Problema Identificado**

Erro ao criar usuário: `ldap_add(): Add: Invalid DN syntax`

### **Causa Raiz**
O Distinguished Name (DN) estava sendo construído de forma insegura, sem escape de caracteres especiais:

```php
// ❌ PROBLEMÁTICO
$entry->setDn("uid={$request->uid},ou={$ou},{$baseDn}");
```

**Problemas:**
- Caracteres especiais no UID/OU não escapados
- Espaços no início/fim dos valores
- Caracteres de controle ou inválidos

## ✅ **Solução Implementada**

### **1. Classe Utilitária LdapDnUtils**

Criada `App\Utils\LdapDnUtils` com métodos seguros:

```php
// ✅ SEGURO
$safeDn = LdapDnUtils::buildUserDn($uid, $ou, $baseDn);
$entry->setDn($safeDn);
```

#### **Métodos Principais:**
- `escapeDnValue($value)` - Escapa caracteres especiais
- `buildUserDn($uid, $ou, $baseDn)` - Constrói DN de usuário seguro
- `buildOuDn($ou, $baseDn)` - Constrói DN de OU seguro
- `isValidDnValue($value)` - Valida se valor é seguro para DN
- `hasProblematicChars($value)` - Detecta caracteres problemáticos

### **2. Validações Adicionais**

#### **Validação de UID:**
```php
'uid' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/'
```

#### **Validação de DN:**
```php
if (!LdapDnUtils::isValidDnValue($request->uid)) {
    return response()->json([
        'success' => false,
        'message' => 'UID contém caracteres inválidos para LDAP'
    ], 422);
}
```

### **3. Escape de Caracteres Especiais**

A classe escapa automaticamente:
- `,` → `\,`
- `"` → `\"`
- `\` → `\\`
- `/` → `\/`
- `<` → `\<`
- `>` → `\>`
- `;` → `\;`
- `=` → `\=`
- `+` → `\+`
- `#` → `\#`

### **4. Logs de Debug**

Adicionado logging para facilitar troubleshooting:

```php
\Log::info('Criando usuário com DN', [
    'uid' => $request->uid,
    'ou' => $ou,
    'dn' => $safeDn
]);
```

## 🧪 **Como Testar**

### **1. Comando de Teste de DN**
```bash
# Testar construção de DN
sudo ./vendor/bin/sail artisan test:dn-construction "joao.silva" "ti"

# Testar com caracteres problemáticos  
sudo ./vendor/bin/sail artisan test:dn-construction "user,test" "ou/special"
```

### **2. Verificar Logs**
```bash
tail -f storage/logs/laravel.log | grep "Criando usuário com DN"
```

### **3. Teste via Interface**
- Tente criar usuário com UID normal: `joao.silva`
- Tente criar usuário com caracteres especiais: `user,test`
- Verifique mensagens de erro específicas

## 📊 **Caracteres Problemáticos Comuns**

| Caractere | Problema | Escape |
|-----------|----------|--------|
| `,` | Separador de componentes DN | `\,` |
| `"` | Delimitador de string | `\"` |
| `\` | Caractere de escape | `\\` |
| `/` | Separador de caminho | `\/` |
| `=` | Separador atributo=valor | `\=` |
| `+` | Separador multi-valor | `\+` |
| `<>` | Delimitadores | `\<` `\>` |

## 🔧 **Exemplos de Uso**

### **Construção Segura de DN:**
```php
use App\Utils\LdapDnUtils;

// Para usuário
$userDn = LdapDnUtils::buildUserDn('joao.silva', 'ti', $baseDn);
// Resultado: uid=joao.silva,ou=ti,dc=sei,dc=pe,dc=gov,dc=br

// Para OU
$ouDn = LdapDnUtils::buildOuDn('recursos-humanos', $baseDn);
// Resultado: ou=recursos-humanos,dc=sei,dc=pe,dc=gov,dc=br
```

### **Validação de Valores:**
```php
// Verificar se valor é seguro
if (LdapDnUtils::isValidDnValue($uid)) {
    // Valor seguro para usar em DN
}

// Verificar caracteres problemáticos
if (LdapDnUtils::hasProblematicChars($uid)) {
    $problematic = LdapDnUtils::getProblematicChars($uid);
    // Array com lista de caracteres problemáticos
}
```

## 🚨 **Casos Especiais**

### **1. UIDs com Pontos:**
```
✅ VÁLIDO: joao.silva, user.test, admin.sistema
❌ INVÁLIDO: user,test, admin/sistema, user"test
```

### **2. OUs com Espaços:**
```
✅ VÁLIDO: recursos-humanos, tecnologia-informacao
❌ INVÁLIDO: recursos humanos, ti/desenvolvimento
```

### **3. Caracteres Acentuados:**
```
⚠️  CUIDADO: joão, administração
💡 RECOMENDADO: joao, administracao
```

## 📋 **Checklist de Verificação**

### **Antes da Correção:**
- [ ] ❌ DNS construídos concatenando strings diretamente
- [ ] ❌ Sem validação de caracteres especiais
- [ ] ❌ Sem escape de valores
- [ ] ❌ Erros "Invalid DN syntax" frequentes

### **Após a Correção:**
- [ ] ✅ Uso de `LdapDnUtils::buildUserDn()`
- [ ] ✅ Validação regex para UID
- [ ] ✅ Verificação `isValidDnValue()`
- [ ] ✅ Logs de debug habilitados
- [ ] ✅ Escape automático de caracteres especiais

## 🎯 **Próximos Passos**

1. **Teste com dados reais** que causavam o erro
2. **Verifique logs** para confirmar DNs seguros
3. **Teste casos extremos** com caracteres especiais
4. **Monitore** por novos erros de DN syntax

## 💡 **Dicas Adicionais**

### **Para Desenvolvedores:**
- Sempre use `LdapDnUtils::buildUserDn()` ao invés de concatenação manual
- Valide entrada do usuário antes de construir DN
- Use o comando `test:dn-construction` para debug

### **Para Administradores:**
- Configure UIDs seguindo padrão: `[a-zA-Z0-9._-]+`
- Evite caracteres especiais em nomes de OU
- Monitore logs para identificar tentativas de valores inválidos

### **Para Troubleshooting:**
- Use `tail -f storage/logs/laravel.log | grep DN` para acompanhar construção de DNs
- Execute comando de teste com valores suspeitos
- Verifique estrutura do LDAP se problemas persistirem

---

**Data da Correção**: 2024  
**Status**: ✅ DN seguro implementado  
**Testado**: Construção de DN com escape automático  
**Compatível**: OpenLDAP e Active Directory 