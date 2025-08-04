# 🚨 COMANDOS SSH PARA PRODUÇÃO - ERRO 503

## 📡 **Conectar no Servidor**

```bash
ssh -p 7654 igor.franca@10.238.124.200
```

**Senha:** `30102024@Real`
**Senha sudo:** `30102024@Real`

---

## ⚡ **CORREÇÃO RÁPIDA (Execute no servidor)**

### **1. Encontrar o Projeto**
```bash
# Encontrar o diretório do projeto
find /home -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
# OU
find /var/www -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
# OU
find /opt -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null

# Navegar para o diretório encontrado
cd /caminho/para/ati-ldap-manager
```

### **2. Copiar Scripts de Correção**
Crie os arquivos no servidor:

#### **A. Criar `production-fix.sh`**
```bash
cat > production-fix.sh << 'EOF'
#!/bin/bash

echo "🔧 CORREÇÃO PRODUÇÃO - ATI LDAP MANAGER"
echo "======================================="
echo "🌐 Servidor: $(hostname)"
echo "📅 Data/Hora: $(date)"
echo ""

# Verificar se está no diretório correto
if [ ! -f "composer.json" ]; then
    echo "❌ Execute este script no diretório do projeto!"
    echo "💡 Procure por: find /home -name composer.json -path '*ati-ldap-manager*'"
    exit 1
fi

echo "1️⃣ PARANDO CONTAINERS ANTIGOS"
echo "──────────────────────────────"
sudo ./vendor/bin/sail down 2>/dev/null || echo "⚠️  Sail down falhou"
sudo docker stop $(sudo docker ps -q) 2>/dev/null || echo "⚠️  Nenhum container para parar"

echo ""
echo "2️⃣ LIMPANDO SISTEMA DOCKER"
echo "───────────────────────────"
sudo docker system prune -f
sudo docker volume prune -f

echo ""
echo "3️⃣ VERIFICANDO DEPENDÊNCIAS"
echo "────────────────────────────"
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependências..."
    if command -v composer &> /dev/null; then
        sudo composer install --no-dev --optimize-autoloader
    else
        echo "❌ Composer não encontrado!"
        exit 1
    fi
else
    echo "✅ Dependências já instaladas"
fi

echo ""
echo "4️⃣ CONFIGURANDO .env"
echo "─────────────────────"
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        sudo cp .env.example .env
        echo "✅ .env criado"
    else
        echo "❌ .env.example não encontrado!"
        exit 1
    fi
fi

echo ""
echo "5️⃣ CORRIGINDO PERMISSÕES"
echo "─────────────────────────"
sudo mkdir -p storage/{app,framework,logs} bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || \
sudo chown -R $USER:$USER storage bootstrap/cache

echo ""
echo "6️⃣ INICIANDO CONTAINERS"
echo "────────────────────────"
sudo ./vendor/bin/sail up -d
sleep 15

echo ""
echo "7️⃣ CONFIGURANDO APLICAÇÃO"
echo "──────────────────────────"
sudo ./vendor/bin/sail artisan key:generate --force
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan view:clear
sudo ./vendor/bin/sail artisan migrate --force

echo ""
echo "8️⃣ TESTANDO"
echo "───────────"
status=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "Status: $status"

if [ "$status" = "200" ]; then
    echo "🎉 SUCESSO! Aplicação funcionando!"
else
    echo "⚠️  Ainda com problemas. Verificar logs:"
    echo "sudo ./vendor/bin/sail logs --tail=50"
fi

sudo ./vendor/bin/sail ps
EOF

chmod +x production-fix.sh
```

#### **B. Criar `quick-check.sh`**
```bash
cat > quick-check.sh << 'EOF'
#!/bin/bash

echo "🔍 VERIFICAÇÃO RÁPIDA"
echo "==================="

echo "📁 Diretório atual: $(pwd)"
echo "📂 Arquivos principais:"
ls -la composer.json .env docker-compose.yml 2>/dev/null || echo "❌ Arquivos não encontrados"

echo ""
echo "🐳 Docker:"
if sudo docker ps &> /dev/null; then
    echo "✅ Docker rodando"
    sudo docker ps --format "table {{.Names}}\t{{.Status}}"
else
    echo "❌ Docker com problemas"
fi

echo ""
echo "📄 .env:"
if [ -f ".env" ]; then
    echo "✅ .env existe"
    grep -E "^(APP_ENV|APP_DEBUG|APP_KEY|DB_CONNECTION)" .env
else
    echo "❌ .env NÃO EXISTE"
fi

echo ""
echo "📁 Permissões:"
ls -la storage/ bootstrap/cache/ 2>/dev/null || echo "❌ Diretórios não encontrados"

echo ""
echo "🌐 Teste HTTP:"
curl -s -o /dev/null -w "Status: %{http_code}\n" http://localhost 2>/dev/null || echo "❌ Falha na conexão"
EOF

chmod +x quick-check.sh
```

