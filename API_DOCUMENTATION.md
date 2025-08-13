# LDAP User Management System - API Documentation

## Table of Contents
1. [Overview](#overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [API Endpoints](#api-endpoints)
4. [Controllers](#controllers)
5. [Services](#services)
6. [Models](#models)
7. [Middleware](#middleware)
8. [Traits](#traits)
9. [Utilities](#utilities)
10. [Frontend Components](#frontend-components)
11. [Usage Examples](#usage-examples)
12. [Error Handling](#error-handling)

## Overview

The LDAP User Management System is a Laravel-based application that provides comprehensive LDAP user and organizational unit management capabilities. It features role-based access control, LDIF import/export functionality, and a modern Vue.js frontend.

**Key Features:**
- LDAP user CRUD operations
- Organizational unit management
- Role-based access control (Root, OU Admin, User)
- LDIF import/export
- Password management
- Operation logging
- Multi-OU user support

## Authentication & Authorization

### User Roles

The system implements three distinct user roles:

1. **Root User** (`ROLE_ROOT`)
   - Full system access
   - Can manage all OUs and users
   - Can perform LDIF operations
   - Access restricted to specific domains

2. **OU Admin** (`ROLE_OU_ADMIN`)
   - Can manage users within their assigned OU
   - Can view operation logs for their OU
   - Cannot create/modify OUs

3. **Regular User** (`ROLE_USER`)
   - Can only access their own profile
   - Can change their own password
   - Limited to read operations on their data

### Authentication Flow

```php
// Login endpoint
POST /login
// Logout endpoint
POST /logout
```

## API Endpoints

### Base URL
All API endpoints are prefixed with `/api/ldap` and require authentication.

### User Management Endpoints

#### Get All Users
```http
GET /api/ldap/users
```
**Middleware:** `IsOUAdmin`
**Description:** Retrieves all users accessible to the authenticated user
**Response:**
```json
{
  "success": true,
  "data": [
    {
      "dn": "uid=user1,ou=IT,dc=example,dc=com",
      "uid": "user1",
      "givenName": "John",
      "sn": "Doe",
      "cn": "John Doe",
      "fullName": "John Doe",
      "mail": "john.doe@example.com",
      "employeeNumber": "12345678901",
      "organizationalUnits": [
        {
          "ou": "IT",
          "role": "user"
        }
      ]
    }
  ],
  "message": "Usuários carregados com sucesso"
}
```

#### Create User
```http
POST /api/ldap/users
```
**Middleware:** `IsOUAdmin`
**Description:** Creates a new LDAP user
**Request Body:**
```json
{
  "uid": "newuser",
  "givenName": "Jane",
  "sn": "Smith",
  "mail": "jane.smith@example.com",
  "employeeNumber": "98765432109",
  "userPassword": "securepassword",
  "organizationalUnits": [
    {
      "ou": "HR",
      "role": "user"
    }
  ]
}
```

#### Update User
```http
PUT /api/ldap/users/{uid}
```
**Middleware:** `IsOUAdmin`
**Description:** Updates an existing LDAP user
**Parameters:** `uid` - User identifier
**Request Body:** Same as create user (all fields optional)

#### Delete User
```http
DELETE /api/ldap/users/{uid}
```
**Middleware:** `IsOUAdmin`
**Description:** Deletes an LDAP user
**Parameters:** `uid` - User identifier

#### Get User Profile
```http
GET /api/ldap/users/{uid}
```
**Middleware:** `IsSelfAccess`
**Description:** Retrieves a specific user's profile
**Parameters:** `uid` - User identifier

#### Update Password
```http
PUT /api/ldap/users/{uid}/password
```
**Middleware:** `IsSelfAccess`
**Description:** Updates user password
**Parameters:** `uid` - User identifier
**Request Body:**
```json
{
  "userPassword": "newpassword"
}
```

### Organizational Unit Management

#### Create OU
```http
POST /api/ldap/organizational-units
```
**Middleware:** `IsRootUser`
**Description:** Creates a new organizational unit
**Request Body:**
```json
{
  "ou": "NewDepartment",
  "description": "New department description"
}
```

#### Update OU
```http
PUT /api/ldap/organizational-units/{ou}
```
**Middleware:** `IsRootUser`
**Description:** Updates an existing organizational unit
**Parameters:** `ou` - OU name
**Request Body:**
```json
{
  "description": "Updated description"
}
```

#### Get OUs
```http
GET /api/ldap/organizational-units
```
**Middleware:** `IsOUAdmin`
**Description:** Retrieves all organizational units

### LDIF Operations

#### Generate User LDIF
```http
POST /api/ldap/users/generate-ldif
```
**Middleware:** `IsRootUser`
**Description:** Generates LDIF content for user creation
**Request Body:**
```json
{
  "userData": {
    "uid": "user1",
    "givenName": "John",
    "sn": "Doe",
    "mail": "john@example.com",
    "employeeNumber": "12345678901",
    "userPassword": "password"
  },
  "organizationalUnits": [
    {
      "ou": "IT",
      "role": "user"
    }
  ]
}
```

#### Apply LDIF
```http
POST /api/ldap/ldif/apply
```
**Middleware:** `IsRootUser`
**Description:** Applies LDIF content to the LDAP system
**Request Body:**
```json
{
  "ldifContent": "dn: uid=user1,ou=IT,dc=example,dc=com\n..."
}
```

#### Upload LDIF
```http
POST /api/ldap/ldif/upload
```
**Middleware:** `IsRootUser`
**Description:** Uploads and processes an LDIF file
**Request Body:** Multipart form with LDIF file

### Logs

#### Get Operation Logs
```http
GET /api/ldap/logs
```
**Middleware:** `IsOUAdmin`
**Description:** Retrieves operation logs for the authenticated user's OU

## Controllers

### LdapUserController

Main controller for LDAP user management operations.

#### Public Methods

##### `index()`
- **Purpose:** Retrieves all accessible users
- **Returns:** JSON response with user list
- **Access:** OU Admin and Root users

##### `store(Request $request)`
- **Purpose:** Creates a new LDAP user
- **Parameters:** `$request` - HTTP request with user data
- **Returns:** JSON response with operation result
- **Access:** OU Admin and Root users

##### `update(Request $request, string $uid)`
- **Purpose:** Updates an existing LDAP user
- **Parameters:** 
  - `$request` - HTTP request with update data
  - `$uid` - User identifier
- **Returns:** JSON response with operation result
- **Access:** OU Admin and Root users

##### `destroy(string $uid)`
- **Purpose:** Deletes an LDAP user
- **Parameters:** `$uid` - User identifier
- **Returns:** JSON response with operation result
- **Access:** OU Admin and Root users

##### `show(string $uid)`
- **Purpose:** Retrieves a specific user's profile
- **Parameters:** `$uid` - User identifier
- **Returns:** JSON response with user data
- **Access:** Self-access, OU Admin, and Root users

##### `updatePassword(Request $request, string $uid)`
- **Purpose:** Updates user password
- **Parameters:** 
  - `$request` - HTTP request with new password
  - `$uid` - User identifier
- **Returns:** JSON response with operation result
- **Access:** Self-access, OU Admin, and Root users

##### `createOrganizationalUnit(Request $request)`
- **Purpose:** Creates a new organizational unit
- **Parameters:** `$request` - HTTP request with OU data
- **Returns:** JSON response with operation result
- **Access:** Root users only

##### `updateOrganizationalUnit(Request $request, string $ou)`
- **Purpose:** Updates an existing organizational unit
- **Parameters:** 
  - `$request` - HTTP request with update data
  - `$ou` - OU name
- **Returns:** JSON response with operation result
- **Access:** Root users only

##### `getOrganizationalUnits()`
- **Purpose:** Retrieves all organizational units
- **Returns:** JSON response with OU list
- **Access:** OU Admin and Root users

##### `getOperationLogs()`
- **Purpose:** Retrieves operation logs
- **Returns:** JSON response with log entries
- **Access:** OU Admin and Root users

##### `generateUserLdif(Request $request)`
- **Purpose:** Generates LDIF content for user creation
- **Parameters:** `$request` - HTTP request with user data
- **Returns:** JSON response with LDIF content
- **Access:** Root users only

##### `applyLdif(Request $request)`
- **Purpose:** Applies LDIF content to the system
- **Parameters:** `$request` - HTTP request with LDIF content
- **Returns:** JSON response with operation result
- **Access:** Root users only

##### `uploadLdif(Request $request)`
- **Purpose:** Uploads and processes LDIF file
- **Parameters:** `$request` - HTTP request with file upload
- **Returns:** JSON response with operation result
- **Access:** Root users only

### AuthController

Handles LDAP authentication and login/logout operations.

#### Public Methods

##### `showLogin()`
- **Purpose:** Displays the login form
- **Returns:** Login view
- **Access:** Public

##### `login(Request $request)`
- **Purpose:** Authenticates user credentials
- **Parameters:** `$request` - HTTP request with credentials
- **Returns:** Redirect or error response
- **Access:** Public

##### `logout()`
- **Purpose:** Logs out the authenticated user
- **Returns:** Redirect response
- **Access:** Authenticated users

## Services

### RoleResolver

Service for determining user roles and permissions.

#### Public Methods

##### `resolve(Authenticatable $user): string`
- **Purpose:** Determines user role based on LDAP attributes
- **Parameters:** `$user` - Authenticated user instance
- **Returns:** Role string (`ROLE_ROOT`, `ROLE_OU_ADMIN`, or `ROLE_USER`)

##### `getUserOu(Authenticatable $user): ?string`
- **Purpose:** Retrieves the OU name for a user
- **Parameters:** `$user` - Authenticated user instance
- **Returns:** OU name string or null

#### Constants
- `ROLE_ROOT = 'root'`
- `ROLE_OU_ADMIN = 'admin'`
- `ROLE_USER = 'user'`

### LdifService

Service for LDIF generation and processing.

#### Public Methods

##### `generateUserLdif(array $userData, array $organizationalUnits): string`
- **Purpose:** Generates LDIF content for user creation
- **Parameters:** 
  - `$userData` - User information array
  - `$organizationalUnits` - Array of OUs and roles
- **Returns:** LDIF content string

##### `generateOuLdif(string $ouName, ?string $description = null): string`
- **Purpose:** Generates LDIF content for OU creation
- **Parameters:** 
  - `$ouName` - OU name
  - `$description` - Optional description
- **Returns:** LDIF content string

##### `applyLdif(string $ldifContent): array`
- **Purpose:** Applies LDIF content to the system
- **Parameters:** `$ldifContent` - LDIF content string
- **Returns:** Array of operation results

## Models

### LdapUserModel

LDAP user model extending LdapRecord's OpenLDAP User.

#### Object Classes
- `top`
- `person`
- `organizationalPerson`
- `inetOrgPerson`

#### Public Methods

##### `getFullNameAttribute()`
- **Purpose:** Gets the full name (givenName + sn)
- **Returns:** Full name string

##### `setFullNameAttribute($value)`
- **Purpose:** Sets the full name by splitting into givenName and sn
- **Parameters:** `$value` - Full name string

### OrganizationalUnit

LDAP organizational unit model.

#### Object Classes
- `top`
- `organizationalUnit`

#### Public Methods

##### `users()`
- **Purpose:** Gets users in this organizational unit
- **Returns:** HasMany relationship

### OperationLog

Eloquent model for logging system operations.

#### Fillable Attributes
- `operation` - Operation type
- `entity` - Entity type
- `entity_id` - Entity identifier
- `description` - Operation description
- `ou` - Organizational unit

## Middleware

### IsRootUser

Restricts access to root users only.

#### Behavior
- Checks if authenticated user has root role
- Throws 403 error for non-root users
- Allows root users to proceed

### IsOUAdmin

Restricts access to OU admins and root users.

#### Behavior
- Allows root users to pass through
- Checks if user has OU admin role
- Validates OU access for specific resources
- Throws 403 error for unauthorized users

### IsSelfAccess

Allows users to access their own resources.

#### Behavior
- Allows root and OU admin users unlimited access
- Restricts regular users to their own resources
- Validates UID parameter matches authenticated user
- Throws 403 error for unauthorized access

## Traits

### ChecksRootAccess

Trait for checking root user access restrictions.

#### Public Methods

##### `checkRootAccess(Request $request)`
- **Purpose:** Verifies root user access permissions
- **Parameters:** `$request` - HTTP request instance
- **Returns:** Boolean indicating access status

#### Private Methods

##### `getOriginalHost($request)`
- **Purpose:** Gets original host considering proxies
- **Parameters:** `$request` - HTTP request instance
- **Returns:** Host string

##### `isValidHost($host)`
- **Purpose:** Validates host for expected domains
- **Parameters:** `$host` - Host string
- **Returns:** Boolean indicating validity

## Utilities

### LdapUtils

Utility class for LDAP password operations.

#### Public Methods

##### `hashSsha(string $password): string`
- **Purpose:** Generates SSHA hash for passwords
- **Parameters:** `$password` - Plain text password
- **Returns:** SSHA hash string

##### `verifySsha(string $password, string $hash): bool`
- **Purpose:** Verifies password against SSHA hash
- **Parameters:** 
  - `$password` - Plain text password
  - `$hash` - SSHA hash string
- **Returns:** Boolean indicating match

### LdapDnUtils

Utility class for LDAP DN operations.

#### Public Methods

##### `escapeDnValue($value)`
- **Purpose:** Escapes values for safe DN usage
- **Parameters:** `$value` - Value to escape
- **Returns:** Escaped value string

##### `buildUserDn($uid, $ou, $baseDn)`
- **Purpose:** Builds safe user DN
- **Parameters:** 
  - `$uid` - User identifier
  - `$ou` - Organizational unit
  - `$baseDn` - Base DN
- **Returns:** Complete DN string

##### `buildOuDn($ou, $baseDn)`
- **Purpose:** Builds safe OU DN
- **Parameters:** 
  - `$ou` - Organizational unit
  - `$baseDn` - Base DN
- **Returns:** Complete DN string

##### `isValidDnValue($value)`
- **Purpose:** Validates DN value safety
- **Parameters:** `$value` - Value to validate
- **Returns:** Boolean indicating validity

##### `normalizeDnValue($value)`
- **Purpose:** Normalizes DN values
- **Parameters:** `$value` - Value to normalize
- **Returns:** Normalized value string

## Frontend Components

### Main Application (Vue.js)

The frontend is built with Vue.js 3 and uses Tailwind CSS for styling.

#### Key Components

##### User Management
- User creation modal
- User editing interface
- User deletion confirmation
- User search and filtering

##### Organizational Unit Management
- OU creation modal (root only)
- OU editing interface
- OU listing and navigation

##### LDIF Operations
- LDIF generation interface
- LDIF upload functionality
- LDIF application interface

##### Authentication
- Login form
- Logout functionality
- Role-based UI rendering

#### JavaScript Variables

```javascript
window.USER_ROLE    // Current user's role
window.USER_UID     // Current user's UID
window.USER_CN      // Current user's common name
window.USER_MAIL    // Current user's email
```

## Usage Examples

### Creating a New User

```javascript
// Frontend example
const userData = {
  uid: 'newuser',
  givenName: 'John',
  sn: 'Doe',
  mail: 'john.doe@example.com',
  employeeNumber: '12345678901',
  userPassword: 'securepassword',
  organizationalUnits: [
    {
      ou: 'IT',
      role: 'user'
    }
  ]
};

fetch('/api/ldap/users', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  },
  body: JSON.stringify(userData)
})
.then(response => response.json())
.then(data => console.log(data));
```

### PHP Backend Example

```php
// Using the LdapUserController
$controller = new LdapUserController($ldifService);

$request = new Request([
  'uid' => 'newuser',
  'givenName' => 'John',
  'sn' => 'Doe',
  'mail' => 'john.doe@example.com',
  'employeeNumber' => '12345678901',
  'userPassword' => 'securepassword',
  'organizationalUnits' => [
    ['ou' => 'IT', 'role' => 'user']
  ]
]);

$response = $controller->store($request);
```

### LDIF Generation

```php
// Using the LdifService
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

## Error Handling

### HTTP Status Codes

- **200** - Success
- **400** - Bad Request (validation errors)
- **401** - Unauthorized (not authenticated)
- **403** - Forbidden (insufficient permissions)
- **404** - Not Found
- **422** - Validation Error
- **500** - Internal Server Error

### Error Response Format

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field": ["Error message"]
  }
}
```

### Common Error Scenarios

1. **Authentication Required**
   - All API endpoints require valid authentication
   - Use login endpoint to obtain session

2. **Insufficient Permissions**
   - Check user role requirements for each endpoint
   - Ensure proper middleware is applied

3. **Validation Errors**
   - Check request data format
   - Verify required fields are present
   - Ensure data types are correct

4. **LDAP Connection Issues**
   - Verify LDAP server configuration
   - Check network connectivity
   - Validate credentials and permissions

## Security Considerations

### Access Control
- Role-based access control implemented
- Middleware enforces permissions at route level
- Self-access restrictions for regular users

### Data Validation
- Input validation on all endpoints
- LDAP injection prevention through proper escaping
- CSRF protection enabled

### Password Security
- SSHA hashing for password storage
- Secure password verification
- No plain text password logging

### Host Validation
- Domain restriction for root access
- Proxy header validation
- HTTPS enforcement recommendations

## Configuration

### Environment Variables

```env
LDAP_HOST=ldap.example.com
LDAP_PORT=389
LDAP_BASE_DN=dc=example,dc=com
LDAP_USERNAME=cn=admin,dc=example,dc=com
LDAP_PASSWORD=admin_password
```

### LDAP Schema Requirements

The system expects the following LDAP object classes and attributes:

- **User Objects:**
  - `uid` - User identifier
  - `givenName` - First name
  - `sn` - Surname
  - `cn` - Common name
  - `mail` - Email address
  - `employeeNumber` - Employee/CPF number
  - `userPassword` - Password hash
  - `ou` - Organizational unit
  - `employeeType` - User role

- **Organizational Unit Objects:**
  - `ou` - OU name
  - `description` - OU description

## Testing

### API Testing

Use the provided test routes for development and debugging:

```http
GET /test          # Simple test page
GET /debug         # Debug information
GET /phpinfo       # PHP configuration info
```

### Unit Testing

The project includes PHPUnit configuration for testing:

```bash
composer test
```

## Deployment

### Requirements
- PHP 8.2+
- Laravel 12.0+
- OpenLDAP server
- Composer dependencies
- Node.js for frontend assets

### Installation

1. Clone the repository
2. Install PHP dependencies: `composer install`
3. Install Node.js dependencies: `npm install`
4. Configure environment variables
5. Run database migrations: `php artisan migrate`
6. Build frontend assets: `npm run build`

### Docker Support

The project includes Docker configuration for easy deployment:

```bash
docker-compose up -d
```

## Support and Maintenance

### Logging
- Operation logs stored in database
- LDAP operation logging enabled
- Error logging with detailed context

### Monitoring
- System status indicators in frontend
- LDAP connection health checks
- User activity tracking

### Troubleshooting
- Comprehensive error messages
- Debug routes for development
- LDAP connection diagnostics

---

*This documentation covers the complete public API and functionality of the LDAP User Management System. For additional information, refer to the individual source files and inline documentation.*