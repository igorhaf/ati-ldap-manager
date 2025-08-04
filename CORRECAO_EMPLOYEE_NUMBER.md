# Correção: Employee Number não aparece no Modal de Edição

## 🔍 **Problema Identificado**

O campo **Matrícula (não editável)** não está sendo preenchido quando o usuário edita um usuário no modal de edição:

- **Campo afetado**: `employeeNumber` (Matrícula)
- **Localização**: Modal de edição de usuário
- **Sintoma**: Campo aparece vazio mesmo quando o usuário tem matrícula no LDAP

## 🔧 **Investigações Realizadas**

### **1. Verificação do Template Blade**
O campo está corretamente definido no template:
```html
<input type="text" v-model="editUser.employeeNumber" class="..." disabled />
```

### **2. Verificação do JavaScript**
A função `openEditUserModal()` está definindo o valor corretamente:
```javascript
this.editUser.employeeNumber = user.employeeNumber;
```

### **3. Verificação do Backend**
Os métodos `index()` e `show()` estão retornando o `employeeNumber`:
```php
'employeeNumber' => $user->getFirstAttribute('employeeNumber'),
```

## 🛠️ **Soluções Implementadas**

### **1. Logs de Debug no Frontend**
Adicionados logs para rastrear os dados:
```javascript
openEditUserModal(user) {
    console.log('🔍 Debug openEditUserModal - Dados recebidos:', user);
    console.log('🔍 employeeNumber recebido:', user.employeeNumber);
    // ... resto do código
    console.log('🔍 editUser após definição:', this.editUser);
}
```

### **2. Comando de Debug LDAP**
Criado comando para verificar dados no LDAP:
```bash
sudo ./vendor/bin/sail artisan debug:employee-number renata.strobel
```

### **3. Comando de Teste da API**
Criado comando para testar a API:
```bash
sudo ./vendor/bin/sail artisan test:employee-number-api renata.strobel
```

## 🧪 **Como Diagnosticar**

### **1. Via Comando LDAP:**
```bash
sudo ./vendor/bin/sail artisan debug:employee-number renata.strobel
```

**Saída esperada:**
```
🔍 Debug do Employee Number
==========================
UID: renata.strobel

1️⃣ Buscando usuário no LDAP...
✅ Usuário encontrado
DN: cn=renata.strobel,ou=gravata,dc=sei,dc=pe,dc=gov,dc=br

2️⃣ Verificando todos os atributos...
Atributos disponíveis:
  - uid: renata.strobel
  - givenName: renata
  - sn: strobel
  - mail: renata@example.com
  - employeeNumber: 12345  ← Deve aparecer aqui
  - ...

3️⃣ Verificando employeeNumber...
employeeNumber (getFirstAttribute): 12345
```

### **2. Via Comando API:**
```bash
sudo ./vendor/bin/sail artisan test:employee-number-api renata.strobel
```

**Saída esperada:**
```
🔍 Teste da API - Employee Number
==================================
UID: renata.strobel

2️⃣ Testando método index()...
✅ API index() funcionando
✅ Usuário encontrado na lista:
  - Employee Number: 12345

3️⃣ Testando método show()...
✅ API show() funcionando
  - Employee Number: 12345
```

### **3. Via Console do Navegador:**
1. Abra o modal de edição de um usuário
2. Abra o console do navegador (F12)
3. Procure pelos logs de debug:
```
🔍 Debug openEditUserModal - Dados recebidos: {uid: "renata.strobel", employeeNumber: "12345", ...}
🔍 employeeNumber recebido: 12345
🔍 editUser após definição: {uid: "renata.strobel", employeeNumber: "12345", ...}
```

## 📊 **Possíveis Causas**

### **1. Atributo não existe no LDAP**
- **Sintoma**: `employeeNumber` é `null` no LDAP
- **Solução**: Verificar se o schema LDAP inclui `employeeNumber`

### **2. Problema na API**
- **Sintoma**: API não retorna `employeeNumber`
- **Solução**: Verificar métodos `index()` e `show()`

### **3. Problema no Frontend**
- **Sintoma**: API retorna dados, mas frontend não exibe
- **Solução**: Verificar logs de debug no console

### **4. Problema de Cache**
- **Sintoma**: Dados antigos sendo exibidos
- **Solução**: Limpar cache do navegador

## 🎯 **Passos para Resolver**

### **1. Verificar LDAP:**
```bash
sudo ./vendor/bin/sail artisan debug:employee-number renata.strobel
```

### **2. Verificar API:**
```bash
sudo ./vendor/bin/sail artisan test:employee-number-api renata.strobel
```

### **3. Verificar Frontend:**
1. Abrir console do navegador
2. Editar usuário
3. Verificar logs de debug

### **4. Testar via Interface:**
1. Fazer login como admin
2. Editar usuário `renata.strobel`
3. Verificar se campo de matrícula está preenchido

## 💡 **Soluções Alternativas**

### **1. Se employeeNumber não existe no LDAP:**
```php
// No LdapUserController, adicionar fallback
'employeeNumber' => $user->getFirstAttribute('employeeNumber') ?: 'N/A',
```

### **2. Se problema é no frontend:**
```javascript
// Forçar atualização do campo
this.$nextTick(() => {
    this.editUser.employeeNumber = user.employeeNumber;
});
```

### **3. Se problema é de cache:**
```javascript
// Limpar dados antes de carregar
this.editUser = {
    uid: '',
    givenName: '',
    sn: '',
    employeeNumber: '',
    mail: '',
    userPassword: '',
    organizationalUnits: []
};
```

## 🔍 **Comandos de Debug Disponíveis**

| **Comando** | **Descrição** | **Uso** |
|-------------|---------------|---------|
| `debug:employee-number` | Debug LDAP | `sudo ./vendor/bin/sail artisan debug:employee-number renata.strobel` |
| `test:employee-number-api` | Teste API | `sudo ./vendor/bin/sail artisan test:employee-number-api renata.strobel` |

## 📝 **Logs de Debug**

### **Frontend (Console do Navegador):**
```
🔍 Debug openEditUserModal - Dados recebidos: {uid: "renata.strobel", employeeNumber: "12345", ...}
🔍 employeeNumber recebido: 12345
🔍 editUser após definição: {uid: "renata.strobel", employeeNumber: "12345", ...}
```

### **Backend (Logs Laravel):**
```php
\Log::info('Employee Number debug', [
    'uid' => $uid,
    'employeeNumber' => $user->getFirstAttribute('employeeNumber')
]);
```

## 🎉 **Resultado Esperado**

Após a correção, o campo **Matrícula (não editável)** deve:
- ✅ **Exibir o valor** correto do LDAP
- ✅ **Manter-se desabilitado** (não editável)
- ✅ **Atualizar automaticamente** quando o modal abrir

---

**Status**: 🔍 **Em investigação**  
**Prioridade**: **Média**  
**Comandos**: `debug:employee-number`, `test:employee-number-api`  
**Logs**: Console do navegador + comandos Artisan 