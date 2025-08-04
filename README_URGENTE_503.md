# 🚨 ERRO 503 - SOLUÇÃO URGENTE

## 📞 **ACESSO SSH**
```bash
ssh -p 7654 igor.franca@10.238.124.200
```
**Senha:** `30102024@Real`

---

## ⚡ **COMANDOS DIRETOS (Copie e Cole no SSH)**

### **1. Encontrar e acessar o projeto:**
```bash
find /home /var/www /opt -name "composer.json" -path "*ati-ldap-manager*" 2>/dev/null
```
```bash
cd [CAMINHO_ENCONTRADO_ACIMA]
```

### **2. Verificação rápida:**
```bash
ls -la composer.json .env docker-compose.yml
sudo docker ps
curl -I http://localhost
```

### **3. Correção automática:**
```bash
# Criar script de correção
cat > fix-now.sh << 'EOF'
#!/bin/bash
echo "🔧 CORRIGINDO ERRO 503..."
sudo ./vendor/bin/sail down 2>/dev/null
sudo docker system prune -f
[ ! -f ".env" ] && sudo cp .env.example .env
sudo mkdir -p storage/{app,framework,logs} bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo ./vendor/bin/sail up -d
sleep 20
sudo ./vendor/bin/sail artisan key:generate --force
sudo ./vendor/bin/sail artisan config:clear
sudo ./vendor/bin/sail artisan cache:clear
sudo ./vendor/bin/sail artisan migrate --force
status=$(sudo ./vendor/bin/sail exec laravel.test curl -s -o /dev/null -w "%{http_code}" http://localhost 2>/dev/null)
echo "Status: $status"
[ "$status" = "200" ] && echo "✅ FUNCIONANDO!" || echo "❌ Verificar logs: sudo ./vendor/bin/sail logs"
sudo ./vendor/bin/sail ps
EOF

chmod +x fix-now.sh
./fix-now.sh
```

---

## 🔍 **Se ainda não funcionar:**

### **Ver logs:**
```bash
sudo ./vendor/bin/sail logs -f
```

### **Status containers:**
```bash
sudo ./vendor/bin/sail ps
```

### **Reset completo (último recurso):**
```bash
sudo ./vendor/bin/sail down -v
sudo docker system prune -af
sudo ./vendor/bin/sail up -d
./fix-now.sh
```

---

## ✅ **Teste final:**
```bash
curl -I http://10.238.124.200
```

**Se retornar status 200, está funcionando!** 🎉

---

## 📋 **Resumo:**
1. SSH no servidor
2. Encontrar projeto (`find /home -name composer.json -path "*ati-ldap*"`)
3. Acessar diretório (`cd /caminho/encontrado`)
4. Executar correção (copiar script `fix-now.sh` acima)
5. Testar (`curl -I http://10.238.124.200`)

**Tempo estimado:** 2-5 minutos 