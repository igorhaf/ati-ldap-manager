# Configuração de Ambientes

## 🏠 **Desenvolvimento Local**

### Serviços Docker:
**Base (docker-compose.yml):**
- **Laravel App** - `laravel.test` (porta 80)
- **PostgreSQL** - `postgres` (porta 5432)

**Desenvolvimento (docker-compose.override.yml):**
- **LDAP** - `ldap` (porta 389) - **LOCAL**
- **phpLDAPadmin** - `phpldapadmin` (porta 8080) - **LOCAL**
- **Mailpit** - `mailpit` (porta 1025 SMTP, 8025 Web) - **LOCAL**

### Configuração `.env.local`:
```env
# LDAP Local
LDAP_HOST=ldap
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin
LDAP_BASE_DN=dc=example,dc=com

# Email Local (Mailpit)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

# Database Local
DB_HOST=postgres
DB_DATABASE=atildaplogs
DB_USERNAME=ati
DB_PASSWORD=123456
```

### Comandos para desenvolvimento:
```bash
# 1. Copiar configuração local
cp .env.local .env

# 2. Rodar com override (LDAP + phpLDAPadmin + Mailpit)
docker-compose up -d

# Acessar serviços:
# - App: http://localhost
# - phpLDAPadmin: http://localhost:8080
# - Mailpit: http://localhost:8025
```

---

## 🚀 **Produção**

### Serviços:
**Base (docker-compose.yml):**
- **Laravel App** - Servidor de produção
- **PostgreSQL** - Servidor de produção

**Serviços Remotos:**
- **LDAP** - Servidor remoto (200.238.112.200)
- **Email** - Servidor SMTP remoto (200.238.112.200:25)

### Configuração `.env.prod`:
```env
# LDAP Remoto
LDAP_HOST=200.238.112.200
LDAP_USERNAME=cn=admin,dc=sei,dc=pe,dc=gov,dc=br
LDAP_PASSWORD=admin
LDAP_BASE_DN=dc=sei,dc=pe,dc=gov,dc=br

# Email Remoto
MAIL_MAILER=smtp
MAIL_HOST=200.238.112.200
MAIL_PORT=25
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

# Database Produção
DB_HOST=127.0.0.1
DB_DATABASE=ati_ldap_manager
DB_USERNAME=postgres
DB_PASSWORD=password
```

### Comandos para produção:
```bash
# 1. Copiar configuração de produção
cp .env.prod .env

# 2. Rodar SEM override (apenas base)
docker-compose -f docker-compose.yml up -d
```

---

## 🔄 **Como alternar entre ambientes:**

### Para desenvolvimento:
```bash
cp .env.local .env
docker-compose up -d
```

### Para produção:
```bash
cp .env.prod .env
docker-compose -f docker-compose.yml up -d
```

---

## 📋 **Resumo dos Serviços:**

| Serviço | Desenvolvimento | Produção |
|---------|----------------|----------|
| **Laravel** | Docker local | Servidor |
| **PostgreSQL** | Docker local | Servidor |
| **LDAP** | Docker local | Remoto |
| **phpLDAPadmin** | Docker local | N/A |
| **Email** | Mailpit local | SMTP remoto |
