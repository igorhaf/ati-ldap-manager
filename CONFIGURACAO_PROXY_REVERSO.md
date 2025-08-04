# 🌐 CONFIGURAÇÃO PROXY REVERSO HTTPS

## 🚨 **PROBLEMA IDENTIFICADO**

A aplicação Laravel está rodando **atrás de um proxy reverso HTTPS** em:
- **Proxy HTTPS:** `https://contas.gravata.sei.pe.gov.br`
- **Laravel interno:** `http://localhost` (via Docker/Sail)

**Resultado:** URLs quebradas, sessões perdidas, CSRF inválido, cookies não funcionam.

---

## ✅ **SOLUÇÃO IMPLEMENTADA**

### **1. AppServiceProvider Configurado**
Arquivo: `app/Providers/AppServiceProvider.php`

**O que faz:**
- ✅ **Detecta proxy HTTPS** automaticamente via headers
- ✅ **Força scheme HTTPS** no Laravel
- ✅ **Configura headers** para proxies confiáveis
- ✅ **Funciona com múltiplos tipos** de proxy (Nginx, Apache, Cloudflare, AWS)

**Headers detectados:**
- `X-Forwarded-Proto: https`
- `X-Forwarded-SSL: on`
- `X-Forwarded-Port: 443`
- `CF-Visitor` (Cloudflare)
- `X-Real-IP` (Nginx)

### **2. Configurações .env**
```env
APP_URL=https://contas.gravata.sei.pe.gov.br
APP_ENV=production
APP_DEBUG=false
TRUSTED_PROXIES=*
FORCE_HTTPS=true
PROXY_HEADERS=true
```

### **3. Script de Configuração**
Arquivo: `configure-proxy.sh` - Automatiza toda a configuração.

### **4. Comando de Debug**
Arquivo: `app/Console/Commands/ProxyDebug.php`
```bash
php artisan proxy:debug
```

---

## 🚀 **COMO CONFIGURAR (Passo a Passo)**

### **Opção 1: Script Automático (Recomendado)**

#### **No servidor de produção:**
```bash
# 1. Conectar SSH
ssh -p 7654 igor.franca@10.238.124.200
# Senha: 30102024@Real

# 2. Encontrar projeto
find /home /var/www /opt -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null

# 3. Navegar para o diretório
cd /caminho/do/projeto

# 4. Criar e executar script
cat > configure-proxy.sh << 'EOF'
[CONTEÚDO DO SCRIPT - copie do arquivo configure-proxy.sh]
EOF

chmod +x configure-proxy.sh
./configure-proxy.sh
```

### **Opção 2: Configuração Manual**

#### **1. Configurar .env**
```bash
# Backup
sudo cp .env .env.backup

# Configurar URL
sudo sed -i 's|^APP_URL=.*|APP_URL=https://contas.gravata.sei.pe.gov.br|' .env

# Configurar produção
sudo sed -i 's|^APP_ENV=.*|APP_ENV=production|' .env
sudo sed -i 's|^APP_DEBUG=.*|APP_DEBUG=false|' .env

# Adicionar configurações proxy
echo "TRUSTED_PROXIES=*" | sudo tee -a .env
echo "FORCE_HTTPS=true" | sudo tee -a .env
```

#### **2. Reiniciar aplicação**
```bash
sudo ./vendor/bin/sail down
sudo ./vendor/bin/sail up -d
sudo ./vendor/bin/sail artisan key:generate --force
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
```

---

## 🔍 **VERIFICAÇÃO E DEBUG**

### **1. Teste Básico**
```bash
# Status HTTP local
curl -I http://localhost

# Status HTTP pelo proxy
curl -I https://contas.gravata.sei.pe.gov.br
```

### **2. Debug Detalhado**
```bash
# Command personalizado
sudo ./vendor/bin/sail artisan proxy:debug

# Verificar headers manualmente
sudo ./vendor/bin/sail artisan tinker
# dd($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'não definido');
```

### **3. Teste HTTPS Detection**
```bash
sudo ./vendor/bin/sail artisan tinker
# dd(request()->isSecure(), request()->getScheme(), url('/'));
```

### **4. Verificar URLs Geradas**
```bash
sudo ./vendor/bin/sail artisan tinker
# dd(route('login'), asset('css/app.css'), csrf_token());
```

---

## 🛠️ **CONFIGURAÇÃO DO PROXY (Para Administrador de Rede)**

### **Headers Necessários**

O proxy precisa enviar estes headers para o Laravel:

```nginx
# Nginx
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-SSL on;
proxy_set_header X-Forwarded-Port 443;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header Host $host;
```

