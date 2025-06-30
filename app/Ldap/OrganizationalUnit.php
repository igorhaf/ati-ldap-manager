<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\OrganizationalUnit as LdapOrganizationalUnit;

class OrganizationalUnit extends LdapOrganizationalUnit
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static array $objectClasses = [
        'top',
        'organizationalUnit',
    ];

    // Removidos os atributos customizados que causavam loop infinito
    // Agora usaremos diretamente os métodos padrão do LdapRecord

    /**
     * Get users in this organizational unit
     */
    public function users()
    {
        return $this->hasMany(User::class, 'ou');
    }
} 