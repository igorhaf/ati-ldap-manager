<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use Illuminate\Support\Str;
use App\Utils\LdapUtils;

class LdapRichSeeder extends Seeder
{
    public function run(): void
    {
        $baseDn = config('ldap.connections.default.base_dn');

        // Apenas o usuário root (sem OU)
        $root = [
            'uid' => 'root.root',
            'givenName' => 'root',
            'sn' => 'root',
            'password' => 'password',
            'mail' => 'root@sei.pe.gov.br',
            'employeeNumber' => '00000000000',
        ];

        $dn = "uid={$root['uid']},{$baseDn}";
        $entry = LdapUserModel::find($dn);
        if (!$entry) {
            $entry = new LdapUserModel();
            $entry->setFirstAttribute('uid', $root['uid']);
        }

        $entry->setFirstAttribute('givenName', $root['givenName']);
        $entry->setFirstAttribute('sn',        $root['sn']);
        $entry->setFirstAttribute('cn',        $root['givenName'].' '.$root['sn']);
        $entry->setFirstAttribute('mail',      $root['mail']);
        $entry->setFirstAttribute('employeeNumber', $root['employeeNumber']);
        $entry->setFirstAttribute('userPassword', LdapUtils::hashSsha($root['password']));
        // Entrada root não possui OU
        if ($entry->getAttribute('ou')) {
            $entry->removeAttribute('ou');
        }
        $entry->setAttribute('employeeType', ['root']);
        $entry->setDn($dn);
        $entry->save();
    }
} 