### **3. Executar Correção**
```bash
# Dar permissão e executar
chmod +x *.sh

# Verificação rápida primeiro
./quick-check.sh

# Se houver problemas, executar correção completa
./production-fix.sh
```

---

## 🔍 **DIAGNÓSTICO DETALHADO**

### **Verificar Status dos Containers**
```bash
# Status geral
sudo docker ps -a

# Containers do projeto
sudo docker ps -a | grep -E "(ati|ldap|postgres)"

# Status via Sail
sudo ./vendor/bin/sail ps
```

### **Verificar Logs**
```bash
# Logs em tempo real
sudo ./vendor/bin/sail logs -f

# Logs específicos
sudo ./vendor/bin/sail logs laravel.test --tail=50
sudo ./vendor/bin/sail logs pgsql --tail=20

# Logs do sistema
sudo tail -50 /var/log/syslog | grep -i error
```

### **Verificar Configuração**
```bash
# Ver .env
cat .env | grep -E "^(APP_|DB_|LDAP_)"

# Testar banco de dados
sudo ./vendor/bin/sail artisan migrate:status

# Testar LDAP
sudo ./vendor/bin/sail artisan tinker
# No tinker: App\Ldap\LdapUserModel::all();
```

### **Testar Conectividade**
```bash
# Teste local
curl -I http://localhost
curl -I http://127.0.0.1

# Teste por IP
curl -I http://10.238.124.200

# Dentro do container
sudo ./vendor/bin/sail exec laravel.test curl -I http://localhost
```

---

## 🚨 **COMANDOS DE EMERGÊNCIA**

### **Reset Completo dos Containers**
```bash
# CUIDADO: Apaga todos os dados dos containers
sudo ./vendor/bin/sail down -v
sudo docker system prune -af
sudo docker volume prune -f
sudo ./vendor/bin/sail up -d
./production-fix.sh
```

### **Reinstalar Dependências**
```bash
# Apagar vendor e reinstalar
sudo rm -rf vendor/
sudo composer install --no-dev --optimize-autoloader
```

### **Verificar Espaço em Disco**
```bash
# Espaço disponível
df -h

# Limpar logs antigos se necessário
sudo find /var/log -name "*.log" -type f -size +100M -delete
sudo docker system prune -af
```

---

## 📋 **CHECKLIST DE VERIFICAÇÃO**

Execute cada comando e marque se está OK:

```bash
# [ ] Docker rodando
sudo systemctl status docker

# [ ] Containers do projeto ativos  
sudo ./vendor/bin/sail ps

# [ ] Arquivo .env configurado
grep "^APP_KEY=" .env | grep -v "APP_KEY=$"

# [ ] Permissões corretas
ls -la storage/ bootstrap/cache/

# [ ] Banco conectado
sudo ./vendor/bin/sail artisan migrate:status

# [ ] HTTP respondendo 200
curl -s -o /dev/null -w "%{http_code}" http://localhost
```

---

## 🔧 **CONFIGURAÇÃO ESPECÍFICA PARA PRODUÇÃO**

### **Arquivo .env para Produção:**
```env
APP_NAME="ATI LDAP Manager"
APP_ENV=production
APP_KEY=base64:SUA_CHAVE_AQUI
APP_DEBUG=false
APP_TIMEZONE=America/Recife
APP_URL=http://10.238.124.200

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=atildaplogs
DB_USERNAME=ati
DB_PASSWORD=123456

LDAP_CONNECTION=default
LDAP_HOST=SEU_SERVIDOR_LDAP
LDAP_USERNAME=cn=admin,dc=exemplo,dc=com
LDAP_PASSWORD=SUA_SENHA_LDAP
LDAP_BASE_DN=dc=exemplo,dc=com
LDAP_PORT=389
LDAP_SSL=false
LDAP_TLS=false
```

### **Otimizações para Produção:**
```bash
# Depois que estiver funcionando
sudo ./vendor/bin/sail artisan config:cache
sudo ./vendor/bin/sail artisan route:cache
sudo ./vendor/bin/sail artisan view:cache
```

---

## 🆘 **SE NADA FUNCIONAR**

1. **Verificar se Docker está instalado:**
   ```bash
   docker --version
   sudo systemctl status docker
   ```

2. **Verificar se está no diretório correto:**
   ```bash
   pwd
   ls -la composer.json
   ```

3. **Procurar o projeto em outros locais:**
   ```bash
   find / -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
   ```

4. **Verificar logs do sistema:**
   ```bash
   sudo journalctl -u docker.service --no-pager | tail -50
   ```

5. **Executar em modo debug:**
   ```bash
   sudo ./vendor/bin/sail up
   # (sem -d para ver logs em tempo real)
   ```

---

**🎯 Resumo dos comandos principais:**
```bash
ssh -p 7654 igor.franca@10.238.124.200
cd /caminho/para/projeto
./quick-check.sh
./production-fix.sh
``` 