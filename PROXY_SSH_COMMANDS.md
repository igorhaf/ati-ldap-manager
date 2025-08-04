# 🌐 COMANDOS SSH DIRETOS - PROXY REVERSO HTTPS

## 📞 **CONECTAR NO SERVIDOR**
```bash
ssh -p 7654 igor.franca@10.238.124.200
```
**Senha:** `30102024@Real`

---

## ⚡ **CONFIGURAÇÃO COMPLETA (Copie e Cole)**

### **1. Encontrar e acessar projeto:**
```bash
find /home /var/www /opt -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
cd [CAMINHO_ENCONTRADO_ACIMA]
```

### **2. Configurar proxy HTTPS (script automático):**
```bash
cat > configure-proxy-now.sh << 'EOF'
#!/bin/bash
echo "🌐 CONFIGURANDO PROXY HTTPS - $(date)"
echo "======================================="

# Backup .env
[ -f ".env" ] && sudo cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
[ ! -f ".env" ] && sudo cp .env.example .env

# Configurar .env para HTTPS
sudo sed -i 's|^APP_URL=.*|APP_URL=https://contas.gravata.sei.pe.gov.br|' .env
sudo sed -i 's|^APP_ENV=.*|APP_ENV=production|' .env
sudo sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' .env

# Remover configurações antigas de proxy
sudo grep -v "^TRUSTED_PROXIES=" .env > .env.tmp && sudo mv .env.tmp .env
sudo grep -v "^FORCE_HTTPS=" .env > .env.tmp && sudo mv .env.tmp .env

# Adicionar configurações de proxy
echo "" | sudo tee -a .env
echo "# PROXY REVERSO HTTPS" | sudo tee -a .env
echo "TRUSTED_PROXIES=*" | sudo tee -a .env
echo "FORCE_HTTPS=true" | sudo tee -a .env

echo "✅ .env configurado para proxy HTTPS"

# Reiniciar aplicação
echo "🔄 Reiniciando aplicação..."
sudo ./vendor/bin/sail down 2>/dev/null
sudo docker system prune -f
sudo ./vendor/bin/sail up -d
sleep 20

# Configurar Laravel
echo "🔧 Configurando Laravel..."
sudo ./vendor/bin/sail artisan key:generate --force
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan view:clear
sudo ./vendor/bin/sail artisan migrate --force

# Testar
echo "🧪 Testando..."
status=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "Status local: $status"

echo ""
echo "✅ CONFIGURAÇÃO CONCLUÍDA!"
echo "========================="
echo "🌐 Teste agora: https://contas.gravata.sei.pe.gov.br"
echo "🔍 Debug: sudo ./vendor/bin/sail artisan proxy:debug"
echo "📋 Logs: sudo ./vendor/bin/sail logs -f"

# Mostrar configurações
echo ""
echo "📄 Configurações aplicadas:"
grep -E "^(APP_URL|APP_ENV|APP_DEBUG|TRUSTED_PROXIES)" .env
EOF

chmod +x configure-proxy-now.sh
./configure-proxy-now.sh
```

---

## 🔍 **COMANDOS DE VERIFICAÇÃO**

### **Verificar status:**
```bash
sudo ./vendor/bin/sail ps
```

### **Testar URLs:**
```bash
curl -I http://localhost
curl -I https://contas.gravata.sei.pe.gov.br
```

### **Debug proxy (comando personalizado):**
```bash
sudo ./vendor/bin/sail artisan proxy:debug
```

### **Verificar configurações:**
```bash
grep -E "^(APP_URL|APP_ENV|APP_DEBUG|TRUSTED_PROXIES)" .env
```

---

## 🔧 **SE AINDA NÃO FUNCIONAR**

### **Verificar logs:**
```bash
sudo ./vendor/bin/sail logs -f
```

### **Testar detecção HTTPS:**
```bash
sudo ./vendor/bin/sail artisan tinker
# No tinker:
dd(request()->isSecure(), request()->getScheme(), url('/'));
exit
```

### **Verificar headers:**
```bash
sudo ./vendor/bin/sail artisan tinker
# No tinker:
dd($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'não definido');
exit
```

### **Reset completo:**
```bash
sudo ./vendor/bin/sail down -v
sudo docker system prune -af
sudo ./vendor/bin/sail up -d
./configure-proxy-now.sh
```

---

## 🎯 **O QUE O SCRIPT FAZ**

1. ✅ **Configura APP_URL** = `https://contas.gravata.sei.pe.gov.br`
2. ✅ **Ativa modo produção** (APP_ENV=production, APP_DEBUG=false)
3. ✅ **Configura proxies confiáveis** (TRUSTED_PROXIES=*)
4. ✅ **Força HTTPS** (FORCE_HTTPS=true)
5. ✅ **Reinicia containers** limpos
6. ✅ **Gera nova APP_KEY**
7. ✅ **Limpa todos os caches**
8. ✅ **Executa migrações**
9. ✅ **Testa a aplicação**

---

## ⚠️ **IMPORTANTE**

O **AppServiceProvider** já foi configurado para:
- Detectar proxy HTTPS automaticamente
- Forçar scheme HTTPS quando detectado
- Configurar headers corretos

Se você precisar **recriar** o AppServiceProvider:

```bash
cat > app/Providers/AppServiceProvider.php << 'EOF'
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
        // Detectar proxy HTTPS e forçar scheme
        if ($this->isRequestFromHttpsProxy()) {
            URL::forceScheme('https');
            request()->server->set('HTTPS', 'on');
            request()->server->set('SERVER_PORT', 443);
        }
        
        if (env('TRUSTED_PROXIES')) {
            $proxies = explode(',', env('TRUSTED_PROXIES'));
            request()->setTrustedProxies($proxies, \Illuminate\Http\Request::HEADER_X_FORWARDED_ALL);
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
EOF
```

---

## 🎉 **RESULTADO ESPERADO**

Após executar o script, **teste no navegador**:
- 🌐 **URL:** `https://contas.gravata.sei.pe.gov.br`
- ✅ **Login deve funcionar** sem erro CSRF
- ✅ **Sessão deve persistir** entre páginas
- ✅ **URLs geradas** devem usar HTTPS
- ✅ **Assets (CSS/JS)** devem carregar via HTTPS

---

**🏁 Resumo dos comandos:**
```bash
ssh -p 7654 igor.franca@10.238.124.200
cd /caminho/do/projeto
# Copiar script configure-proxy-now.sh
chmod +x configure-proxy-now.sh
./configure-proxy-now.sh
# Testar: https://contas.gravata.sei.pe.gov.br
``` 