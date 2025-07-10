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

        // 2. Admin da OU Financeiro
        $adminUid = 'admin.financeiro';
        $admin = User::where('uid', $adminUid)->first();
        if (!$admin) {
            $admin = new User();
            $admin->setFirstAttribute('uid', $adminUid);
            $admin->setFirstAttribute('cn', 'Admin Financeiro');
            $admin->setFirstAttribute('sn', 'Admin');
            $admin->setFirstAttribute('givenName', 'Admin');
            $admin->setFirstAttribute('userPassword', 'password');
            $admin->setFirstAttribute('mail', 'admin.financeiro@empresa.com');
            $admin->setFirstAttribute('employeeNumber', '001');
            $admin->setFirstAttribute('ou', 'Financeiro');
            $admin->setAttribute('employeeType', ['admin']);
            $admin->setDn("uid={$adminUid},ou=Financeiro,{$baseDn}");
            $admin->save();
        }

        // 3. Usuário comum dentro da OU Financeiro  
        $userUid = 'jane.doe';
        $user = User::where('uid', $userUid)->first();
        if (!$user) {
            $user = new User();
            $user->setFirstAttribute('uid', $userUid);
            $user->setFirstAttribute('cn', 'Jane Doe');
            $user->setFirstAttribute('sn', 'Doe');
            $user->setFirstAttribute('givenName', 'Jane');
            $user->setFirstAttribute('userPassword', 'password');
            $user->setFirstAttribute('mail', 'jane.doe@empresa.com');
            $user->setFirstAttribute('employeeNumber', '002');
            $user->setFirstAttribute('ou', 'Financeiro');
            $user->setAttribute('employeeType', ['user']);
            $user->setDn("uid={$userUid},ou=Financeiro,{$baseDn}");
            $user->save();
        }
    }
} 