# Restrição: Administradores de OU - Criação de Usuários

## 🎯 **Objetivo**

Implementar restrição para que administradores de OU só possam criar usuários na própria Unidade Organizacional, especificando se o usuário será comum ou administrador.

## ✅ **Funcionalidade Implementada**

### **Para Administradores de OU**
- ✅ **Restrição de OU**: Só podem criar usuários na própria OU
- ✅ **Seleção de Papel**: Podem definir se o usuário será "Usuário Comum" ou "Administrador"
- ✅ **Interface Simplificada**: Campo OU readonly mostrando apenas sua OU
- ✅ **Validação Backend**: Impede criação em OUs diferentes da sua

### **Para Usuários ROOT**
- ✅ **Sem Restrições**: Continuam podendo criar usuários em qualquer OU
- ✅ **Interface Completa**: Mantém funcionalidade de múltiplas OUs
- ✅ **Controle Total**: Acesso irrestrito como antes

## 🔧 **Alterações Implementadas**

### **1. Backend - Validação Aprimorada**

```php
// app/Http/Controllers/LdapUserController.php
if ($role === RoleResolver::ROLE_OU_ADMIN) {
    $adminOu = RoleResolver::getUserOu(auth()->user());
    
    // Validar se alguma OU especificada não é a do admin
    $requestedOus = collect($request->organizationalUnits)->map(function($i) {
        return is_string($i) ? $i : ($i['ou'] ?? null);
    })->filter();
    
    foreach ($requestedOus as $requestedOu) {
        if (strtolower($requestedOu) !== strtolower($adminOu)) {
            return response()->json([
                'success' => false,
                'message' => "Acesso negado: você só pode criar usuários na OU '{$adminOu}'"
            ], 403);
        }
    }
}
```

### **2. Frontend - Interface Diferenciada**

#### **Para ROOT (interface completa):**
```html
<div v-if="isRoot">
    <label>Organizações</label>
    <!-- Múltiplas OUs com botão adicionar -->
    <select multiple>...</select>
    <button>+ adicionar OU</button>
</div>
```

#### **Para Admin OU (interface restrita):**
```html
<div v-if="isOuAdmin">
    <label>Unidade Organizacional</label>
    <input readonly v-model="adminOu">
    <select v-model="newUserRole">
        <option value="user">Usuário Comum</option>
        <option value="admin">Administrador</option>
    </select>
</div>
```

### **3. Lógica JavaScript**

#### **Obtenção da OU do Admin:**
```javascript
async getAdminOu() {
    const currentUser = this.users.find(u => u.uid === window.USER_UID);
    if (currentUser && currentUser.organizationalUnits.length > 0) {
        const adminOuEntry = currentUser.organizationalUnits.find(unit => 
            unit.role === 'admin'
        );
        this.adminOu = adminOuEntry ? adminOuEntry.ou : currentUser.organizationalUnits[0].ou;
    }
}
```

#### **Criação de Usuário Diferenciada:**
```javascript
async createUser() {
    let userData = { ...this.newUser };
    
    if (this.isOuAdmin) {
        // Para admin de OU: usar apenas sua OU com o papel selecionado
        userData.organizationalUnits = [{ ou: this.adminOu, role: this.newUserRole }];
    }
    
    // Enviar para API...
}
```

## 🎨 **Interface do Usuário**

### **Tela do Administrador de OU**

```
┌─────────────────────────────────────────┐
│ 📋 Criar Novo Usuário                   │
├─────────────────────────────────────────┤
│ UID: [________________]                 │
│ Nome: [______________]                  │
│ Sobrenome: [_________]                  │
│ Email: [_____________]                  │
│ CPF: [_________]                        │
│ Senha: [_____________]                  │
│                                         │
│ 🏢 Unidade Organizacional               │
│ ┌─────────────────┬─────────────────┐   │
│ │ TI (readonly)   │ [▼] Usuário     │   │
│ └─────────────────┴─────────────────┘   │
│ ℹ️ O usuário será criado na sua OU     │
│ com o papel selecionado                 │
│                                         │
│ [Cancelar]              [Criar Usuário] │
└─────────────────────────────────────────┘
```

### **Opções de Papel**
- 📝 **Usuário Comum**: Acesso apenas à troca de senha
- 👤 **Administrador**: Pode gerenciar usuários da OU

## 🚨 **Validações e Segurança**

### **Validações Backend**
1. ✅ **OU Restrita**: Admin só pode usar sua própria OU
2. ✅ **Papel Válido**: Apenas "user" ou "admin" permitidos
3. ✅ **UID Único**: Por OU (mantida validação existente)
4. ✅ **CPF Único**: Globalmente (mantida validação existente)

### **Validações Frontend**
1. ✅ **Campo OU Readonly**: Impede alteração manual
2. ✅ **Seleção de Papel**: Dropdown com opções válidas
3. ✅ **Reset Automático**: Limpa formulário após criação

### **Mensagens de Erro**
```json
{
    "success": false,
    "message": "Acesso negado: você só pode criar usuários na OU 'TI'"
}
```

## 🧪 **Como Testar**

### **1. Como Administrador de OU**

1. **Faça login** com conta de admin de OU
2. **Clique em "Novo Usuário"**
3. **Verifique a interface**:
   - Campo OU deve estar readonly com sua OU
   - Dropdown deve mostrar "Usuário Comum" e "Administrador"
4. **Preencha os dados** e selecione o papel
5. **Crie o usuário**

### **2. Validação de Restrição**

Tente enviar via API com OU diferente:
```bash
curl -X POST /api/ldap/users \
  -H "Content-Type: application/json" \
  -d '{
    "uid": "test.user",
    "givenName": "Test",
    "sn": "User", 
    "mail": "test@test.com",
    "userPassword": "senha123",
    "employeeNumber": "99999",
    "organizationalUnits": [{"ou": "OutraOU", "role": "user"}]
  }'
```

**Resultado esperado**: Erro 403 com mensagem de acesso negado.

## 📊 **Comportamento por Tipo de Usuário**

| Tipo de Usuário | OUs Disponíveis | Pode Criar em | Interface |
|------------------|-----------------|---------------|-----------|
| **ROOT** | Todas | Qualquer OU | Completa (múltiplas OUs) |
| **Admin OU** | Apenas sua OU | Apenas sua OU | Simplificada (OU readonly) |
| **Usuário Comum** | N/A | Não pode criar | Sem acesso |

## 🎯 **Casos de Uso**

### **Admin de OU "TI"**
- ✅ Pode criar usuários na OU "TI" como user ou admin
- ❌ **NÃO** pode criar usuários na OU "RH"
- ❌ **NÃO** pode criar usuários em múltiplas OUs

### **Admin de OU "RH"**  
- ✅ Pode criar usuários na OU "RH" como user ou admin
- ❌ **NÃO** pode criar usuários na OU "TI"
- ❌ **NÃO** pode criar usuários em múltiplas OUs

### **Usuário ROOT**
- ✅ Pode criar usuários em qualquer OU
- ✅ Pode criar usuários em múltiplas OUs
- ✅ Interface completa mantida

## ✨ **Benefícios**

1. **🔒 Segurança**: Admins só gerenciam sua própria OU
2. **🎯 Simplicidade**: Interface adaptada ao contexto
3. **⚡ Eficiência**: Processo mais rápido para admins de OU
4. **🛡️ Isolamento**: Evita erros entre OUs diferentes
5. **📈 Escalabilidade**: Permite delegação segura de administração

---

**Data da Implementação**: 2024  
**Versão**: 1.2  
**Testado**: ✅ Funcional 