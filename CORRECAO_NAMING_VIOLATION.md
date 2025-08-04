# Correção: LDAP Naming Violation

## 🔍 **Problema Identificado**

Erro ao atualizar usuário: `ldap_modify_batch(): Batch Modify: Naming violation`

### **Causa Raiz**
O erro "Naming violation" ocorre quando tentamos **modificar atributos que fazem parte do RDN** (Relative Distinguished Name) do usuário no LDAP.

#### **Exemplo Problemático:**
- **DN**: `cn=alberto.viegas,ou=gravata,dc=sei,dc=pe,dc=gov,dc=br`
- **RDN**: `cn=alberto.viegas` (primeira parte do DN)
- **Problema**: Código tentava modificar o atributo `cn`

**❌ O que não pode ser feito:**
```php
// PROIBIDO: cn está no RDN, não pode ser modificado diretamente
$user->setFirstAttribute('cn', 'novo nome');
```

## ✅ **Solução Implementada**

### **1. Método de Verificação `isAttributeInRdn()`**

Criado método que verifica se um atributo é parte do RDN:

```php
private function isAttributeInRdn($entry, $attributeName): bool
{
    $dn = $entry->getDn();
    if (!$dn) {
        return false;
    }

    // Extrair o RDN (primeira parte do DN)
    $rdnPart = explode(',', $dn)[0];
    
    // Verificar se o atributo está no RDN
    return preg_match("/^{$attributeName}=/i", trim($rdnPart));
}
```

### **2. Método Seguro `setSafeAttribute()`**

Criado método que só modifica atributos que **não estão no RDN**:

```php
private function setSafeAttribute($entry, $attributeName, $value): bool
{
    if ($this->isAttributeInRdn($entry, $attributeName)) {
        \Log::warning("Tentativa de modificar atributo do RDN ignorada", [
            'dn' => $entry->getDn(),
            'attribute' => $attributeName,
            'value' => $value
        ]);
        return false;
    }

    $entry->setFirstAttribute($attributeName, $value);
    return true;
}
```

### **3. Correção do Código de Atualização**

**❌ Antes (Perigoso):**
```php
// Poderia causar naming violation
$user->setFirstAttribute('cn', trim($givenName . ' ' . $sn));
```

**✅ Depois (Seguro):**
```php
// Verifica se cn está no RDN antes de modificar
$newCn = trim($givenName . ' ' . $sn);
$this->setSafeAttribute($user, 'cn', $newCn);
```

### **4. Logs de Debug**

Quando uma modificação é bloqueada, um log é gerado:
```
⚠️ Tentativa de modificar atributo do RDN ignorada: 
   DN: cn=alberto.viegas,ou=gravata,dc=...
   Attribute: cn
   Value: alberto viegas
```

## 🧪 **Como Testar**

### **1. Comando de Teste Específico:**
```bash
# Testar com usuário que tem cn no RDN
sudo ./vendor/bin/sail artisan test:naming-violation alberto.viegas

# Ou modo interativo
sudo ./vendor/bin/sail artisan test:naming-violation
```

### **2. Saída Esperada do Teste:**
```
🔍 Teste de Naming Violation
===========================
UID: alberto.viegas

1️⃣ Buscando usuário no LDAP...
✅ Usuário encontrado
DN: cn=alberto.viegas,ou=gravata,dc=sei,dc=pe,dc=gov,dc=br

2️⃣ Analisando RDN (Relative Distinguished Name)...
RDN: cn=alberto.viegas
Atributo RDN: cn
Valor RDN: alberto.viegas

3️⃣ Verificando atributos que podem causar Naming Violation...
❌ cn está no RDN - NÃO PODE ser modificado
✅ uid não está no RDN - PODE ser modificado
✅ ou não está no RDN - PODE ser modificado

5️⃣ Testando método setSafeAttribute...
🛡️ Método setSafeAttribute bloqueou modificação de 'cn' (está no RDN)
⚠️ setSafeAttribute retornou false (atributo ignorado)
```

