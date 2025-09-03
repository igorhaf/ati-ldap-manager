<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\User as LdapUser;

class LdapUserModel extends LdapUser
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    // Usar apenas 'inetOrgPerson' para garantir que entradas como o usuário root
    // (que podem não listar explicitamente 'person' / 'organizationalPerson')
    // sejam retornadas nas buscas.
    public static array $objectClasses = [
        'inetOrgPerson',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    // Garantir que 'mail' seja tratado como string, conforme regra do projeto
    protected array $casts = [
        'mail' => 'string',
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
