<?php

namespace App\Services;

use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use App\Utils\LdapUtils;
use App\Models\OperationLog;
use Illuminate\Support\Collection;

class LdifService
{
    /**
     * Gera um LDIF para criar um usuário em múltiplas OUs
     */
    public function generateUserLdif(array $userData, array $organizationalUnits): string
    {
        $baseDn = config('ldap.connections.default.base_dn');
        $ldif = "# LDIF para criação do usuário {$userData['uid']} em múltiplas OUs\n";
        $ldif .= "# Gerado automaticamente em " . now()->format('Y-m-d H:i:s') . "\n\n";

        // Hash da senha uma única vez para reutilizar
        $hashedPassword = LdapUtils::hashSsha($userData['userPassword']);

        foreach ($organizationalUnits as $unit) {
            $ou = is_string($unit) ? $unit : $unit['ou'];
            $role = is_string($unit) ? 'user' : ($unit['role'] ?? 'user');

            $dn = "uid={$userData['uid']},ou={$ou},{$baseDn}";

            $ldif .= "dn: {$dn}\n";
            $ldif .= "objectClass: top\n";
            $ldif .= "objectClass: person\n";
            $ldif .= "objectClass: organizationalPerson\n";
            $ldif .= "objectClass: inetOrgPerson\n";
            $ldif .= "uid: {$userData['uid']}\n";
            $ldif .= "givenName: {$userData['givenName']}\n";
            $ldif .= "sn: {$userData['sn']}\n";
            $ldif .= "cn: {$userData['givenName']} {$userData['sn']}\n";
            $ldif .= "mail: {$userData['mail']}\n";
            $ldif .= "employeeNumber: {$userData['employeeNumber']}\n";
            $ldif .= "userPassword: {$hashedPassword}\n";
            $ldif .= "ou: {$ou}\n";
            $ldif .= "employeeType: {$role}\n";
            $ldif .= "\n";
        }

        return $ldif;
    }

    /**
     * Gera um LDIF para criar uma Unidade Organizacional
     */
    public function generateOuLdif(string $ouName, ?string $description = null): string
    {
        $baseDn = config('ldap.connections.default.base_dn');
        $dn = "ou={$ouName},{$baseDn}";

        $ldif = "# LDIF para criação da OU {$ouName}\n";
        $ldif .= "# Gerado automaticamente em " . now()->format('Y-m-d H:i:s') . "\n\n";
        $ldif .= "dn: {$dn}\n";
        $ldif .= "objectClass: top\n";
        $ldif .= "objectClass: organizationalUnit\n";
        $ldif .= "ou: {$ouName}\n";
        
        if ($description) {
            $ldif .= "description: {$description}\n";
        }
        
        $ldif .= "\n";

        return $ldif;
    }

