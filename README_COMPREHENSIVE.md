# LDAP User Management System

A comprehensive Laravel-based LDAP user and organizational unit management system with role-based access control, LDIF import/export capabilities, and a modern Vue.js frontend.

## 🚀 Features

- **LDAP User Management**: Full CRUD operations for LDAP users
- **Organizational Unit Management**: Create, update, and manage OUs
- **Role-Based Access Control**: Three-tier permission system (Root, OU Admin, User)
- **LDIF Operations**: Import/export users and OUs via LDIF
- **Password Management**: Secure password handling with SSHA hashing
- **Operation Logging**: Comprehensive audit trail for all operations
- **Multi-OU Support**: Users can belong to multiple organizational units
- **Modern UI**: Responsive Vue.js frontend with Tailwind CSS
- **Security**: CSRF protection, input validation, and LDAP injection prevention

## 🏗️ Architecture

### Backend (Laravel 12)
- **Controllers**: Handle HTTP requests and business logic
- **Services**: Business logic separation and LDIF processing
- **Models**: LDAP and database models
- **Middleware**: Authentication and authorization
- **Traits**: Reusable functionality for access control

### Frontend (Vue.js 3)
- **Single Page Application**: Modern reactive interface
- **Tailwind CSS**: Utility-first CSS framework
- **Responsive Design**: Mobile-first approach
- **Role-Based UI**: Dynamic interface based on user permissions

### LDAP Integration
- **LdapRecord**: Laravel LDAP integration package
- **OpenLDAP Support**: Compatible with OpenLDAP servers
- **Schema Flexibility**: Adaptable to various LDAP schemas

## 📋 Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 12.0 or higher
- **OpenLDAP**: Server with appropriate schema
- **Database**: MySQL/PostgreSQL/SQLite for operation logs
- **Node.js**: 18+ for frontend asset compilation

## 🛠️ Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd ldap-user-management
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Install Node.js Dependencies
```bash
npm install
```

### 4. Environment Configuration
Copy the environment file and configure your settings:
```bash
cp .env.example .env
```

Configure the following environment variables:
```env
# LDAP Configuration
LDAP_HOST=ldap.example.com
LDAP_PORT=389
LDAP_BASE_DN=dc=example,dc=com
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin_password

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ldap_management
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Application Configuration
APP_NAME="LDAP User Management"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Run Database Migrations
```bash
php artisan migrate
```

### 7. Build Frontend Assets
```bash
npm run build
```

### 8. Set Permissions
```bash
chmod -R 775 storage bootstrap/cache
```

## 🚀 Quick Start

### 1. Start the Development Server
```bash
# Start Laravel development server
php artisan serve

# Start Vite development server (in another terminal)
npm run dev
```

### 2. Access the Application
- **Main Application**: http://localhost:8000/ldap-manager
- **Login Page**: http://localhost:8000/login
- **API Endpoints**: http://localhost:8000/api/ldap/*

### 3. First Login
Use your LDAP credentials to log in. The system will automatically determine your role based on your LDAP attributes.

## 🔐 User Roles & Permissions

### Root User (`ROLE_ROOT`)
- **Full System Access**: Can manage all OUs and users
- **LDIF Operations**: Can import/export LDIF files
- **OU Management**: Can create, update, and delete OUs
- **Domain Restriction**: Access limited to specific domains

### OU Admin (`ROLE_OU_ADMIN`)
- **OU-Specific Access**: Can manage users within their assigned OU
- **User Management**: Full CRUD operations for OU users
- **Log Access**: Can view operation logs for their OU
- **No OU Management**: Cannot create or modify OUs

### Regular User (`ROLE_USER`)
- **Self-Access Only**: Can only access their own profile
- **Password Management**: Can change their own password
- **Read-Only**: Limited to viewing their own data

## 📚 API Documentation

For comprehensive API documentation, including all endpoints, request/response formats, and usage examples, see:

**[📖 Complete API Documentation](API_DOCUMENTATION.md)**

The API documentation covers:
- All REST endpoints with examples
- Authentication and authorization details
- Request/response formats
- Error handling
- Security considerations
- Usage examples in multiple languages

## 🔧 Configuration

### LDAP Schema Requirements

The system expects the following LDAP object classes and attributes:

#### User Objects
```ldap
objectClass: top
objectClass: person
objectClass: organizationalPerson
objectClass: inetOrgPerson

