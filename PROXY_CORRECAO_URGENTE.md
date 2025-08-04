# 🚨 CORREÇÃO URGENTE - ERRO HEADER_X_FORWARDED_ALL

## ❌ **PROBLEMA IDENTIFICADO**

**Erro:** `Undefined constant Illuminate\Http\Request::HEADER_X_FORWARDED_ALL`

**Causa:** A constante `HEADER_X_FORWARDED_ALL` não existe no Laravel. Usei uma constante incorreta no AppServiceProvider.

---

## ⚡ **CORREÇÃO IMEDIATA (SSH)**

### **1. Conectar no servidor:**
```bash
ssh -p 7654 igor.franca@10.238.124.200
# Senha: 30102024@Real
```

### **2. Ir para o projeto:**
```bash
find /home /var/www /opt -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
cd [CAMINHO_ENCONTRADO]
```

### **3. Correção automática (copie e cole):**
```bash
cat > fix-proxy-error-now.sh << 'EOF'
#!/bin/bash
echo "🔧 CORRIGINDO ERRO HEADER_X_FORWARDED_ALL"
echo "========================================="

# Backup
sudo cp app/Providers/AppServiceProvider.php app/Providers/AppServiceProvider.php.backup

# Corrigir AppServiceProvider
cat > app/Providers/AppServiceProvider.php << 'EOFPHP'
<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Detectar proxy HTTPS
        if ($this->isRequestFromHttpsProxy()) {
            URL::forceScheme('https');
            request()->server->set('HTTPS', 'on');
            request()->server->set('SERVER_PORT', 443);
        }
        
        // Configurar proxies confiáveis - CORREÇÃO APLICADA
        if (env('TRUSTED_PROXIES')) {
            $proxies = explode(',', env('TRUSTED_PROXIES'));
            request()->setTrustedProxies($proxies, 
                \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
                \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX
            );
        }
    }
    
    private function isRequestFromHttpsProxy(): bool
    {
        return (
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] === '443')
        );
    }
}
EOFPHP

echo "✅ AppServiceProvider corrigido"

# Limpar cache
echo "🧹 Limpando cache..."
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan view:clear

# Testar
echo "🧪 Testando..."
if sudo ./vendor/bin/sail artisan --version >/dev/null 2>&1; then
    echo "✅ Erro corrigido! Artisan funcionando"
    
    # Testar aplicação
    status=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
    echo "📡 Status HTTP: $status"
    
    if [ "$status" = "200" ]; then
        echo "🎉 SUCESSO TOTAL! Aplicação funcionando"
        echo "🌐 Teste: https://contas.gravata.sei.pe.gov.br"
    else
        echo "⚠️  Artisan OK, mas aplicação ainda com problemas"
    fi
else
    echo "❌ Ainda há problemas. Verificar logs:"
    echo "sudo ./vendor/bin/sail logs --tail=20"
fi

echo ""
echo "✅ CORREÇÃO CONCLUÍDA!"
EOF

chmod +x fix-proxy-error-now.sh
./fix-proxy-error-now.sh
```

---

## 🔍 **VERIFICAÇÃO**

### **Testar se erro foi corrigido:**
```bash
sudo ./vendor/bin/sail artisan --version
```
**Deve mostrar:** `Laravel Framework X.X.X`

### **Testar aplicação:**
```bash
sudo ./vendor/bin/sail exec laravel.test curl -I http://localhost
```
**Deve retornar:** `HTTP/1.1 200 OK`

### **Testar proxy debug:**
```bash
sudo ./vendor/bin/sail artisan proxy:debug
```
**Deve mostrar:** configurações detalhadas sem erro

---

## 🎯 **O QUE FOI CORRIGIDO**

### **❌ Antes (erro):**
```php
request()->setTrustedProxies($proxies, \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL);
```

### **✅ Depois (correto):**
```php
request()->setTrustedProxies($proxies, 
    \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
    \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
    \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
    \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO |
    \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX
);
```

---

## 🚨 **SE AINDA NÃO FUNCIONAR**

### **1. Verificar logs:**
```bash
sudo ./vendor/bin/sail logs --tail=50
```

### **2. Verificar sintaxe PHP:**
```bash
sudo ./vendor/bin/sail exec laravel.test php -l app/Providers/AppServiceProvider.php
```

### **3. Reconstruir cache:**
```bash
sudo ./vendor/bin/sail artisan config:cache
sudo ./vendor/bin/sail artisan view:cache
```

### **4. Restart completo:**
```bash
sudo ./vendor/bin/sail down
sudo ./vendor/bin/sail up -d
```

---

## ✅ **RESULTADO ESPERADO**

Após a correção:
- ✅ **Comando artisan funciona** sem erro
- ✅ **Aplicação responde HTTP 200**
- ✅ **Proxy HTTPS detectado** corretamente
- ✅ **URLs geradas com HTTPS**
- 🌐 **`https://contas.gravata.sei.pe.gov.br` funciona**

---

## 📝 **RESUMO DOS COMANDOS**

```bash
# Conectar
ssh -p 7654 igor.franca@10.238.124.200

# Encontrar projeto
find /home -name "composer.json" -path "*ati-ldap*" 2>/dev/null

# Acessar e corrigir
cd /caminho/do/projeto
# [COPIAR SCRIPT DE CORREÇÃO ACIMA]
chmod +x fix-proxy-error-now.sh
./fix-proxy-error-now.sh

# Testar
https://contas.gravata.sei.pe.gov.br
```

**⏱️ Tempo estimado:** 2-3 minutos

**🎯 Status:** Erro identificado e correção pronta para aplicar! 