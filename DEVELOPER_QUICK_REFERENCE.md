# Developer Quick Reference Guide

## 🚀 Quick Start Commands

```bash
# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate

# Development servers
php artisan serve          # Laravel server (http://localhost:8000)
npm run dev               # Vite dev server
npm run build            # Build production assets

# Testing
composer test             # Run all tests
php artisan test --filter=ControllerName  # Run specific tests
```

## 📁 Project Structure

```
app/
├── Http/
│   ├── Controllers/          # API controllers
│   │   ├── AuthController.php
│   │   └── LdapUserController.php
│   └── Middleware/          # Authentication middleware
│       ├── IsRootUser.php
│       ├── IsOUAdmin.php
│       └── IsSelfAccess.php
├── Services/                # Business logic services
│   ├── RoleResolver.php
│   └── LdifService.php
├── Models/                  # Database models
│   └── OperationLog.php
├── Ldap/                    # LDAP models
│   ├── LdapUserModel.php
│   └── OrganizationalUnit.php
├── Traits/                  # Reusable traits
│   └── ChecksRootAccess.php
└── Utils/                   # Utility classes
    ├── LdapUtils.php
    └── LdapDnUtils.php

routes/
├── api.php                  # API routes
└── web.php                  # Web routes

resources/
├── views/                   # Blade templates
│   ├── ldap-simple.blade.php
│   └── auth/
└── js/                      # Frontend assets
```

## 🔐 Authentication & Roles

### User Roles
```php
// Check user role
$role = RoleResolver::resolve(auth()->user());

// Role constants
RoleResolver::ROLE_ROOT      // 'root'
RoleResolver::ROLE_OU_ADMIN  // 'admin'
RoleResolver::ROLE_USER      // 'user'
```

### Middleware Usage
```php
// In routes
Route::middleware(IsRootUser::class)->group(function () {
    // Root-only routes
});

Route::middleware(IsOUAdmin::class)->group(function () {
    // OU Admin and Root routes
});

Route::middleware(IsSelfAccess::class)->group(function () {
    // Self-access routes
});
```

### Access Control in Controllers
```php
// Check root access
$this->checkRootAccess($request);

// Get user OU
$userOu = RoleResolver::getUserOu(auth()->user());
```

## 🌐 API Endpoints Quick Reference

### User Management
```http
GET    /api/ldap/users                    # List users
POST   /api/ldap/users                    # Create user
PUT    /api/ldap/users/{uid}             # Update user
DELETE /api/ldap/users/{uid}             # Delete user
GET    /api/ldap/users/{uid}             # Get user profile
PUT    /api/ldap/users/{uid}/password    # Update password
```

### Organizational Units
```http
POST   /api/ldap/organizational-units    # Create OU
PUT    /api/ldap/organizational-units/{ou} # Update OU
GET    /api/ldap/organizational-units    # List OUs
```

### LDIF Operations
```http
POST   /api/ldap/users/generate-ldif     # Generate user LDIF
POST   /api/ldap/ldif/apply              # Apply LDIF
POST   /api/ldap/ldif/upload             # Upload LDIF file
```

### Logs
```http
GET    /api/ldap/logs                     # Get operation logs
```

## 💻 Common Code Patterns

### Creating a New User
```php
// In controller
public function store(Request $request)
{
    $this->checkRootAccess($request);
    
    $validated = $request->validate([
        'uid' => 'required|string',
        'givenName' => 'required|string',
        'sn' => 'required|string',
        'mail' => 'required|email',
        'employeeNumber' => 'required|string',
        'userPassword' => 'required|string',
        'organizationalUnits' => 'required|array'
    ]);
    
    // Create user logic...
}
```

### LDAP User Creation
```php
use App\Ldap\LdapUserModel;

$user = new LdapUserModel();
$user->setFirstAttribute('uid', 'newuser');
$user->setFirstAttribute('givenName', 'John');
$user->setFirstAttribute('sn', 'Doe');
$user->setFirstAttribute('cn', 'John Doe');
$user->setFirstAttribute('mail', 'john@example.com');
$user->setFirstAttribute('employeeNumber', '12345678901');
$user->setFirstAttribute('userPassword', LdapUtils::hashSsha('password'));
$user->setFirstAttribute('ou', 'IT');
$user->setFirstAttribute('employeeType', 'user');

$user->save();
```

