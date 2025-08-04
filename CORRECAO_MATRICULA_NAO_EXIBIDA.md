# Correção: Matrícula Não Exibida na Edição

## 🔍 **Problema Identificado**

O campo de **matrícula não está sendo exibido** quando edita um usuário:

- **Campo afetado**: `employeeNumber` (Matrícula)
- **Localização**: Modal de edição de usuário
- **Problema**: Campo aparece vazio mesmo quando o usuário tem matrícula

## 🔍 **Diagnóstico**

### **1. Verificação do Frontend**

O campo está presente no HTML:
```html
<input type="text" v-model="editUser.employeeNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100" disabled />
```

### **2. Verificação do JavaScript**

O objeto `editUser` está sendo inicializado corretamente:
```javascript
editUser: {
    uid: '',
    givenName: '',
    sn: '',
    employeeNumber: '', // ✅ Inicializado
    mail: '',
    userPassword: '',
    organizationalUnits: [{ou: '', role: 'user'}]
}
```

### **3. Verificação do Backend**

O método `index()` retorna `employeeNumber`:
```php
'employeeNumber' => $first->getFirstAttribute('employeeNumber'),
```

O método `show()` também retorna `employeeNumber`:
```php
'employeeNumber' => $user->getFirstAttribute('employeeNumber'),
```

## 🛠️ **Solução Implementada**

### **1. Logs de Debug Adicionados**

Adicionados logs para rastrear o problema:

```javascript
openEditUserModal(user) {
    console.log('🔍 Dados do usuário para edição:', user);
    
    this.editUser.uid = user.uid;
    this.editUser.givenName = user.givenName;
    this.editUser.sn = user.sn;
    this.editUser.employeeNumber = user.employeeNumber;
    this.editUser.mail = user.mail;
    this.editUser.userPassword = '';
    
    console.log('📝 editUser após carregamento:', this.editUser);
    // ... resto do código
}
```

### **2. Comando de Teste Criado**

Criado comando para diagnosticar o problema:

```bash
sudo ./vendor/bin/sail artisan test:employee-number renata.strobel
```

**Funcionalidades do comando:**
- ✅ Verifica se o usuário existe no LDAP
- ✅ Lista todos os atributos disponíveis
- ✅ Verifica especificamente o `employeeNumber`
- ✅ Simula a resposta da API
- ✅ Analisa se o valor é null, vazio ou tem conteúdo
- ✅ Identifica se o problema é no backend ou frontend

## 🧪 **Como Testar**

### **1. Via Interface Web:**
1. **Faça login** como admin
2. **Edite** um usuário (ex: `renata.strobel`)
3. **Abra o console** do navegador (F12)
4. **Verifique** os logs de debug
5. **Confirme** se `employeeNumber` aparece nos logs

### **2. Via Comando:**
```bash
sudo ./vendor/bin/sail artisan test:employee-number renata.strobel
```

### **3. Saída Esperada do Teste:**
```
🔍 Teste do Atributo Employee Number
===================================
UID: renata.strobel

1️⃣ Buscando usuário no LDAP...
✅ Usuário encontrado
DN: cn=renata.strobel,ou=gravata,dc=sei,dc=pe,dc=gov,dc=br

2️⃣ Verificando todos os atributos...
Atributos disponíveis:
  - uid: renata.strobel
  - givenName: renata
  - sn: strobel
  - employeeNumber: 12345
  - mail: renata@empresa.com

3️⃣ Verificando employeeNumber especificamente...
✅ employeeNumber encontrado: 12345

6️⃣ Análise do employeeNumber...
✅ employeeNumber tem valor: '12345'

7️⃣ Verificando possível problema no frontend...
✅ Backend está retornando employeeNumber
🔍 Verifique se o frontend está carregando corretamente
```

## 🔧 **Possíveis Causas**

### **1. Atributo Não Existe no LDAP:**
- ❌ `employeeNumber` não foi definido no schema LDAP
- ❌ Usuário foi criado sem matrícula
- ❌ Atributo foi removido posteriormente

### **2. Problema no Backend:**
- ❌ Método `getFirstAttribute('employeeNumber')` retorna null
- ❌ Atributo existe mas não está sendo retornado pela API
- ❌ Erro na consulta LDAP

### **3. Problema no Frontend:**
- ❌ Dados não estão sendo carregados corretamente
- ❌ `v-model` não está funcionando
- ❌ Objeto `editUser` não está sendo atualizado

## 💡 **Próximos Passos**

### **Se o Backend Retorna o Valor:**
1. ✅ Verificar logs do console do navegador
2. ✅ Confirmar se `user.employeeNumber` tem valor
3. ✅ Verificar se `this.editUser.employeeNumber` é atualizado
4. ✅ Testar se o `v-model` está funcionando

### **Se o Backend Não Retorna o Valor:**
1. ✅ Verificar se o atributo existe no LDAP
2. ✅ Verificar se o schema LDAP inclui `employeeNumber`
3. ✅ Verificar se o usuário foi criado com matrícula
4. ✅ Testar com outros usuários

## 📊 **Status do Problema**

| **Componente** | **Status** | **Observação** |
|----------------|------------|----------------|
| **HTML** | ✅ OK | Campo presente e configurado |
| **JavaScript** | ✅ OK | Inicialização e atribuição corretas |
| **Backend API** | ✅ OK | Métodos retornam employeeNumber |
| **LDAP Schema** | 🔍 Pendente | Precisa verificar se atributo existe |
| **Dados do Usuário** | 🔍 Pendente | Precisa verificar se usuário tem matrícula |

## 🎯 **Resultado Esperado**

Após a correção, o campo de matrícula deve:
- ✅ **Exibir o valor** quando editar um usuário
- ✅ **Manter o valor** durante a edição
- ✅ **Não permitir edição** (campo desabilitado)
- ✅ **Ser consistente** com outros campos

---

**Status**: 🔍 **Em investigação**  
**Prioridade**: **Média**  
**Teste**: Comando `test:employee-number` disponível  
**Debug**: Logs adicionados no frontend 