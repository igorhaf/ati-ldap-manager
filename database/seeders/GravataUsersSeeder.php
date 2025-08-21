<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use App\Utils\LdapUtils;
use App\Utils\LdapDnUtils;

class GravataUsersSeeder extends Seeder
{
    public function run(): void
    {
        $baseDn = config('ldap.connections.default.base_dn');

        // Garantir que a OU gravata exista
        $ouName = 'gravata';
        $ouDn = "ou={$ouName},{$baseDn}";
        $ou = OrganizationalUnit::find($ouDn);
        if (!$ou) {
            $ou = new OrganizationalUnit();
            $ou->setFirstAttribute('ou', $ouName);
            $ou->setFirstAttribute('description', 'Município de Gravatá');
            $ou->setAttribute('objectClass', ['top', 'organizationalUnit']);
            $ou->setDn($ouDn);
            $ou->save();
        }

        // Usuários a serem criados na OU gravata
        $users = [
            [
                'uid' => 'igor.herson',
                'givenName' => 'Igor',
                'sn' => 'Herson',
                'mail' => 'igor.herson@gravata.pe.gov.br',
                'employeeNumber' => '11111111111', // CPF numérico (sem formatação)
                'password' => 'igor.herson',
                'role' => 'admin',
            ],
            [
                'uid' => 'igor.franca',
                'givenName' => 'Igor',
                'sn' => 'Franca',
                'mail' => 'igor.franca@gravata.pe.gov.br',
                'employeeNumber' => '22222222222', // CPF numérico (sem formatação)
                'password' => 'igor.franca',
                'role' => 'user',
            ],
        ];

        foreach ($users as $u) {
            // Construir DN de forma segura
            $dn = LdapDnUtils::buildUserDn($u['uid'], $ouName, $baseDn);

            $entry = LdapUserModel::find($dn);
            if (!$entry) {
                $entry = new LdapUserModel();
                $entry->setFirstAttribute('uid', $u['uid']);
            }

            $entry->setFirstAttribute('givenName', $u['givenName']);
            $entry->setFirstAttribute('sn', $u['sn']);
            $entry->setFirstAttribute('cn', trim($u['givenName'] . ' ' . $u['sn']));
            $entry->setFirstAttribute('mail', $u['mail']);
            $entry->setFirstAttribute('employeeNumber', $u['employeeNumber']);
            $entry->setFirstAttribute('userPassword', LdapUtils::hashSsha($u['password']));
            $entry->setFirstAttribute('ou', $ouName);
            $entry->setAttribute('employeeType', [$u['role']]);
            $entry->setAttribute('objectClass', [
                'top',
                'person',
                'organizationalPerson',
                'inetOrgPerson',
            ]);

            $entry->setDn($dn);
            $entry->save();
        }
    }
}