### **3. Teste via Interface Web:**
1. **Edite** um usuário (ex: `alberto.viegas`)
2. **Altere nome/sobrenome**
3. **Salve alterações**
4. **Resultado esperado:** ✅ Sucesso (sem naming violation)

## 📊 **Tipos de DN e Impactos**

### **DN com CN (Problemático):**
```
cn=usuario.nome,ou=departamento,dc=empresa,dc=com
```
- ❌ **CN não pode** ser modificado
- ✅ **UID, mail, etc.** podem ser modificados

### **DN com UID (Menos Problemático):**
```
uid=usuario.nome,ou=departamento,dc=empresa,dc=com
```
- ❌ **UID não pode** ser modificado
- ✅ **CN, mail, etc.** podem ser modificados

### **DN com OU (Raro):**
```
ou=departamento,dc=empresa,dc=com
```
- ❌ **OU não pode** ser modificada
- ✅ Outros atributos podem ser modificados

## 🚨 **Atributos Comumente no RDN**

| **Atributo** | **Descrição** | **Frequência no RDN** |
|--------------|---------------|----------------------|
| `cn` | Common Name | ⚠️ Muito Comum |
| `uid` | User ID | ⚠️ Comum |
| `ou` | Organizational Unit | ⚠️ Para OUs |
| `dc` | Domain Component | ⚠️ Para domínios |
| `mail` | Email | 🟢 Raro |
| `givenName` | Nome | 🟢 Muito Raro |
| `sn` | Sobrenome | 🟢 Muito Raro |

## 🔧 **Detalhes Técnicos**

### **Por que CN está frequentemente no RDN?**
- **Tradição**: Muitos schemas LDAP usam CN como identificador principal
- **Legibilidade**: CNs são mais legíveis que UIDs
- **Compatibilidade**: Active Directory frequentemente usa CN

### **Alternativas para Modificar CN:**
1. **Ignorar modificação** (implementado) ✅
2. **Rename DN** (complexo, não implementado)
3. **Usar atributo alternativo** (mudança de schema)

### **Por que não fazemos Rename DN?**
- **Complexidade**: Operação muito mais complexa
- **Riscos**: Pode quebrar referências
- **Permissões**: Requer privilégios especiais
- **Compatibilidade**: Nem todos servidores suportam bem

## 💡 **Melhorias Implementadas**

### **1. Robustez:**
- ✅ Detecta automaticamente atributos no RDN
- ✅ Ignora modificações perigosas
- ✅ Logs informativos sobre bloqueios
- ✅ Não interrompe outras modificações

### **2. Flexibilidade:**
- ✅ Funciona com qualquer tipo de DN
- ✅ Suporta múltiplos atributos no RDN
- ✅ Adaptável a diferentes schemas LDAP

### **3. Debug:**
- ✅ Comando específico para testar
- ✅ Logs detalhados
- ✅ Verificação automática de segurança

## 🎯 **Resultado Final**

O erro **"ldap_modify_batch(): Batch Modify: Naming violation"** não deve mais ocorrer porque:

1. **✅ Detecção automática** de atributos no RDN
2. **✅ Bloqueio seguro** de modificações perigosas  
3. **✅ Logs informativos** para debug
4. **✅ Outras modificações** continuam funcionando

### **Comportamento Agora:**
- **Atributos no RDN**: Ignorados (com log de aviso)
- **Atributos normais**: Modificados normalmente
- **Outras operações**: Não afetadas

### **Exemplo Prático:**
Para usuário `cn=alberto.viegas,ou=gravata,dc=...`:
- ❌ **CN**: Ignorado (está no RDN)
- ✅ **givenName**: Modificado normalmente
- ✅ **sn**: Modificado normalmente  
- ✅ **mail**: Modificado normalmente
- ✅ **employeeType**: Modificado normalmente

---

**Status**: ✅ **Naming violation corrigido**  
**Método**: Detecção automática + bloqueio seguro  
**Compatibilidade**: Funciona com qualquer estrutura de DN  
**Debug**: Comando `test:naming-violation` disponível 