    /**
     * Aplica um LDIF no sistema
     */
    public function applyLdif(string $ldifContent): array
    {
        $results = [];
        $entries = $this->parseLdif($ldifContent);

        foreach ($entries as $entry) {
            try {
                if ($this->isUserEntry($entry)) {
                    $result = $this->createUserFromLdifEntry($entry);
                } elseif ($this->isOuEntry($entry)) {
                    $result = $this->createOuFromLdifEntry($entry);
                } else {
                    $result = [
                        'success' => false,
                        'dn' => $entry['dn'],
                        'message' => 'Tipo de entrada não suportado'
                    ];
                }
                
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'dn' => $entry['dn'] ?? 'Desconhecido',
                    'message' => 'Erro ao processar entrada: ' . $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Faz o parse de um LDIF
     */
    private function parseLdif(string $ldifContent): array
    {
        $entries = [];
        $lines = explode("\n", $ldifContent);
        $currentEntry = null;

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pular linhas vazias e comentários
            if (empty($line) || strpos($line, '#') === 0) {
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                    $currentEntry = null;
                }
                continue;
            }

            // Nova entrada
            if (strpos($line, 'dn:') === 0) {
                if ($currentEntry !== null) {
                    $entries[] = $currentEntry;
                }
                $currentEntry = ['dn' => trim(substr($line, 3))];
                continue;
            }

            // Atributos
            if ($currentEntry !== null && strpos($line, ':') !== false) {
                [$attribute, $value] = explode(':', $line, 2);
                $attribute = trim($attribute);
                $value = trim($value);

                if (!isset($currentEntry[$attribute])) {
                    $currentEntry[$attribute] = [];
                }
                $currentEntry[$attribute][] = $value;
            }
        }

        // Adicionar a última entrada
        if ($currentEntry !== null) {
            $entries[] = $currentEntry;
        }

        return $entries;
    }

    /**
     * Verifica se uma entrada LDIF é de usuário
     */
    private function isUserEntry(array $entry): bool
    {
        $objectClasses = $entry['objectClass'] ?? [];
        return in_array('inetOrgPerson', $objectClasses) || in_array('person', $objectClasses);
    }

    /**
     * Verifica se uma entrada LDIF é de OU
     */
    private function isOuEntry(array $entry): bool
    {
        $objectClasses = $entry['objectClass'] ?? [];
        return in_array('organizationalUnit', $objectClasses);
    }

    /**
     * Cria um usuário a partir de uma entrada LDIF
     */
    private function createUserFromLdifEntry(array $entry): array
    {
        $uid = $entry['uid'][0] ?? null;
        $ou = $entry['ou'][0] ?? null;

        if (!$uid) {
            return [
                'success' => false,
                'dn' => $entry['dn'],
                'message' => 'UID é obrigatório'
            ];
        }

        // Verificar se já existe usuário com mesmo UID na mesma OU
        $existingUser = LdapUserModel::where('uid', $uid)
            ->where('ou', $ou)
            ->first();

        if ($existingUser) {
            return [
                'success' => false,
                'dn' => $entry['dn'],
                'message' => "Usuário já existe na OU {$ou}"
            ];
        }

        // Verificar se a matrícula já existe (se informada)
        if (isset($entry['employeeNumber'][0])) {
            $existingEmployee = LdapUserModel::where('employeeNumber', $entry['employeeNumber'][0])->first();
            if ($existingEmployee) {
                return [
                    'success' => false,
                    'dn' => $entry['dn'],
                    'message' => 'Matrícula já cadastrada'
                ];
            }
        }

        // Criar o usuário
        $user = new LdapUserModel();
        $user->setFirstAttribute('uid', $uid);
        $user->setFirstAttribute('givenName', $entry['givenName'][0] ?? '');
        $user->setFirstAttribute('sn', $entry['sn'][0] ?? '');
        $user->setFirstAttribute('cn', $entry['cn'][0] ?? '');
        $user->setFirstAttribute('mail', $entry['mail'][0] ?? '');
        
        if (isset($entry['employeeNumber'][0])) {
            $user->setFirstAttribute('employeeNumber', $entry['employeeNumber'][0]);
        }
        
        if (isset($entry['userPassword'][0])) {
            // Se a senha já está hasheada, usar diretamente
            $password = $entry['userPassword'][0];
            if (!str_starts_with($password, '{SSHA}') && !str_starts_with($password, '{SHA}')) {
                $password = LdapUtils::hashSsha($password);
            }
            $user->setFirstAttribute('userPassword', $password);
        }

        if ($ou) {
            $user->setFirstAttribute('ou', $ou);
        }

        $role = $entry['employeeType'][0] ?? 'user';
        $user->setAttribute('employeeType', [$role]);

        $user->setAttribute('objectClass', [
            'top',
            'person',
            'organizationalPerson',
            'inetOrgPerson',
        ]);

        $user->setDn($entry['dn']);
        $user->save();

        // Log da operação
        OperationLog::create([
            'operation' => 'create_user_ldif',
            'entity' => 'User',
            'entity_id' => $uid,
            'ou' => $ou,
            'description' => "Usuário {$uid} criado via LDIF",
        ]);

        return [
            'success' => true,
            'dn' => $entry['dn'],
            'message' => "Usuário {$uid} criado com sucesso"
        ];
    }

    /**
     * Cria uma OU a partir de uma entrada LDIF
     */
    private function createOuFromLdifEntry(array $entry): array
    {
        $ouName = $entry['ou'][0] ?? null;

        if (!$ouName) {
            return [
                'success' => false,
                'dn' => $entry['dn'],
                'message' => 'Nome da OU é obrigatório'
            ];
        }

        // Verificar se a OU já existe
        $existingOu = OrganizationalUnit::where('ou', $ouName)->first();
        if ($existingOu) {
            return [
                'success' => false,
                'dn' => $entry['dn'],
                'message' => "OU {$ouName} já existe"
            ];
        }

        // Criar a OU
        $ou = new OrganizationalUnit();
        $ou->setFirstAttribute('ou', $ouName);
        
        if (isset($entry['description'][0])) {
            $ou->setFirstAttribute('description', $entry['description'][0]);
        }

        $ou->setAttribute('objectClass', [
            'top',
            'organizationalUnit',
        ]);

        $ou->setDn($entry['dn']);
        $ou->save();

        // Log da operação
        OperationLog::create([
            'operation' => 'create_ou_ldif',
            'entity' => 'OrganizationalUnit',
            'entity_id' => $ouName,
            'ou' => $ouName,
            'description' => "OU {$ouName} criada via LDIF",
        ]);

        return [
            'success' => true,
            'dn' => $entry['dn'],
            'message' => "OU {$ouName} criada com sucesso"
        ];
    }
} 