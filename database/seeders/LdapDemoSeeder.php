<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Ldap\User;
use App\Ldap\OrganizationalUnit;

class LdapDemoSeeder extends Seeder
{
    public function run(): void
    {
        $baseDn = config('ldap.connections.default.base_dn');

        // 1. Criar OU Financeiro
        $financeOu = OrganizationalUnit::where('ou', 'Financeiro')->first();
        if (!$financeOu) {
            $financeOu = new OrganizationalUnit();
            $financeOu->setFirstAttribute('ou', 'Financeiro');
            $financeOu->setFirstAttribute('description', 'Departamento Financeiro');
            $financeOu->setDn("ou=Financeiro,{$baseDn}");
            $financeOu->save();
        }

        // 2. Usuário root
        $root = User::where('uid', 'root')->first();
        if (!$root) {
            $root = new User();
            $root->setFirstAttribute('uid', 'root');
            $root->setFirstAttribute('cn', 'root');
            $root->setFirstAttribute('sn', 'root');
            $root->setFirstAttribute('givenName', 'root');
            $root->setFirstAttribute('userPassword', 'password');
            $root->setAttribute('employeeType', ['root']);
            $root->setDn("cn=root,{$baseDn}");
            $root->save();
        }

        // 3. Admin da OU Financeiro
        $adminUid = 'admin.financeiro';
        $admin = User::where('uid', $adminUid)->first();
        if (!$admin) {
            $admin = new User();
            $admin->setFirstAttribute('uid', $adminUid);
            $admin->setFirstAttribute('cn', 'admin.financeiro');
            $admin->setFirstAttribute('sn', 'Admin');
            $admin->setFirstAttribute('givenName', 'Admin Fin');
            $admin->setFirstAttribute('userPassword', 'password');
            $admin->setFirstAttribute('ou', 'Financeiro');
            $admin->setAttribute('employeeType', ['admin']);
            $admin->setDn("cn=admin.{$financeOu->getFirstAttribute('ou')},ou={$financeOu->getFirstAttribute('ou')},{$baseDn}");
            $admin->save();
        }

        // 4. Usuário comum dentro da OU Financeiro
        $userUid = 'jane.doe';
        $user = User::where('uid', $userUid)->first();
        if (!$user) {
            $user = new User();
            $user->setFirstAttribute('uid', $userUid);
            $user->setFirstAttribute('cn', 'Jane Doe');
            $user->setFirstAttribute('sn', 'Doe');
            $user->setFirstAttribute('givenName', 'Jane');
            $user->setFirstAttribute('userPassword', 'password');
            $user->setFirstAttribute('ou', 'Financeiro');
            $user->setAttribute('employeeType', ['user']);
            $user->setDn("uid={$userUid},ou={$financeOu->getFirstAttribute('ou')},{$baseDn}");
            $user->save();
        }
    }
} 