# Permissão de Edição de CPF

## 🔍 **Funcionalidade Implementada**

O sistema agora implementa **controle de permissão** para edição do campo CPF (employeeNumber):

- ✅ **Usuários ROOT**: Podem editar CPF
- ❌ **Usuários ADMIN**: NÃO podem editar CPF
- ❌ **Usuários USER**: NÃO podem editar CPF

## 🎯 **Comportamento por Tipo de Usuário**

### **Para Usuários ROOT:**
```
┌─────────────────────────────────────────────────────────┐
│ CPF                                                      │
│ [12345678901] (editável)                                │
└─────────────────────────────────────────────────────────┘
```

**Características:**
- **Label**: "CPF" (sem texto adicional)
- **Input**: Habilitado e editável
- **Estilo**: Fundo branco, bordas normais
- **Funcionalidade**: Pode alterar o CPF

### **Para Usuários ADMIN/USER:**
```
┌─────────────────────────────────────────────────────────┘
│ CPF (não editável)                                      │
│ [12345678901] (somente leitura)                         │
└─────────────────────────────────────────────────────────┘
```

**Características:**
- **Label**: "CPF (não editável)"
- **Input**: Desabilitado e somente leitura
- **Estilo**: Fundo cinza, indica que não é editável
- **Funcionalidade**: Apenas visualização

## 🛠️ **Implementação Técnica**

### **1. Interface Dinâmica (Vue.js)**

**Modal de Edição (`ldap-simple.blade.php`):**
```html
<label class="block text-sm font-medium text-gray-700 mb-1">
    CPF @{{ isRoot ? '' : '(não editável)' }}
</label>
<input 
    type="text" 
    v-model="editUser.employeeNumber" 
    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
    :class="isRoot ? '' : 'bg-gray-100'" 
    :disabled="!isRoot" 
/>
```

**Modal de Edição (`ldap-manager.blade.php`):**
```html
<label class="block text-sm font-medium text-gray-700">
    CPF @{{ userRole === 'root' ? '' : '(não editável)' }}
</label>
<input 
    type="text" 
    v-model="editUserData.employeeNumber" 
    class="mt-1 block w-full border rounded px-3 py-2" 
    :class="userRole === 'root' ? '' : 'bg-gray-100'" 
    :disabled="userRole !== 'root'" 
/>
```

### **2. Lógica de Controle**

**Verificação de Role:**
- `isRoot`: Variável Vue que indica se o usuário logado é root
- `userRole`: Variável Vue que contém o role do usuário ('root', 'admin', 'user')

**Classes CSS Dinâmicas:**
- **Root**: Sem classe adicional (fundo branco)
- **Outros**: `bg-gray-100` (fundo cinza)

**Estado do Input:**
- **Root**: `:disabled="false"` (habilitado)
- **Outros**: `:disabled="true"` (desabilitado)

## 📊 **Comparação de Permissões**

| **Campo** | **ROOT** | **ADMIN** | **USER** |
|-----------|----------|-----------|----------|
| **UID** | ❌ Não editável | ❌ Não editável | ❌ Não editável |
| **CPF** | ✅ Editável | ❌ Não editável | ❌ Não editável |
| **Nome** | ✅ Editável | ✅ Editável | ✅ Editável |
| **Sobrenome** | ✅ Editável | ✅ Editável | ✅ Editável |
| **Email** | ✅ Editável | ✅ Editável | ✅ Editável |
| **Senha** | ✅ Editável | ✅ Editável | ✅ Editável |
| **OUs** | ✅ Gerenciar todas | ✅ Gerenciar sua OU | ❌ Apenas visualizar |

## 🧪 **Como Testar**

### **1. Via Interface Web:**
1. **Faça login** como usuário root
2. **Edite** um usuário qualquer
3. **Verifique**: Campo CPF está editável
4. **Faça login** como admin de OU
5. **Edite** um usuário
6. **Verifique**: Campo CPF está desabilitado

### **2. Via Comando:**
```bash
# Testar com usuário root
sudo ./vendor/bin/sail artisan test:cpf-edit-permission admin

# Testar com usuário admin
sudo ./vendor/bin/sail artisan test:cpf-edit-permission joao.admin

# Testar com usuário comum
sudo ./vendor/bin/sail artisan test:cpf-edit-permission usuario.comum
```

### **3. Saída Esperada do Teste:**
```
🔍 Teste de Permissão de Edição de CPF
=======================================
UID: admin

1️⃣ Buscando usuário no LDAP...
✅ Usuário encontrado
DN: uid=admin,dc=example,dc=com

2️⃣ Determinando role do usuário...
employeeType: root
Role determinado: root

3️⃣ Verificando permissão de edição de CPF...
✅ Usuário ROOT pode editar CPF
   - Campo CPF será editável
   - Label: 'CPF' (sem texto adicional)
   - Input: Habilitado e sem fundo cinza

4️⃣ Simulando interface de edição...
┌─────────────────────────────────────────────────────────┐
│ CPF                                                      │
│ [12345678901] (editável)                                │
└─────────────────────────────────────────────────────────┘

✅ Teste concluído com sucesso!
```

## 🔒 **Segurança**

### **Benefícios:**
- **Controle de Acesso**: Apenas usuários root podem alterar CPFs
- **Integridade de Dados**: Previne alterações acidentais
- **Auditoria**: Facilita rastreamento de mudanças de CPF
- **Conformidade**: Atende requisitos de segurança

### **Considerações:**
- **Frontend**: Controle implementado na interface
- **Backend**: Validação adicional pode ser implementada
- **Logs**: Alterações de CPF devem ser registradas
- **Backup**: CPFs são dados críticos, backup necessário

## 📁 **Arquivos Modificados**

### **Views:**
- `resources/views/ldap-simple.blade.php`
- `resources/views/ldap-manager.blade.php`

### **Comandos:**
- `app/Console/Commands/TestCpfEditPermission.php`

### **Documentação:**
- `PERMISSAO_EDICAO_CPF.md` (este arquivo)

## 🎉 **Resultado Final**

A funcionalidade está **completa e funcional**:

- ✅ **Interface dinâmica** baseada no role do usuário
- ✅ **Controle de permissão** implementado
- ✅ **Feedback visual** claro para o usuário
- ✅ **Teste automatizado** disponível
- ✅ **Documentação completa** criada

### **Comportamento Final:**
- **ROOT**: Pode editar CPF normalmente
- **ADMIN/USER**: Campo CPF desabilitado e somente leitura
- **Interface**: Adapta automaticamente baseada no role
- **Segurança**: Controle de acesso implementado

---

**Status**: ✅ **Permissão de edição de CPF implementada**  
**Acesso**: Apenas usuários ROOT  
**Interface**: Dinâmica baseada no role  
**Teste**: Comando `test:cpf-edit-permission` disponível 