### LDIF Generation
```php
use App\Services\LdifService;

$ldifService = new LdifService();

$userData = [
    'uid' => 'user1',
    'givenName' => 'Jane',
    'sn' => 'Smith',
    'mail' => 'jane@example.com',
    'employeeNumber' => '98765432109',
    'userPassword' => 'password'
];

$organizationalUnits = [
    ['ou' => 'HR', 'role' => 'user'],
    ['ou' => 'Finance', 'role' => 'admin']
];

$ldif = $ldifService->generateUserLdif($userData, $organizationalUnits);
```

### Role-Based UI Rendering
```javascript
// In Vue.js template
<template>
  <div>
    <!-- Root-only content -->
    <div v-if="isRoot">
      <button @click="createOU">Create OU</button>
    </div>
    
    <!-- Admin and Root content -->
    <div v-if="canManageUsers">
      <button @click="createUser">Create User</button>
    </div>
    
    <!-- All users -->
    <div>
      <button @click="changePassword">Change Password</button>
    </div>
  </div>
</template>

<script>
export default {
  computed: {
    isRoot() {
      return window.USER_ROLE === 'root';
    },
    canManageUsers() {
      return ['root', 'admin'].includes(window.USER_ROLE);
    }
  }
}
</script>
```

## 🔧 Configuration Examples

### LDAP Configuration
```php
// config/ldap.php
return [
    'default' => env('LDAP_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'auto_connect' => env('LDAP_AUTO_CONNECT', true),
            'connection' => Adldap\Connections\Ldap::class,
            'settings' => [
                'schema' => Adldap\Schemas\ActiveDirectory::class,
                'account_prefix' => env('LDAP_ACCOUNT_PREFIX', ''),
                'account_suffix' => env('LDAP_ACCOUNT_SUFFIX', ''),
                'hosts' => explode(' ', env('LDAP_HOSTS', 'corp-dc.domain.com')),
                'port' => env('LDAP_PORT', 389),
                'timeout' => env('LDAP_TIMEOUT', 5),
                'base_dn' => env('LDAP_BASE_DN', 'dc=domain,dc=com'),
                'username' => env('LDAP_USERNAME', 'username'),
                'password' => env('LDAP_PASSWORD', 'secret'),
                'follow_referrals' => false,
                'use_ssl' => env('LDAP_USE_SSL', false),
                'use_tls' => env('LDAP_USE_TLS', false),
            ],
        ],
    ],
];
```

### Environment Variables
```env
# LDAP
LDAP_HOST=ldap.example.com
LDAP_PORT=389
LDAP_BASE_DN=dc=example,dc=com
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin_password

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ldap_management
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Application
APP_NAME="LDAP User Management"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
```

## 🧪 Testing Examples

### Controller Testing
```php
// tests/Feature/LdapUserControllerTest.php
public function test_root_user_can_create_ou()
{
    $user = $this->createRootUser();
    
    $response = $this->actingAs($user)
        ->postJson('/api/ldap/organizational-units', [
            'ou' => 'TestOU',
            'description' => 'Test Description'
        ]);
    
    $response->assertStatus(200);
    $response->assertJson(['success' => true]);
}
```

### Service Testing
```php
// tests/Unit/RoleResolverTest.php
public function test_resolves_root_role()
{
    $user = $this->createMockUser(['employeeType' => ['root']]);
    
    $role = RoleResolver::resolve($user);
    
    $this->assertEquals(RoleResolver::ROLE_ROOT, $role);
}
```

### LDAP Testing
```php
// tests/Feature/LdapIntegrationTest.php
public function test_can_connect_to_ldap()
{
    $this->assertTrue(
        LdapUserModel::count() >= 0,
        'LDAP connection failed'
    );
}
```

## 🐛 Common Issues & Solutions

