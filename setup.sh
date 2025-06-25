#!/bin/bash

echo "🚀 Configurando o Gerenciador LDAP..."

# Verificar se o Docker está instalado
if ! command -v docker &> /dev/null; then
    echo "❌ Docker não está instalado. Por favor, instale o Docker primeiro."
    exit 1
fi

# Verificar se o Docker Compose está instalado
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose não está instalado. Por favor, instale o Docker Compose primeiro."
    exit 1
fi

echo "📦 Instalando dependências PHP..."
composer install

echo "📦 Instalando dependências Node.js..."
npm install

echo "🔨 Compilando assets..."
npm run build

echo "🐳 Iniciando serviços Docker..."
docker-compose up -d

echo "⏳ Aguardando serviços iniciarem..."
sleep 10

echo "🗄️ Executando migrações..."
php artisan migrate --force

echo "✅ Configuração concluída!"
echo ""
echo "🌐 URLs de acesso:"
echo "   - Gerenciador LDAP: http://localhost:8000/ldap-manager"
echo "   - phpLDAPadmin: http://localhost:8080"
echo "   - PostgreSQL: localhost:5432"
echo ""
echo "🔑 Credenciais LDAP:"
echo "   - DN: cn=admin,dc=example,dc=com"
echo "   - Senha: admin"
echo ""
echo "🔑 Credenciais PostgreSQL:"
echo "   - Database: atildaplogs"
echo "   - Usuário: ati"
echo "   - Senha: 123456"
echo ""
echo "🚀 Para iniciar o servidor Laravel:"
echo "   php artisan serve"
echo ""
echo "📝 Para parar os serviços Docker:"
echo "   docker-compose down" 