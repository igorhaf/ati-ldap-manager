# Troubleshooting: Falha de Conexão LDAP em Produção

## 🔍 **Problema**

Falha ao conectar no LDAP em produção com erro "❌ Falha na conexão LDAP".

## 🧪 **Comandos de Diagnóstico**

### **0. Teste da Aplicação Básica (PRIMEIRO)**
```bash
# Verificar se Laravel e configurações estão funcionando
php artisan test:basic-app
```

### **1. Teste Rápido**
```bash
# Teste básico de conectividade
php artisan quick:ldap-test
```

### **2. Teste da Correção do Container**
```bash
# Verificar se a correção do LdapRecord Container funcionou
php artisan test:container-fix
```

### **3. Teste Simples da Estrutura**
```bash
# Teste robusto e simplificado (recomendado)
php artisan test:simple-structure
```

### **4. Teste Completo**
```bash
# Diagnóstico detalhado
php artisan test:ldap-connection --detailed
```

### **5. Debug da Estrutura**
```bash
# Verificar estrutura após conexão (avançado)
php artisan debug:ldap-structure
```

### **6. Teste LdapRecord Específico**
```bash
# Testar especificamente LdapRecord/Laravel (avançado)
php artisan test:ldap-record
```

## ⚙️ **Configurações de Produção**

### **1. Variáveis de Ambiente (.env)**

```env
# Configuração básica
LDAP_HOST=10.238.124.3
LDAP_PORT=389
LDAP_USERNAME=cn=admin,dc=sei,dc=pe,dc=gov,dc=br
LDAP_PASSWORD=sua_senha_aqui
LDAP_BASE_DN=dc=sei,dc=pe,dc=gov,dc=br

# Timeouts (aumente se a rede for lenta)
LDAP_TIMEOUT=15
LDAP_NETWORK_TIMEOUT=15

# SSL/TLS (se necessário)
LDAP_SSL=false
LDAP_TLS=false
LDAP_TLS_REQUIRE_CERT=never

# Outras opções
LDAP_FOLLOW_REFERRALS=false
LDAP_LOGGING=true
```

### **2. Para LDAPS (SSL)**
```env
LDAP_HOST=10.238.124.3
LDAP_PORT=636
LDAP_SSL=true
LDAP_TLS=false
LDAP_TLS_REQUIRE_CERT=never  # Use 'demand' apenas se certificados estiverem corretos
```

### **3. Para LDAP com StartTLS**
```env
LDAP_HOST=10.238.124.3
LDAP_PORT=389
LDAP_SSL=false
LDAP_TLS=true
LDAP_TLS_REQUIRE_CERT=never
```

## 🔧 **Problemas Comuns e Soluções**

### **1. Erro: "Connection refused"**
```bash
❌ TCP falhou: Connection refused
```

**Possíveis Causas:**
- Firewall bloqueando a porta
- Serviço LDAP não rodando
- IP/porta incorretos

**Soluções:**
```bash
# Teste de conectividade TCP manual
telnet 10.238.124.3 389

# Ou com netcat
nc -zv 10.238.124.3 389

# Verificar se a porta está aberta
nmap -p 389 10.238.124.3
```

**Ações:**
- Contate o administrador de rede para liberar a porta 389
- Verifique se o IP está correto
- Confirme se o serviço LDAP está rodando no servidor

### **2. Erro: "Connection timeout"**
```bash
❌ TCP falhou: Connection timed out
```

**Soluções:**
```env
# Aumente os timeouts
LDAP_TIMEOUT=30
LDAP_NETWORK_TIMEOUT=30
```

### **3. Erro: "Invalid credentials"**
```bash
❌ Autenticação falhou: Invalid credentials
```

**Verificações:**
1. Username está no formato completo: `cn=admin,dc=sei,dc=pe,dc=gov,dc=br`
2. Senha está correta
3. Usuário existe e tem permissão de bind

**Teste Manual:**
```bash
# Teste com ldapsearch (se disponível)
ldapsearch -H ldap://10.238.124.3:389 \
  -D "cn=admin,dc=sei,dc=pe,dc=gov,dc=br" \
  -W \
  -b "dc=sei,dc=pe,dc=gov,dc=br" \
  "(objectClass=*)" \
  dn
```