### LDAP Connection Issues
```php
// Check LDAP connection
try {
    $users = LdapUserModel::all();
    echo "LDAP connection successful";
} catch (\Exception $e) {
    echo "LDAP connection failed: " . $e->getMessage();
}

// Enable LDAP debugging
config(['ldap.logging' => true]);
```

### Permission Errors
```php
// Check user role
$role = RoleResolver::resolve(auth()->user());
\Log::info('User role: ' . $role);

// Check user OU
$userOu = RoleResolver::getUserOu(auth()->user());
\Log::info('User OU: ' . $userOu);
```

### Validation Errors
```php
// Get validation errors
if ($validator->fails()) {
    return response()->json([
        'success' => false,
        'errors' => $validator->errors()
    ], 422);
}
```

### CSRF Token Issues
```javascript
// Ensure CSRF token is included
const token = document.querySelector('meta[name="csrf-token"]').content;

fetch('/api/ldap/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify(data)
});
```

## 📊 Logging & Debugging

### Operation Logging
```php
// Log operations
OperationLog::create([
    'operation' => 'user_created',
    'entity' => 'user',
    'entity_id' => $user->getFirstAttribute('uid'),
    'description' => 'User created successfully',
    'ou' => $ou
]);
```

### Debug Logging
```php
// Debug information
\Log::info('User operation', [
    'user' => auth()->user()->getFirstAttribute('uid'),
    'operation' => 'create_user',
    'data' => $request->all()
]);
```

### LDAP Query Debugging
```php
// Enable LDAP query logging
\Log::info('LDAP Query', [
    'query' => 'uid=user1',
    'base_dn' => config('ldap.connections.default.settings.base_dn')
]);
```

## 🚀 Performance Optimization

### LDAP Query Optimization
```php
// Use specific attributes instead of all
$users = LdapUserModel::select(['uid', 'givenName', 'sn', 'mail'])
    ->where('ou', 'IT')
    ->get();

// Pagination for large datasets
$users = LdapUserModel::paginate(50);
```

### Caching
```php
// Cache frequently accessed data
$ous = Cache::remember('organizational_units', 3600, function () {
    return OrganizationalUnit::all();
});
```

### Database Optimization
```php
// Use database indexes for operation logs
Schema::table('operation_logs', function (Blueprint $table) {
    $table->index(['ou', 'created_at']);
    $table->index(['entity', 'entity_id']);
});
```

## 🔒 Security Best Practices

### Input Validation
```php
// Always validate input
$validated = $request->validate([
    'uid' => 'required|string|max:50|regex:/^[a-zA-Z0-9_-]+$/',
    'mail' => 'required|email|max:255',
    'employeeNumber' => 'required|string|size:11|regex:/^\d{11}$/'
]);
```

### LDAP Injection Prevention
```php
// Use proper escaping
use App\Utils\LdapDnUtils;

$safeUid = LdapDnUtils::escapeDnValue($uid);
$safeOu = LdapDnUtils::escapeDnValue($ou);
```

### Password Security
```php
// Use secure hashing
use App\Utils\LdapUtils;

$hashedPassword = LdapUtils::hashSsha($password);

// Verify passwords
$isValid = LdapUtils::verifySsha($password, $storedHash);
```

## 📚 Additional Resources

### Documentation Files
- **[API Documentation](API_DOCUMENTATION.md)** - Complete API reference
- **[Implementation Guide](IMPLEMENTACAO.md)** - Implementation details
- **[Troubleshooting Guide](TROUBLESHOOTING_LDAP_CONEXAO.md)** - Common issues

### External Resources
- [Laravel Documentation](https://laravel.com/docs)
- [LdapRecord Documentation](https://ldaprecord.com/docs)
- [OpenLDAP Documentation](https://www.openldap.org/doc/)
- [Vue.js Documentation](https://vuejs.org/guide/)

### Development Tools
- **Laravel Telescope**: Debug and monitor requests
- **Laravel Debugbar**: Development debugging
- **LDAP Browser**: LDAP data inspection
- **Postman/Insomnia**: API testing

---

**For comprehensive API documentation, see [API_DOCUMENTATION.md](API_DOCUMENTATION.md)**

**For implementation details, see [IMPLEMENTACAO.md](IMPLEMENTACAO.md)**