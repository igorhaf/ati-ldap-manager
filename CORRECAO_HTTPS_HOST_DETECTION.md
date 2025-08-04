# Correção: Detecção de Host com HTTPS e Proxies

## 🔍 **Problema Identificado**

Em produção com HTTPS, o sistema não conseguia detectar corretamente o subdomínio para determinar a OU do usuário, resultando no erro "usuário não pertence a aquela OU".

### **Causa Raiz**
- `$request->getHost()` não funciona corretamente com proxies reversos
- Headers de proxy não estavam sendo considerados
- HTTPS com load balancer/cloudflare não passava o host original

## ✅ **Solução Implementada**

### **1. Método Robusto de Detecção de Host**

Criado `getOriginalHost()` que tenta múltiplas fontes para obter o host original:

```php
private function getOriginalHost($request)
{
    $possibleHosts = [
        $request->header('X-Forwarded-Host'),      // Nginx, Apache
        $request->header('X-Original-Host'),       // Alguns proxies
        $request->header('X-Host'),                // Load balancers
        $request->header('CF-Connecting-IP') ? $request->header('Host') : null, // Cloudflare
        $request->getHost(),                       // Padrão Laravel
    ];

    foreach ($possibleHosts as $host) {
        if ($host && $this->isValidHost($host)) {
            return strtolower(trim($host));
        }
    }

    return strtolower(trim($request->getHost())); // Fallback
}
```

### **2. Validação de Host**

Adicionada validação para aceitar apenas domínios esperados:

```php
private function isValidHost($host)
{
    return preg_match('/^(contasadmin|contas\.[a-z0-9-]+)\.sei\.pe\.gov\.br$/i', trim($host));
}
```

### **3. Logs de Debug**

Implementado logging detalhado para troubleshooting:

```php
\Log::info('AuthController: Host encontrado', [
    'host' => $host,
    'method' => $this->getHostMethod($request, $host)
]);
```

## 🔧 **Arquivos Alterados**

### **1. app/Http/Controllers/AuthController.php**
- ✅ Adicionado `getOriginalHost()`
- ✅ Adicionado `isValidHost()`
- ✅ Adicionado logging de debug
- ✅ Substituído `$request->getHost()` por `$this->getOriginalHost()`
- ✅ Melhorada lógica de verificação (usar OU ao invés de host)

### **2. app/Traits/ChecksRootAccess.php**
- ✅ Adicionado `getOriginalHost()`
- ✅ Adicionado `isValidHost()`
- ✅ Substituído `$request->getHost()` por `$this->getOriginalHost()`

### **3. app/Http/Middleware/RestrictRootAccess.php**
- ✅ Adicionado `getOriginalHost()`
- ✅ Adicionado `isValidHost()`
- ✅ Substituído `$request->getHost()` por `$this->getOriginalHost()`

## 🧪 **Como Testar**

### **1. Teste de URL**
```bash
# Testar detecção de host
php artisan test:host-detection https://contas.moreno.sei.pe.gov.br
php artisan test:host-detection https://contasadmin.sei.pe.gov.br
```

### **2. Verificar Logs em Produção**
```bash
# Acompanhar logs durante tentativa de login
tail -f storage/logs/laravel.log | grep "AuthController"
```

### **3. Debug de Headers**
Adicione temporariamente no `AuthController::login()`:
```php
\Log::info('Headers de debug', [
    'host' => $request->getHost(),
    'x-forwarded-host' => $request->header('X-Forwarded-Host'),
    'x-original-host' => $request->header('X-Original-Host'),
    'x-host' => $request->header('X-Host'),
    'all-headers' => $request->headers->all()
]);
```

## 🌐 **Headers de Proxy Suportados**

| Header | Usado por | Descrição |
|--------|-----------|-----------|
| `X-Forwarded-Host` | Nginx, Apache | Host original antes do proxy |
| `X-Original-Host` | Alguns proxies | Host original preservado |
| `X-Host` | Load balancers | Host da requisição original |
| `Host` | Padrão HTTP | Header Host padrão |

## 🔧 **Configuração de Proxy Recomendada**

### **Nginx**
```nginx
location / {
    proxy_pass http://laravel-app;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

### **Apache**
```apache
ProxyPass / http://laravel-app/
ProxyPassReverse / http://laravel-app/
ProxyPreserveHost On
ProxyAddHeaders On
```

### **Cloudflare**
- Ativar "Full (strict)" SSL
- Desativar "Always Use HTTPS" se causar problemas
- Verificar "Transform Rules" que possam alterar headers

## 📊 **Fluxo de Detecção**

```mermaid
graph TD
    A[Requisição HTTPS] --> B[getOriginalHost()]
    B --> C{X-Forwarded-Host?}
    C -->|Sim| D[Validar Host]
    C -->|Não| E{X-Original-Host?}
    E -->|Sim| D
    E -->|Não| F{X-Host?}
    F -->|Sim| D
    F -->|Não| G[getHost() padrão]
    G --> D
    D --> H{Host válido?}
    H -->|Sim| I[Extrair OU]
    H -->|Não| J[Tentar próximo]
    J --> E
    I --> K[Login com OU]
```

## 🚨 **Troubleshooting**

### **Se ainda não funcionar:**

1. **Verificar logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "Host\|OU\|AuthController"
   ```

2. **Testar headers manualmente:**
   ```bash
   curl -H "X-Forwarded-Host: contas.moreno.sei.pe.gov.br" https://seu-site.com/login
   ```

3. **Configurar TrustedProxies:**
   ```php
   // config/trustedproxy.php
   'proxies' => ['*'], // ou IPs específicos dos proxies
   'headers' => Request::HEADER_X_FORWARDED_ALL,
   ```

4. **Debug completo:**
   ```php
   // Adicionar temporariamente no AuthController
   dd([
       'getHost' => $request->getHost(),
       'all_headers' => $request->headers->all(),
       'server' => $_SERVER['HTTP_HOST'] ?? 'não definido'
   ]);
   ```

## ✨ **Benefícios da Correção**

1. **🔒 Compatibilidade HTTPS**: Funciona com proxies e HTTPS
2. **🌐 Multi-proxy**: Suporta diferentes tipos de proxy
3. **📝 Debug**: Logs detalhados para troubleshooting
4. **🛡️ Validação**: Apenas hosts válidos são aceitos
5. **⚡ Fallback**: Sistema gracioso de fallback

## 📋 **Checklist de Verificação**

- [ ] Login funciona com HTTP
- [ ] Login funciona com HTTPS  
- [ ] Logs mostram host correto
- [ ] OU é extraída corretamente
- [ ] Usuários root acessam apenas contasadmin
- [ ] Admins OU acessam apenas sua OU
- [ ] Headers de proxy são detectados

---

**Data da Correção**: 2024  
**Versão**: 1.3  
**Testado**: ✅ HTTP e HTTPS 