# Required Attributes
uid: user_identifier
givenName: first_name
sn: surname
cn: common_name
mail: email_address
employeeNumber: employee_id_or_cpf
userPassword: password_hash
ou: organizational_unit
employeeType: user_role
```

#### Organizational Unit Objects
```ldap
objectClass: top
objectClass: organizationalUnit

# Required Attributes
ou: organizational_unit_name
description: unit_description
```

### Customization

You can customize the system by:

1. **Modifying LDAP Schema**: Update the models in `app/Ldap/`
2. **Adding New Roles**: Extend the `RoleResolver` service
3. **Custom Validation**: Modify validation rules in controllers
4. **UI Customization**: Update Vue.js components and Tailwind classes

## 🧪 Testing

### Run Tests
```bash
# Run all tests
composer test

# Run specific test file
php artisan test --filter=LdapUserControllerTest

# Run with coverage
php artisan test --coverage
```

### Test Routes
The application includes several test routes for development:
- `/test` - Simple test page
- `/debug` - Debug information
- `/phpinfo` - PHP configuration details

## 🐳 Docker Support

### Using Docker Compose
```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop services
docker-compose down
```

### Docker Services
- **Laravel Application**: PHP 8.2 with Laravel
- **Database**: MySQL/PostgreSQL
- **LDAP Server**: OpenLDAP (optional)
- **Web Server**: Nginx/Apache

## 📊 Monitoring & Logging

### Operation Logs
All system operations are logged to the database with:
- Operation type and description
- Entity affected
- User performing the operation
- Timestamp and organizational unit

### System Status
The frontend displays real-time system status including:
- LDAP connection health
- Database connectivity
- System performance metrics

### Error Logging
Comprehensive error logging with:
- Detailed error context
- Stack traces
- User and request information
- LDAP operation details

## 🔒 Security Features

### Access Control
- **Role-Based Permissions**: Granular access control
- **Middleware Protection**: Route-level security
- **Self-Access Restrictions**: Users can only access their own data

### Data Protection
- **Input Validation**: Comprehensive request validation
- **LDAP Injection Prevention**: Proper escaping and validation
- **CSRF Protection**: Built-in Laravel CSRF protection
- **Password Security**: SSHA hashing with salt

### Network Security
- **Host Validation**: Domain restriction for sensitive operations
- **Proxy Support**: Proper handling of proxy headers
- **HTTPS Enforcement**: Secure communication recommendations

## 🚨 Troubleshooting

### Common Issues

#### LDAP Connection Problems
1. Verify LDAP server is running and accessible
2. Check credentials and permissions
3. Verify base DN configuration
4. Check network connectivity and firewall rules

#### Authentication Issues
1. Ensure user exists in LDAP
2. Verify user has appropriate `employeeType` attribute
3. Check OU assignment and permissions
4. Review LDAP schema compatibility

#### Permission Errors
1. Verify user role assignment
2. Check middleware configuration
3. Ensure proper OU access
4. Review role resolution logic

### Debug Tools
- **Debug Route**: `/debug` for system information
- **Log Files**: Check `storage/logs/laravel.log`
- **LDAP Logs**: Enable LDAP debugging in configuration
- **Database Logs**: Check database connection and queries

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

### Code Standards
- Follow PSR-12 coding standards
- Add comprehensive documentation
- Include unit tests for new features
- Update API documentation as needed

## 📄 License

This project is licensed under the MIT License. See the LICENSE file for details.

## 🆘 Support

### Documentation
- **[API Documentation](API_DOCUMENTATION.md)** - Complete API reference
- **[Implementation Guide](IMPLEMENTACAO.md)** - Implementation details
- **[Troubleshooting Guide](TROUBLESHOOTING_LDAP_CONEXAO.md)** - Common issues and solutions

### Getting Help
1. Check the troubleshooting guide
2. Review the API documentation
3. Check existing issues
4. Create a new issue with detailed information

## 🔄 Changelog

### Version 1.0.0
- Initial release
- LDAP user management
- Organizational unit management
- Role-based access control
- LDIF import/export
- Vue.js frontend
- Comprehensive logging

---

**For detailed API documentation and technical specifications, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)**

**For implementation details and configuration, see [IMPLEMENTACAO.md](IMPLEMENTACAO.md)**