### **4. Erro: "Can't contact LDAP server"**
```bash
❌ ldap_connect falhou
```

**Soluções:**
1. **Verificar SSL/TLS:**
   ```env
   LDAP_SSL=false
   LDAP_TLS=false
   ```

2. **Desabilitar verificação de certificado:**
   ```env
   LDAP_TLS_REQUIRE_CERT=never
   ```

3. **Protocolo LDAP:**
   - Força protocolo versão 3 (já configurado automaticamente)

### **5. Erro: "Argument #1 ($connection) must be of type LdapRecord\Connection, array given"**
```bash
❌ ConnectionManager::addConnection(): Argument #1 ($connection) must be of type LdapRecord\Connection, array given
```

**Causa:** Tentativa de passar array de configuração diretamente para `Container::addConnection()`.

**Solução:** Criar objeto `Connection` primeiro:
```php
// ❌ ERRADO
Container::addConnection($config, 'default');

// ✅ CORRETO
$connection = new Connection($config);
Container::addConnection($connection, 'default');
```

**Teste:** Execute `php artisan test:container-fix` para verificar se foi corrigido.

### **6. Erro: "Call to a member function count() on array"**
```bash
❌ Call to a member function count() on array
```

**Causa:** Alguns métodos LDAP retornam arrays ao invés de Collections.

**Solução:** Usar verificação de tipo:
```php
// ❌ ERRADO
$count = $results->count();

// ✅ CORRETO
$count = is_array($results) ? count($results) : $results->count();
```

**Teste:** Execute `php artisan test:simple-structure` para verificar se foi corrigido.

### **7. Erro: "Target class [ensure-ldap-record] does not exist"**
```bash
❌ Target class [ensure-ldap-record] does not exist
```

**Causa:** Middleware não registrado corretamente ou conflito de versão Laravel.

**Solução:** Middleware foi removido e substituído por inicialização automática no `AppServiceProvider`.

**Verificação:**
```bash
# Verificar se foi removido
grep -r "ensure-ldap-record" app/ routes/

# Limpar cache
php artisan config:clear
php artisan route:clear
```

**Teste:** Execute `php artisan test:basic-app` para verificar.

### **8. Erro: "SyntaxError: Unexpected token '<', "<!DOCTYPE ""**
```bash
❌ SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON
```

**Causa:** Aplicação retornando HTML (página de erro) ao invés de JSON.

**Possíveis Causas:**
- Erro 500 interno
- Middleware falhando
- Rota não encontrada
- Problema de autenticação

**Soluções:**
1. **Verificar logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Testar API diretamente:**
   ```bash
   curl -H "Accept: application/json" http://localhost/api/ldap/users
   ```

3. **Limpar cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

**Teste:** Execute `php artisan test:basic-app` primeiro.

### **9. Problemas de Certificado SSL**
```bash
❌ Falha na conexão SSL/TLS
```

**Soluções:**
1. **Modo desenvolvimento (não seguro):**
   ```env
   LDAP_TLS_REQUIRE_CERT=never
   ```

2. **Certificado auto-assinado:**
   ```env
   LDAP_TLS_REQUIRE_CERT=allow
   ```

3. **Produção segura:**
   ```env
   LDAP_TLS_REQUIRE_CERT=demand
   # Certifique-se que o certificado do servidor está válido
   ```

## 🌐 **Testes de Rede Manuais**

### **1. Teste de Ping**
```bash
ping -c 4 10.238.124.3
```

### **2. Teste de Porta TCP**
```bash
telnet 10.238.124.3 389
# Ou
nc -zv 10.238.124.3 389
```

### **3. Teste de DNS (se usando hostname)**
```bash
nslookup seu-servidor-ldap.com
```

### **4. Teste de Firewall**
```bash
# Teste de conectividade com timeout
timeout 10 bash -c "</dev/tcp/10.238.124.3/389"
echo $?  # 0 = sucesso, 1 = falha
```

## 🔒 **Configurações de Firewall**

### **Portas LDAP Comuns:**
- **389**: LDAP simples
- **636**: LDAPS (SSL)
- **3268**: Global Catalog (AD)
- **3269**: Global Catalog SSL (AD)

