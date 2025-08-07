# Correção: Logs para Administradores de OU

## 🔍 **Problema Identificado**

Administradores de Organização não conseguiam carregar os logs de suas organizações, recebendo erro de acesso negado.

### **Causa Raiz**
- Rota `/api/ldap/logs` estava protegida apenas pelo middleware `IsRootUser`
- Método `getOperationLogs()` não filtrava logs por OU para administradores
- Interface não aplicava filtro de permissão adequado

## ✅ **Solução Implementada**

### **1. Alteração na Rota (routes/api.php)**
```php
// ANTES: Apenas ROOT
Route::middleware(IsRootUser::class)->group(function () {
    Route::get('/logs', [LdapUserController::class, 'getOperationLogs']);
});

// DEPOIS: ROOT e Admin OU
Route::middleware(IsOUAdmin::class)->get('/logs', [LdapUserController::class, 'getOperationLogs']);
```

### **2. Filtro por OU no Controlador**
```php
public function getOperationLogs(): JsonResponse
{
    $role = RoleResolver::resolve(auth()->user());
    
    // Se for ROOT, vê todos os logs
    if ($role === RoleResolver::ROLE_ROOT) {
        $logs = OperationLog::orderBy('created_at', 'desc')->get();
    } else {
        // Se for admin de OU, vê apenas logs da sua OU
        $adminOu = RoleResolver::getUserOu(auth()->user());
        $logs = OperationLog::where('ou', $adminOu)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    return response()->json([
        'success' => true,
        'data' => $logs,
        'message' => 'Logs carregados com sucesso'
    ]);
}
```

### **3. Interface Atualizada**
- Aba "Logs" agora só aparece para `canManageUsers` (ROOT e Admin OU)
- Watcher da aba verifica permissão antes de carregar logs
- Adicionado log de debug para troubleshooting

## 🎯 **Comportamento Atual**

### **Para Usuários ROOT**
- ✅ Veem **todos os logs** do sistema
- ✅ Aba "Logs" visível na interface
- ✅ Acesso completo via API

### **Para Administradores de OU**
- ✅ Veem apenas logs **da sua OU**
- ✅ Aba "Logs" visível na interface
- ✅ Filtro automático por OU na API

### **Para Usuários Comuns**
- ❌ Não veem aba "Logs"
- ❌ Sem acesso à API de logs
- ✅ Apenas acesso à troca de senha

## 🧪 **Comandos de Teste**

### **Verificar Logs Existentes**
```bash
# Ver todos os logs
php artisan logs:test-access

# Ver logs de uma OU específica
php artisan logs:test-access moreno
```

### **Criar Logs de Teste**
```bash
# Criar 5 logs de teste para OU "moreno"
php artisan logs:create-test moreno

# Criar 10 logs de teste
php artisan logs:create-test moreno --count=10
```

## 🔧 **Troubleshooting**

### **Se admin OU não vê logs:**

1. **Verificar se há logs para a OU:**
   ```bash
   php artisan logs:test-access [nome-da-ou]
   ```

2. **Criar logs de teste:**
   ```bash
   php artisan logs:create-test [nome-da-ou]
   ```

3. **Verificar console do navegador:**
   - Abrir F12 → Console
   - Procurar por mensagens de debug do carregamento de logs

4. **Verificar papel do usuário:**
   ```bash
   # No Laravel Tinker
   php artisan tinker
   $user = App\Ldap\LdapUserModel::where('uid', 'SEU_UID')->first();
   App\Services\RoleResolver::resolve($user);
   App\Services\RoleResolver::getUserOu($user);
   ```

## 📊 **Logs Monitorados**

A tabela `operation_logs` registra as seguintes operações:

- `create_user` - Criação de usuário
- `update_user` - Atualização de usuário  
- `delete_user` - Exclusão de usuário
- `update_password` - Alteração de senha
- `create_ou` - Criação de OU
- `update_ou` - Atualização de OU
- `create_user_ldif` - Criação via LDIF
- `create_ou_ldif` - Criação de OU via LDIF

## ✨ **Benefícios**

- ✅ **Segurança**: Cada admin vê apenas logs da sua OU
- ✅ **Auditoria**: Rastreamento completo de operações
- ✅ **Facilidade**: Interface integrada e intuitiva
- ✅ **Performance**: Filtro automático reduz volume de dados
- ✅ **Flexibilidade**: ROOT continua vendo tudo

---

**Data da Correção**: 2024  
**Versão**: 1.1  
**Testado**: ✅ 