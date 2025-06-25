<?php

namespace App\Ldap;

use LdapRecord\Models\ActiveDirectory\OrganizationalUnit as LdapOrganizationalUnit;

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

    /**
     * Get the organizational unit name
     */
    public function getOuAttribute()
    {
        return $this->getFirstAttribute('ou');
    }

    /**
     * Set the organizational unit name
     */
    public function setOuAttribute($value)
    {
        $this->setFirstAttribute('ou', $value);
    }

    /**
     * Get the description
     */
    public function getDescriptionAttribute()
    {
        return $this->getFirstAttribute('description');
    }

    /**
     * Set the description
     */
    public function setDescriptionAttribute($value)
    {
        $this->setFirstAttribute('description', $value);
    }

    /**
     * Get users in this organizational unit
     */
    public function users()
    {
        return $this->hasMany(User::class, 'ou');
    }
} 