### **Liberação no Firewall:**
```bash
# iptables (se você tem acesso)
sudo iptables -A OUTPUT -p tcp --dport 389 -d 10.238.124.3 -j ACCEPT

# firewalld
sudo firewall-cmd --add-port=389/tcp --permanent
sudo firewall-cmd --reload
```

## 🐳 **Problemas Específicos do Docker**

Se usando Docker, verifique:

### **1. Rede do Container**
```bash
# Verificar se o container consegue acessar a rede externa
docker exec -it seu-container ping 10.238.124.3
```

### **2. DNS no Container**
```bash
# Verificar resolução DNS
docker exec -it seu-container nslookup 10.238.124.3
```

### **3. Configuração de Rede Docker**
```yaml
# docker-compose.yml
services:
  app:
    networks:
      - ldap-network
    extra_hosts:
      - "ldap-server:10.238.124.3"

networks:
  ldap-network:
    driver: bridge
```

## 📊 **Checklist de Verificação**

### **Conectividade Básica**
- [ ] Ping para o servidor responde
- [ ] Porta TCP 389 está acessível
- [ ] Não há firewall bloqueando

### **Configuração LDAP**
- [ ] LDAP_HOST está correto
- [ ] LDAP_PORT está correto (389 ou 636)
- [ ] LDAP_USERNAME está no formato DN completo
- [ ] LDAP_PASSWORD está correto
- [ ] LDAP_BASE_DN está correto

### **SSL/TLS**
- [ ] Se não usar SSL/TLS: LDAP_SSL=false e LDAP_TLS=false
- [ ] Se usar LDAPS: porta 636 e LDAP_SSL=true
- [ ] Se usar StartTLS: porta 389 e LDAP_TLS=true
- [ ] Certificados válidos ou LDAP_TLS_REQUIRE_CERT=never

### **Timeouts**
- [ ] LDAP_TIMEOUT adequado (10-30 segundos)
- [ ] LDAP_NETWORK_TIMEOUT adequado

## 🚨 **Cenários de Emergência**

### **1. Bypass temporário de SSL (NÃO para produção final)**
```env
LDAP_SSL=false
LDAP_TLS=false
LDAP_TLS_REQUIRE_CERT=never
```

### **2. Aumento extremo de timeout**
```env
LDAP_TIMEOUT=60
LDAP_NETWORK_TIMEOUT=60
```

### **3. Teste com usuário diferente**
```env
# Se admin não funcionar, teste com outro usuário
LDAP_USERNAME=cn=readonly,dc=sei,dc=pe,dc=gov,dc=br
```

## 📝 **Logs Úteis**

### **1. Logs do Laravel**
```bash
tail -f storage/logs/laravel.log | grep -i ldap
```

### **2. Logs do Sistema**
```bash
# Ubuntu/Debian
sudo tail -f /var/log/syslog | grep ldap

# CentOS/RHEL
sudo tail -f /var/log/messages | grep ldap
```

### **3. Debug LDAP no PHP**
```php
// Adicionar temporariamente no código
ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);
```

## 🔧 **Ferramentas Úteis**

### **1. ldapsearch (cliente LDAP)**
```bash
# Instalar
sudo apt-get install ldap-utils  # Ubuntu/Debian
sudo yum install openldap-clients  # CentOS/RHEL

# Usar
ldapsearch -H ldap://10.238.124.3:389 \
  -D "cn=admin,dc=sei,dc=pe,dc=gov,dc=br" \
  -W -b "dc=sei,dc=pe,dc=gov,dc=br" "(objectClass=*)"
```

### **2. nmap (verificar portas)**
```bash
nmap -p 389,636 10.238.124.3
```

### **3. telnet/nc (teste de conectividade)**
```bash
telnet 10.238.124.3 389
nc -zv 10.238.124.3 389
```

---

## 📞 **Contatos para Escalação**

Se nenhuma solução funcionar:

1. **Administrador de Rede**: Verificar firewall e conectividade
2. **Administrador LDAP**: Verificar logs do servidor LDAP
3. **Administrador de Sistema**: Verificar logs do servidor de aplicação

---

**Data:** 2024  
**Testado em:** Produção SEI/PE  
**Status:** ✅ Documentação Completa 