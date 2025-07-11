<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use Illuminate\Support\Str;

class LdapRichSeeder extends Seeder
{
    public function run(): void
    {
        $baseDn = config('ldap.connections.default.base_dn');

        // Definição de OUs a serem criadas
        $ous = [
            ['ou' => 'Financeiro',   'description' => 'Departamento Financeiro'],
            ['ou' => 'RH',           'description' => 'Recursos Humanos'],
            ['ou' => 'TI',           'description' => 'Tecnologia da Informação'],
            ['ou' => 'Vendas',       'description' => 'Departamento Comercial'],
            ['ou' => 'Marketing',    'description' => 'Departamento de Marketing'],
            ['ou' => 'Logistica',    'description' => 'Operações de Logística'],
            ['ou' => 'Suprimentos',  'description' => 'Gerenciamento de Suprimentos'],
            ['ou' => 'Pesquisa',     'description' => 'Pesquisa e Desenvolvimento'],
            ['ou' => 'Juridico',     'description' => 'Departamento Jurídico'],
            ['ou' => 'Operacoes',    'description' => 'Operações Gerais'],
            ['ou' => 'Suporte',      'description' => 'Suporte Técnico e Atendimento'],
            ['ou' => 'Engenharia',   'description' => 'Engenharia'],
            ['ou' => 'Compras',      'description' => 'Departamento de Compras'],
        ];

        // Cria ou atualiza as OUs
        foreach ($ous as $ouData) {
            $ou = OrganizationalUnit::where('ou', $ouData['ou'])->first();
            if (!$ou) {
                $ou = new OrganizationalUnit();
                $ou->setFirstAttribute('ou', $ouData['ou']);
            }
            $ou->setFirstAttribute('description', $ouData['description']);
            $ou->setDn("ou={$ouData['ou']},{$baseDn}");
            $ou->save();
        }

        // Gerador de employeeNumber sequencial
        $employeeCounter = 1;
        $nextEmp = function() use (&$employeeCounter) {
            return str_pad((string)($employeeCounter++), 4, '0', STR_PAD_LEFT);
        };

        // Definição de usuários com possíveis múltiplas OUs
        /*
         * Cada item:
         *  uid, givenName, sn, password, mail, roles = [ [ou, role] ... ]
         */
        $users = [
            [
                'uid' => 'root',
                'givenName' => 'root',
                'sn' => 'root',
                'password' => 'password',
                'mail' => 'root@example.com',
                'roles' => [ [null, 'root'] ], // null OU = entry diretamente no baseDN
            ],
            [
                'uid' => 'admin.financeiro',
                'givenName' => 'Admin',
                'sn' => 'Financeiro',
                'password' => 'password',
                'mail' => 'admin.financeiro@empresa.com',
                'roles' => [ ['Financeiro', 'admin'] ],
            ],
            [
                'uid' => 'admin.rh',
                'givenName' => 'Admin',
                'sn' => 'RH',
                'password' => 'password',
                'mail' => 'admin.rh@empresa.com',
                'roles' => [ ['RH', 'admin'] ],
            ],
            [
                'uid' => 'admin.ti',
                'givenName' => 'Admin',
                'sn' => 'TI',
                'password' => 'password',
                'mail' => 'admin.ti@empresa.com',
                'roles' => [ ['TI', 'admin'] ],
            ],
            [
                'uid' => 'admin.vendas',
                'givenName' => 'Admin',
                'sn' => 'Vendas',
                'password' => 'password',
                'mail' => 'admin.vendas@empresa.com',
                'roles' => [ ['Vendas', 'admin'] ],
            ],
            // Marketing admin será gerado abaixo se necessário
            [
                'uid' => 'john.doe',
                'givenName' => 'John',
                'sn' => 'Doe',
                'password' => 'password',
                'mail' => 'john.doe@empresa.com',
                'roles' => [ ['Financeiro', 'user'], ['RH', 'user'] ], // presente em 2 OUs
            ],
            [
                'uid' => 'maria.silva',
                'givenName' => 'Maria',
                'sn' => 'Silva',
                'password' => 'password',
                'mail' => 'maria.silva@empresa.com',
                'roles' => [ ['Vendas', 'user'] ],
            ],
            [
                'uid' => 'carlos.pereira',
                'givenName' => 'Carlos',
                'sn' => 'Pereira',
                'password' => 'password',
                'mail' => 'carlos.pereira@empresa.com',
                'roles' => [ ['TI', 'user'], ['Marketing', 'admin'] ], // user em TI, admin em Marketing
            ],
            [
                'uid' => 'ana.lima',
                'givenName' => 'Ana',
                'sn' => 'Lima',
                'password' => 'password',
                'mail' => 'ana.lima@empresa.com',
                'roles' => [ ['Marketing', 'user'] ],
            ],
            [
                'uid' => 'joao.souza',
                'givenName' => 'João',
                'sn' => 'Souza',
                'password' => 'password',
                'mail' => 'joao.souza@empresa.com',
                'roles' => [ ['Logistica', 'user'] ],
            ],
            [
                'uid' => 'patricia.martins',
                'givenName' => 'Patricia',
                'sn' => 'Martins',
                'password' => 'password',
                'mail' => 'patricia.martins@empresa.com',
                'roles' => [ ['Suprimentos', 'admin'] ],
            ],
            [
                'uid' => 'fernando.gomes',
                'givenName' => 'Fernando',
                'sn' => 'Gomes',
                'password' => 'password',
                'mail' => 'fernando.gomes@empresa.com',
                'roles' => [ ['Pesquisa', 'user'], ['TI', 'user'] ],
            ],
            [
                'uid' => 'lucas.costa',
                'givenName' => 'Lucas',
                'sn' => 'Costa',
                'password' => 'password',
                'mail' => 'lucas.costa@empresa.com',
                'roles' => [ ['Compras', 'user'], ['Suprimentos', 'user'] ],
            ],
            [
                'uid' => 'mariana.rocha',
                'givenName' => 'Mariana',
                'sn' => 'Rocha',
                'password' => 'password',
                'mail' => 'mariana.rocha@empresa.com',
                'roles' => [ ['Engenharia', 'admin'], ['Operacoes', 'admin'] ],
            ],
            [
                'uid' => 'diego.alves',
                'givenName' => 'Diego',
                'sn' => 'Alves',
                'password' => 'password',
                'mail' => 'diego.alves@empresa.com',
                'roles' => [ ['Suporte', 'user'], ['TI', 'admin'] ],
            ],
            [
                'uid' => 'laura.pinto',
                'givenName' => 'Laura',
                'sn' => 'Pinto',
                'password' => 'password',
                'mail' => 'laura.pinto@empresa.com',
                'roles' => [ ['Juridico', 'user'] ],
            ],
            [
                'uid' => 'sergio.ramos',
                'givenName' => 'Sergio',
                'sn' => 'Ramos',
                'password' => 'password',
                'mail' => 'sergio.ramos@empresa.com',
                'roles' => [ ['Operacoes', 'user'], ['Logistica', 'admin'] ],
            ],
        ];

        // Garante admin para todas as OUs listadas
        foreach ($ous as $ouData) {
            $ouName = $ouData['ou'];
            $slug   = strtolower($ouName);
            $adminUid = "admin.$slug";

            // Verifica se já existe usuário admin para esta OU
            $hasAdmin = collect($users)->contains(function ($u) use ($ouName) {
                return collect($u['roles'])->contains(fn($r) => $r[0] === $ouName && $r[1] === 'admin');
            });

            if (!$hasAdmin) {
                $users[] = [
                    'uid' => $adminUid,
                    'givenName' => 'Admin',
                    'sn' => $ouName,
                    'password' => 'password',
                    'mail' => "$adminUid@empresa.com",
                    'roles' => [ [$ouName, 'admin'] ],
                ];
            }
        }

        // Garante ao menos um USER (não-admin) em cada OU
        $userIndex = 0;
        foreach ($ous as $ouData) {
            $ouName = $ouData['ou'];

            $hasUser = collect($users)->contains(function ($u) use ($ouName) {
                return collect($u['roles'])->contains(fn($r) => $r[0] === $ouName && $r[1] === 'user');
            });

            if (!$hasUser) {
                // Seleciona usuário existente de forma circular (ignorando root)
                do {
                    $candidate = $users[$userIndex % count($users)];
                    $userIndex++;
                } while ($candidate['uid'] === 'root');

                // Evita duplicidade
                foreach ($users as &$uRef) {
                    if ($uRef['uid'] === $candidate['uid']) {
                        $uRef['roles'][] = [$ouName, 'user'];
                        break;
                    }
                }
                unset($uRef);
            }
        }

        foreach ($users as $userData) {
            // Garante employeeNumber único por UID
            $employeeNumber = $nextEmp();

            // Para cada papel/OU cria ou atualiza entrada
            foreach ($userData['roles'] as [$ouName, $role]) {
                // DN: se ouName null, entry direto no baseDN
                $dn = $ouName ? "uid={$userData['uid']},ou={$ouName},{$baseDn}"
                               : "uid={$userData['uid']},{$baseDn}";

                $entry = LdapUserModel::find($dn);
                if (!$entry) {
                    $entry = new LdapUserModel();
                    $entry->setFirstAttribute('uid', $userData['uid']);
                }

                $entry->setFirstAttribute('givenName', $userData['givenName']);
                $entry->setFirstAttribute('sn',        $userData['sn']);
                $entry->setFirstAttribute('cn',        $userData['givenName'].' '.$userData['sn']);
                $entry->setFirstAttribute('mail',      $userData['mail']);
                $entry->setFirstAttribute('userPassword', $userData['password']);

                // Mantém mesmo employeeNumber para todas as OUs deste usuário
                $currentEmp = $entry->getFirstAttribute('employeeNumber');
                $entry->setFirstAttribute('employeeNumber', $currentEmp ?: $employeeNumber);

                if ($ouName) {
                    $entry->setFirstAttribute('ou', $ouName);
                } else {
                    // Se entrada já existir e possuir OU, removemos
                    if ($entry->exists && $entry->getAttribute('ou')) {
                        $entry->removeAttribute('ou');
                    }
                }

                $entry->setAttribute('employeeType', [$role]);
                $entry->setDn($dn);
                $entry->save();
            }
        }
    }
} 