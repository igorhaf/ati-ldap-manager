<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\User as LdapUser;

class User extends LdapUser
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalPerson',
        'inetOrgPerson',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected array $casts = [
        'mail' => 'array',
    ];

    // Removidos os atributos customizados que causavam loop infinito
    // Agora usaremos diretamente os métodos padrão do LdapRecord

    /**
     * Get the full name (givenName + sn)
     */
    public function getFullNameAttribute()
    {
        $givenName = $this->getFirstAttribute('givenName') ?? '';
        $sn = $this->getFirstAttribute('sn') ?? '';
        return trim($givenName . ' ' . $sn);
    }

    /**
     * Set the full name by splitting into givenName and sn
     */
    public function setFullNameAttribute($value)
    {
        $parts = explode(' ', $value, 2);
        $this->setFirstAttribute('givenName', $parts[0] ?? '');
        $this->setFirstAttribute('sn', $parts[1] ?? '');
        $this->setFirstAttribute('cn', $value);
    }
} 