```apache
# Apache
ProxyPreserveHost On
ProxyAddHeaders On

RequestHeader set X-Forwarded-Proto "https"
RequestHeader set X-Forwarded-SSL "on"
RequestHeader set X-Forwarded-Port "443"
```

### **Exemplo de Configuração Nginx**
```nginx
server {
    listen 443 ssl;
    server_name contas.gravata.sei.pe.gov.br;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        proxy_pass http://10.238.124.200:80;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-SSL on;
        proxy_set_header X-Forwarded-Port 443;
        
        # Timeouts
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
        
        # Buffer settings
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
    }
}
```

---

## 🔒 **SEGURANÇA E CONSIDERAÇÕES**

### **1. Trusted Proxies**
```env
# Restritivo (mais seguro)
TRUSTED_PROXIES=192.168.1.100,10.0.0.0/8

# Permissivo (desenvolvimento)
TRUSTED_PROXIES=*
```

### **2. Headers de Segurança**
O proxy deve também configurar:
```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
```

### **3. Configurações de Sessão**
```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

---

## 🚨 **PROBLEMAS COMUNS E SOLUÇÕES**

### **1. "CSRF token mismatch"**
**Causa:** Laravel não detecta HTTPS
**Solução:**
```bash
# Verificar detecção
sudo ./vendor/bin/sail artisan proxy:debug

# Forçar HTTPS
sudo ./vendor/bin/sail artisan tinker
# dd(request()->isSecure()); // deve ser true
```

### **2. "Session expired"**
**Causa:** Cookies sendo enviados como HTTP
**Solução:**
```env
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.gravata.sei.pe.gov.br
```

### **3. "Mixed content warnings"**
**Causa:** Assets carregados via HTTP
**Solução:**
```bash
# Verificar URLs de assets
sudo ./vendor/bin/sail artisan tinker
# dd(asset('css/app.css')); // deve começar com https://
```

### **4. "Login redirect loops"**
**Causa:** Middleware detectando requisição como insegura
**Solução:**
```bash
# Verificar middleware
sudo ./vendor/bin/sail artisan route:list | grep auth
```

---

## 📊 **STATUS ESPERADO APÓS CONFIGURAÇÃO**

### **✅ Funcionando Corretamente:**
```bash
$ sudo ./vendor/bin/sail artisan proxy:debug

📄 CONFIGURAÇÕES .env:
APP_URL: https://contas.gravata.sei.pe.gov.br
APP_ENV: production
APP_DEBUG: false

🌐 HEADERS DE PROXY:
X-Forwarded-Proto: https
X-Forwarded-SSL: on
X-Forwarded-Port: 443

⚙️ STATUS DA APLICAÇÃO:
Scheme detectado: https
É HTTPS: SIM
URL raiz: https://contas.gravata.sei.pe.gov.br

🔍 DETECÇÃO DE PROXY:
Proxy HTTPS detectado: SIM
✅ Proxy HTTPS está sendo detectado corretamente
```

### **🌐 URLs Corretas:**
- Login: `https://contas.gravata.sei.pe.gov.br/login`
- Assets: `https://contas.gravata.sei.pe.gov.br/css/app.css`
- API: `https://contas.gravata.sei.pe.gov.br/api/ldap/users`

---

## 📋 **CHECKLIST FINAL**

- [ ] AppServiceProvider configurado para detectar proxy
- [ ] .env com APP_URL=https://contas.gravata.sei.pe.gov.br
- [ ] .env com APP_ENV=production e APP_DEBUG=false
- [ ] TRUSTED_PROXIES configurado
- [ ] Cache limpo (config, view, route)
- [ ] APP_KEY gerada
- [ ] Containers reiniciados
- [ ] Teste: `curl -I https://contas.gravata.sei.pe.gov.br` retorna 200
- [ ] Teste: Login funciona sem erro CSRF
- [ ] Teste: Sessão persiste entre páginas
- [ ] Comando `proxy:debug` mostra tudo OK

---

## 🆘 **COMANDOS DE EMERGÊNCIA**

### **Voltar configuração anterior:**
```bash
sudo cp .env.backup .env
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
```

### **Debug rápido:**
```bash
sudo ./vendor/bin/sail artisan proxy:debug
sudo ./vendor/bin/sail logs --tail=50
curl -v https://contas.gravata.sei.pe.gov.br
```

### **Reset completo:**
```bash
sudo ./vendor/bin/sail down -v
sudo docker system prune -f
./configure-proxy.sh
```

---

**🎯 RESULTADO:** Laravel funcionando perfeitamente atrás do proxy HTTPS com sessões, CSRF e cookies funcionando corretamente! 