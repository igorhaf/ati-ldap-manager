# Correção do Erro 503 - Laravel Sail

## 🚨 **Problema**

Erro 503 (Service Unavailable) ao acessar a aplicação em ambiente Docker/Sail.

## 🔍 **Causas Mais Comuns**

1. **Containers não iniciados** ou em falha
2. **Arquivo .env** não configurado ou com problemas
3. **Chave da aplicação** (APP_KEY) não gerada
4. **Permissões** incorretas nos diretórios
5. **Cache** corrompido do Laravel
6. **Migrações** não executadas
7. **Configurações** de banco de dados incorretas

## ⚡ **Solução Rápida**

### **1. Dar Permissão aos Scripts**
```bash
chmod +x *.sh
```

### **2. Verificar Configurações**
```bash
./check-env.sh
```

### **3. Executar Correção Automática**
```bash
./fix-503.sh
```

### **4. Se Ainda Não Funcionar - Diagnóstico Completo**
```bash
./sail-diagnostics.sh
```

## 🛠️ **Correção Manual (Passo a Passo)**

### **Passo 1: Verificar Docker**
```bash
# Verificar se Docker está rodando
docker ps

# Se não estiver, iniciar Docker
sudo systemctl start docker
```

### **Passo 2: Verificar Containers**
```bash
# Ver status dos containers
./vendor/bin/sail ps

# Se não estiverem rodando, iniciar
./vendor/bin/sail up -d
```

### **Passo 3: Verificar Arquivo .env**
```bash
# Verificar se existe
ls -la .env

# Se não existir, copiar do exemplo
cp .env.example .env
```

### **Passo 4: Configurar Variáveis Essenciais**

Edite o arquivo `.env` e configure:

```env
# Aplicação
APP_NAME="ATI LDAP Manager"
APP_ENV=local
APP_KEY=                    # Será gerado automaticamente
APP_DEBUG=true
APP_TIMEZONE=America/Recife
APP_URL=http://localhost

# Banco de Dados
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=atildaplogs
DB_USERNAME=ati
DB_PASSWORD=123456

# LDAP (configure conforme seu ambiente)
LDAP_CONNECTION=default
LDAP_HOST=127.0.0.1
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin
LDAP_PORT=389
LDAP_BASE_DN=dc=example,dc=com
LDAP_TIMEOUT=5
LDAP_SSL=false
LDAP_TLS=false
```

### **Passo 5: Limpar Cache e Regenerar Chave**
```bash
# Limpar todos os caches
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan route:clear

# Gerar chave da aplicação
./vendor/bin/sail artisan key:generate
```

### **Passo 6: Corrigir Permissões**
```bash
# Corrigir permissões dos diretórios
./vendor/bin/sail exec laravel.test chmod -R 775 storage
./vendor/bin/sail exec laravel.test chmod -R 775 bootstrap/cache
./vendor/bin/sail exec laravel.test chown -R www-data:www-data storage bootstrap/cache
```

### **Passo 7: Executar Migrações**
```bash
# Verificar status das migrações
./vendor/bin/sail artisan migrate:status

# Executar migrações pendentes
./vendor/bin/sail artisan migrate
```

### **Passo 8: Testar a Aplicação**
```bash
# Verificar resposta HTTP
./vendor/bin/sail exec laravel.test curl -I http://localhost

# Acessar no navegador
echo "Acesse: http://localhost"
```

## 🔧 **Scripts de Diagnóstico**

### **check-env.sh**
- ✅ Verifica configurações do ambiente
- ✅ Valida arquivo .env
- ✅ Verifica estrutura de diretórios
- ✅ Testa instalação do Docker

### **fix-503.sh**
- 🔄 Reinicia containers
- 🧹 Limpa caches
- 🔑 Gera chave da aplicação
- 📁 Corrige permissões
- 📊 Executa migrações

### **sail-diagnostics.sh**
- 🔍 Diagnóstico completo
- 📋 Verifica logs detalhados
- 🌐 Testa conectividade
- 💾 Verifica espaço em disco
- 🔄 Analisa processos

## 📊 **Verificação de Status**

### **Containers Saudáveis**
```bash
./vendor/bin/sail ps
# Deve mostrar todos containers como "Up"
```

### **Aplicação Respondendo**
```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost
# Deve retornar: 200
```

### **Banco Conectado**
```bash
./vendor/bin/sail artisan migrate:status
# Deve listar migrações sem erro
```

### **LDAP Configurado**
```bash
./vendor/bin/sail artisan tinker
# Dentro do tinker:
# App\Ldap\LdapUserModel::all();
```

## 🚨 **Solução de Problemas Específicos**

### **Erro: "Connection refused"**
```bash
# Verificar se containers estão rodando
./vendor/bin/sail ps

# Reiniciar se necessário
./vendor/bin/sail down && ./vendor/bin/sail up -d
```

### **Erro: "Permission denied"**
```bash
# Corrigir permissões
sudo chown -R $USER:$USER .
./vendor/bin/sail exec laravel.test chmod -R 775 storage bootstrap/cache
```

### **Erro: "No application encryption key"**
```bash
# Gerar chave
./vendor/bin/sail artisan key:generate --force
```

### **Erro: "Database connection failed"**
```bash
# Verificar .env
grep "^DB_" .env

# Testar conexão
./vendor/bin/sail artisan migrate:status
```

### **Erro: "LDAP connection failed"**
```bash
# Verificar configurações LDAP no .env
grep "^LDAP_" .env

# Verificar se container OpenLDAP está rodando
./vendor/bin/sail ps | grep ldap
```

## 📝 **Logs para Análise**

### **Logs do Laravel**
```bash
# Logs em tempo real
./vendor/bin/sail logs -f

# Últimos logs
./vendor/bin/sail logs --tail=50
```

### **Logs Específicos do Container**
```bash
# Logs do aplicação
./vendor/bin/sail logs laravel.test

# Logs do banco
./vendor/bin/sail logs pgsql

# Logs do OpenLDAP
./vendor/bin/sail logs openldap
```

### **Logs de Erro do PHP**
```bash
# Dentro do container
./vendor/bin/sail exec laravel.test tail -f /var/log/php_errors.log
```

## ✅ **Checklist Final**

- [ ] Docker instalado e rodando
- [ ] Arquivo .env configurado
- [ ] APP_KEY gerada
- [ ] Containers iniciados (`sail ps`)
- [ ] Permissões corretas (775 em storage/)
- [ ] Cache limpo
- [ ] Migrações executadas
- [ ] Resposta HTTP 200
- [ ] Login funcionando

## 🆘 **Se Nada Funcionar**

1. **Backup dos dados importantes**
2. **Reconstruir containers:**
   ```bash
   ./vendor/bin/sail down -v
   ./vendor/bin/sail build --no-cache
   ./vendor/bin/sail up -d
   ```
3. **Restaurar dados**
4. **Executar `./fix-503.sh` novamente**

---

**💡 Dica**: Mantenha os logs abertos durante os testes:
```bash
./vendor/bin/sail logs -f
``` 