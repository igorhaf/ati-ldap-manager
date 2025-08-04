# 🚨 SOLUÇÃO RÁPIDA PARA ERRO 503

## ⚡ **Comandos Rápidos (Execute em Ordem)**

### **1. Verificar Ambiente**
```bash
./check-env.sh
```

### **2. Correção Automática**
```bash
./fix-503.sh
```

### **3. Se Ainda Não Funcionar**
```bash
./sail-diagnostics.sh
```

---

## 🎯 **Para Pressa - Execute Isto:**

```bash
# Comando único para corrigir a maioria dos problemas
chmod +x *.sh && ./fix-503.sh
```

---

## 📋 **Checklist Rápido**

Execute estes comandos um por um:

```bash
# 1. Verificar se Docker está rodando
docker ps

# 2. Verificar containers do projeto
./vendor/bin/sail ps

# 3. Se containers não estão UP, iniciar
./vendor/bin/sail up -d

# 4. Verificar se .env existe
ls -la .env

# 5. Se não existir, copiar
cp .env.example .env

# 6. Limpar cache e gerar chave
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan key:generate --force

# 7. Corrigir permissões
./vendor/bin/sail exec laravel.test chmod -R 775 storage bootstrap/cache

# 8. Executar migrações
./vendor/bin/sail artisan migrate

# 9. Testar aplicação
curl -I http://localhost
```

---

## 🔍 **Verificação Final**

```bash
# Deve retornar status HTTP 200
curl -s -o /dev/null -w "%{http_code}" http://localhost
```

Se retornar **200**, a aplicação está funcionando! ✅

Se retornar **503** ou outro erro, veja o arquivo `CORRECAO_ERRO_503.md` para diagnóstico completo.

---

## 📞 **Comandos de Emergência**

Se nada funcionar, reset completo:

```bash
# CUIDADO: Vai apagar todos os dados!
./vendor/bin/sail down -v
docker system prune -f
./vendor/bin/sail up -d
./fix-503.sh
```

---

## 📊 **Status Saudável**

Quando tudo estiver funcionando:

```bash
$ ./vendor/bin/sail ps
NAME                          COMMAND                  STATUS
ati-ldap-manager-laravel.test   "start-container"       Up (healthy)
ati-ldap-manager-pgsql-1        "docker-entrypoint..."  Up
ati-ldap-manager-openldap-1     "/container/tool/run"   Up

$ curl -s -o /dev/null -w "%{http_code}" http://localhost
200
```

---

**🎯 TL;DR**: Execute `./fix-503.sh` e teste `http://localhost` 