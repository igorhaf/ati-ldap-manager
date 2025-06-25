<?php

namespace App\Ldap;

use LdapRecord\Models\ActiveDirectory\User as LdapUser;

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
        'emailForwardAddress' => 'array',
    ];

    /**
     * Get the user's UID (login/chave)
     */
    public function getUidAttribute()
    {
        return $this->getFirstAttribute('uid');
    }

    /**
     * Set the user's UID
     */
    public function setUidAttribute($value)
    {
        $this->setFirstAttribute('uid', $value);
    }

    /**
     * Get the user's given name
     */
    public function getGivenNameAttribute()
    {
        return $this->getFirstAttribute('givenName');
    }

    /**
     * Set the user's given name
     */
    public function setGivenNameAttribute($value)
    {
        $this->setFirstAttribute('givenName', $value);
    }

    /**
     * Get the user's surname
     */
    public function getSnAttribute()
    {
        return $this->getFirstAttribute('sn');
    }

    /**
     * Set the user's surname
     */
    public function setSnAttribute($value)
    {
        $this->setFirstAttribute('sn', $value);
    }

    /**
     * Get the user's common name (CN)
     */
    public function getCnAttribute()
    {
        return $this->getFirstAttribute('cn');
    }

    /**
     * Set the user's common name (CN)
     */
    public function setCnAttribute($value)
    {
        $this->setFirstAttribute('cn', $value);
    }

    /**
     * Get the user's email addresses
     */
    public function getMailAttribute()
    {
        return $this->getAttribute('mail') ?? [];
    }

    /**
     * Set the user's email addresses
     */
    public function setMailAttribute($value)
    {
        $this->setAttribute('mail', is_array($value) ? $value : [$value]);
    }

    /**
     * Get the user's employee number (matrícula)
     */
    public function getEmployeeNumberAttribute()
    {
        return $this->getFirstAttribute('employeeNumber');
    }

    /**
     * Set the user's employee number (matrícula)
     */
    public function setEmployeeNumberAttribute($value)
    {
        $this->setFirstAttribute('employeeNumber', $value);
    }

    /**
     * Get the user's email forward addresses
     */
    public function getEmailForwardAddressAttribute()
    {
        return $this->getAttribute('emailForwardAddress') ?? [];
    }

    /**
     * Set the user's email forward addresses
     */
    public function setEmailForwardAddressAttribute($value)
    {
        $this->setAttribute('emailForwardAddress', is_array($value) ? $value : [$value]);
    }

    /**
     * Get the user's password
     */
    public function getUserPasswordAttribute()
    {
        return $this->getFirstAttribute('userPassword');
    }

    /**
     * Set the user's password
     */
    public function setUserPasswordAttribute($value)
    {
        $this->setFirstAttribute('userPassword', $value);
    }

    /**
     * Get the user's organizational units
     */
    public function getOrganizationalUnitsAttribute()
    {
        return $this->getAttribute('ou') ?? [];
    }

    /**
     * Set the user's organizational units
     */
    public function setOrganizationalUnitsAttribute($value)
    {
        $this->setAttribute('ou', is_array($value) ? $value : [$value]);
    }

    /**
     * Get the full name (givenName + sn)
     */
    public function getFullNameAttribute()
    {
        $givenName = $this->givenName ?? '';
        $sn = $this->sn ?? '';
        return trim($givenName . ' ' . $sn);
    }

    /**
     * Set the full name by splitting into givenName and sn
     */
    public function setFullNameAttribute($value)
    {
        $parts = explode(' ', $value, 2);
        $this->givenName = $parts[0] ?? '';
        $this->sn = $parts[1] ?? '';
        $this->cn = $value;
    }
} 