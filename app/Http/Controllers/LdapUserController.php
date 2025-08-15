<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use App\Services\RoleResolver;
use App\Traits\ChecksRootAccess;
use App\Utils\LdapUtils;
use App\Utils\LdapDnUtils;
use App\Models\OperationLog;
use App\Services\LdifService;
use Illuminate\Support\Facades\Response;

class LdapUserController extends Controller
{
    use ChecksRootAccess;

    protected LdifService $ldifService;

    public function __construct(LdifService $ldifService)
    {
        $this->ldifService = $ldifService;
    }

    /**
     * Safely get an attribute that might not be supported by the LDAP schema
     */
    private function safeGetAttribute($user, $attribute)
    {
        return $user->getFirstAttribute($attribute);
    }



    /**
     * Extrai o nome da OU a partir do atributo 'ou' ou, em fallback,
     * do próprio DN da entrada.
     */
    private function extractOu($entry): ?string
    {
        $ou = $entry->getFirstAttribute('ou');
        if ($ou) {
            return $ou;
        }
        if (preg_match('/ou=([^,]+)/i', $entry->getDn(), $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Verifica se um atributo é parte do RDN (Relative Distinguished Name)
     * e não pode ser modificado diretamente
     */
    private function isAttributeInRdn($entry, $attributeName): bool
    {
        $dn = $entry->getDn();
        if (!$dn) {
            return false;
        }

        // Extrair o RDN (primeira parte do DN)
        $rdnPart = explode(',', $dn)[0];
        
        // Verificar se o atributo está no RDN
        return preg_match("/^{$attributeName}=/i", trim($rdnPart));
    }

    /**
     * Define um atributo de forma segura, evitando modificar atributos do RDN
     */
    private function setSafeAttribute($entry, $attributeName, $value): bool
    {
        if ($this->isAttributeInRdn($entry, $attributeName)) {
            \Log::warning("Tentativa de modificar atributo do RDN ignorada", [
                'dn' => $entry->getDn(),
                'attribute' => $attributeName,
                'value' => $value
            ]);
            return false;
        }

        $entry->setFirstAttribute($attributeName, $value);
        return true;
    }

    /**
     * Display a listing of users
     */
    public function index(): JsonResponse
    {
        $this->checkRootAccess(request());
        
        try {
            $role = RoleResolver::resolve(auth()->user());

            $users = LdapUserModel::all();
            
            // Se admin de OU, filtrar apenas entradas da sua OU
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $users = $users->filter(function ($u) use ($adminOu) {
                    $ouName = $this->extractOu($u);
                    return $ouName && strtolower($ouName) === strtolower($adminOu);
                });
            }
            
            // Agrupar por UID e consolidar as OUs para evitar duplicação de usuários na grid
            $formattedUsers = $users->groupBy(fn ($u) => $u->getFirstAttribute('uid'))
                ->map(function ($entries) {
                    $first = $entries->first();
                    // Para cada entrada, extrai a OU e o papel (employeeType) do usuário
                    $ous = $entries->map(function ($e) {
                        $ouName = $this->extractOu($e);
                        $roleAttr = $e->getAttribute('employeeType') ?? [];
                        // employeeType pode ser string ou array
                        if (is_array($roleAttr)) {
                            $role = strtolower($roleAttr[0] ?? 'user');
                        } else {
                            $role = strtolower($roleAttr ?: 'user');
                        }

                        return [
                            'ou'   => $ouName,
                            'role' => $role,
                        ];
                    })
                    ->filter(fn ($i) => !empty($i['ou']))
                    ->unique('ou')
                    ->values();

                return [
                        'dn' => $first->getDn(),
                        'uid' => $first->getFirstAttribute('uid'),
                        'givenName' => $first->getFirstAttribute('givenName'),
                        'sn' => $first->getFirstAttribute('sn'),
                        'cn' => $first->getFirstAttribute('cn'),
                        'fullName' => trim(($first->getFirstAttribute('givenName') ?? '') . ' ' . ($first->getFirstAttribute('sn') ?? '')),
                        'mail' => $first->getFirstAttribute('mail'),
                        'employeeNumber' => $first->getFirstAttribute('employeeNumber'),
                        'organizationalUnits' => $ous,
                ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $formattedUsers,
                'message' => 'Usuários carregados com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar usuários: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        $this->checkRootAccess($request);
        
        try {
            $request->validate([
                'uid' => 'required|string|max:255|regex:/^[a-zA-Z0-9._-]+$/',
                'givenName' => 'required|string|max:255',
                'sn' => 'required|string|max:255',
                'employeeNumber' => 'required|string|max:255',
                'mail' => 'required|email',
                'userPassword' => 'required|string|min:6',
                'organizationalUnits' => 'array',
                // Cada item pode ser string (OU) ou objeto {ou, role}
                'organizationalUnits.*' => 'required',
            ]);

            // Validação adicional para DN seguro
            if (!LdapDnUtils::isValidDnValue($request->uid)) {
                return response()->json([
                    'success' => false,
                    'message' => 'UID contém caracteres inválidos para LDAP'
                ], 422);
            }

            if (LdapDnUtils::hasProblematicChars($request->uid)) {
                \Log::warning('UID contém caracteres problemáticos', [
                    'uid' => $request->uid,
                    'problematic_chars' => LdapDnUtils::getProblematicChars($request->uid)
                ]);
            }

            $role = RoleResolver::resolve(auth()->user());
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                
                // Validar se alguma OU especificada não é a do admin
                $requestedOus = collect($request->organizationalUnits)->map(function($i) {
                    return is_string($i) ? $i : ($i['ou'] ?? null);
                })->filter();
                
                foreach ($requestedOus as $requestedOu) {
                    if (strtolower($requestedOu) !== strtolower($adminOu)) {
                        return response()->json([
                            'success' => false,
                            'message' => "Acesso negado: você só pode criar usuários na OU '{$adminOu}'"
                        ], 403);
                    }
                }
                
                // Se não informou nenhuma OU ou informou OU inválida, usar a OU do admin com role user
                if ($requestedOus->isEmpty()) {
                $request->merge([
                        'organizationalUnits' => [['ou' => $adminOu, 'role' => 'user']],
            ]);
                }
            }

            // Verificar se já existem entradas com UID e mesma OU
            $existingEntries = LdapUserModel::where('uid', $request->uid)->get();

            $unitsInput = collect($request->organizationalUnits)->map(function($i){return is_string($i)? $i : ($i['ou'] ?? null);})->filter();

            foreach ($existingEntries as $entry){
                $existingOu = strtolower($this->extractOu($entry) ?? '');
                if ($unitsInput->contains(fn($ou)=> strtolower($ou) === $existingOu)){
                return response()->json([
                    'success' => false,
                        'message' => "Usuário já existe na OU {$existingOu}"
                ], 422);
                }
            }

            // Verificar se o CPF já existe
            $existingEmployee = LdapUserModel::where('employeeNumber', $request->employeeNumber)->first();
            if ($existingEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'CPF já cadastrado'
                ], 422);
            }

            $baseDn = config('ldap.connections.default.base_dn');

            $units = collect($request->organizationalUnits)->map(function ($item) {
                // aceitar string simples também (default role user)
                if (is_string($item)) {
                    return ['ou' => $item, 'role' => 'user'];
                }
                return $item;
            });

            // Hash da senha uma única vez para reutilizar
            $hashedPassword = LdapUtils::hashSsha($request->userPassword);

            foreach ($units as $unit) {
                $ou = $unit['ou'];
                $role = $unit['role'] ?? 'user';

                // Validar OU
                if (!LdapDnUtils::isValidDnValue($ou)) {
                    return response()->json([
                        'success' => false,
                        'message' => "OU '{$ou}' contém caracteres inválidos para LDAP"
                    ], 422);
                }

                $entry = new LdapUserModel();           // já usa o alias correto
                $entry->setFirstAttribute('uid', $request->uid);
                $entry->setFirstAttribute('givenName',  $request->givenName);
                $entry->setFirstAttribute('sn',         $request->sn);
                $entry->setFirstAttribute('cn',         $request->givenName.' '.$request->sn);
                $entry->setFirstAttribute('mail',       $request->mail);
                $entry->setFirstAttribute('employeeNumber', $request->employeeNumber);
                $entry->setFirstAttribute('userPassword',   $hashedPassword);
                $entry->setFirstAttribute('ou',         $ou);
                $entry->setAttribute('employeeType',    [$role]);

                // 1⃣  defina o objectClass completo ANTES de salvar
                $entry->setAttribute('objectClass', [
                    'top',
                    'person',
                    'organizationalPerson',
                    'inetOrgPerson',
                ]);

                // Construir DN de forma segura
                $safeDn = LdapDnUtils::buildUserDn($request->uid, $ou, $baseDn);
                \Log::info('Criando usuário com DN', [
                    'uid' => $request->uid,
                    'ou' => $ou,
                    'dn' => $safeDn
                ]);
                
                $entry->setDn($safeDn);
                $entry->save();
                //var_dump($entry);             // 1ª operação: cria a entrada
            }

            OperationLog::create([
                'operation' => 'create_user',
                'entity' => 'User',
                'entity_id' => $request->uid,
                'ou' => $units->pluck('ou')->unique()->join(','),
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => 'Criação do usuário com OU(s) ' . $units->pluck('ou')->unique()->join(','),
                'changes' => json_encode([
                    'uid' => [null, $request->uid],
                    'givenName' => [null, $request->givenName],
                    'sn' => [null, $request->sn],
                    'mail' => [null, $request->mail],
                    'employeeNumber' => [null, $request->employeeNumber],
                    'organizationalUnits' => [null, $units->toArray()],
                ]),
                'description' => 'Usuário ' . $request->uid . ' criado',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'uid' => $request->uid,
                    'givenName' => $request->givenName,
                    'sn' => $request->sn,
                    'employeeNumber' => $request->employeeNumber,
                    'mail' => $request->mail,
                ],
                'message' => 'Usuário criado com sucesso'
            ], 201);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'create_user',
                'entity' => 'User',
                'entity_id' => $request->uid ?? null,
                'ou' => collect($request->organizationalUnits ?? [])->map(fn($i)=> is_string($i)? $i : ($i['ou'] ?? null))->filter()->unique()->join(','),
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao criar usuário',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(string $uid): JsonResponse
    {
        $this->checkRootAccess(request());
        
        try {
            $user = LdapUserModel::where('uid', $uid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $user->getDn(),
                    'uid' => $user->getFirstAttribute('uid'),
                    'givenName' => $user->getFirstAttribute('givenName'),
                    'sn' => $user->getFirstAttribute('sn'),
                    'cn' => $user->getFirstAttribute('cn'),
                    'fullName' => trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? '')),
                    'mail' => $user->getFirstAttribute('mail'),
                    'employeeNumber' => $user->getFirstAttribute('employeeNumber'),
                    'organizationalUnits' => $user->getAttribute('ou') ?? [],
                ],
                'message' => 'Usuário encontrado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $uid): JsonResponse
    {
        $this->checkRootAccess($request);
        
        try {
            $request->validate([
                'givenName' => 'sometimes|required|string|max:255',
                'sn' => 'sometimes|required|string|max:255',
                'mail' => 'sometimes|required|email',
                'userPassword' => 'sometimes|nullable|string|min:6',
                'organizationalUnits' => 'sometimes|array',
                // aceitar string ou objeto {ou, role}
                'organizationalUnits.*' => 'required',
            ]);

            $users = LdapUserModel::where('uid', $uid)->get();
            $role = RoleResolver::resolve(auth()->user());

            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $belongs = $users->every(function($u) use ($adminOu){
                    return strtolower($this->extractOu($u) ?? '') === strtolower($adminOu);
                });
                if (!$belongs) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Acesso negado: usuário fora da sua OU'
                    ], 403);
                }
                
                // Validar se alguma OU especificada na atualização não é a do admin
                if ($request->has('organizationalUnits')) {
                    $requestedOus = collect($request->organizationalUnits)->map(function($i) {
                        return is_string($i) ? $i : ($i['ou'] ?? null);
                    })->filter();
                    
                    foreach ($requestedOus as $requestedOu) {
                        if (strtolower($requestedOu) !== strtolower($adminOu)) {
                            return response()->json([
                                'success' => false,
                                'message' => "Acesso negado: você só pode editar usuários na OU '{$adminOu}'"
                            ], 403);
                        }
                    }
                }
            }

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            // Capturar valores originais ANTES de qualquer modificação
            $originalFirst = $users->first();
            $originalValues = [
                'givenName' => $originalFirst?->getFirstAttribute('givenName'),
                'sn' => $originalFirst?->getFirstAttribute('sn'),
                'mail' => $originalFirst?->getFirstAttribute('mail'),
            ];
            $originalUnits = $users->map(function ($u) {
                $ou = $this->extractOu($u);
                $role = $u->getFirstAttribute('employeeType');
                if (is_array($role)) { $role = $role[0] ?? null; }
                return $ou ? ['ou' => $ou, 'role' => ($role ?: 'user')] : null;
            })->filter()->values();

            // Mapear entradas existentes por OU (lowercase)
            $existingByOu = $users->keyBy(fn($u) => strtolower($this->extractOu($u) ?? ''));

            $baseDn = config('ldap.connections.default.base_dn');

            // Unidades enviadas na requisição
            $units = collect($request->organizationalUnits ?? [])->map(function($i){
                if (is_string($i)) return ['ou'=>$i,'role'=>'user'];
                return [
                    'ou' => $i['ou'],
                    'role' => $i['role'] ?? 'user'
                ];
            });

            // Loop unidades solicitadas
            foreach ($units as $unit) {
                $ouLower = strtolower($unit['ou']);
                $role     = $unit['role'];

                if ($existingByOu->has($ouLower)) {
                    // Atualizar entrada existente
                    $user = $existingByOu[$ouLower];

                    if ($request->has('givenName')) $user->setFirstAttribute('givenName', $request->givenName);
                    if ($request->has('sn'))       $user->setFirstAttribute('sn',       $request->sn);
                    if ($request->has('mail'))     $user->setFirstAttribute('mail',     $request->mail);
                    
                    if ($request->has('userPassword') && !empty($request->userPassword)) {
                        $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                    }

                    // Nome completo (só atualiza se cn não faz parte do RDN)
                    $newCn = trim(($request->givenName ?? $user->getFirstAttribute('givenName')) . ' ' . ($request->sn ?? $user->getFirstAttribute('sn')));
                    $this->setSafeAttribute($user, 'cn', $newCn);

                    // Papel
                    $user->setAttribute('employeeType', [$role]);

                    $user->save();
                } else {
                    // Criar nova entrada nessa OU apenas se não existir
                    $entry = new LdapUserModel();
                    $entry->setFirstAttribute('uid', $uid);
                    $entry->setFirstAttribute('givenName', $request->get('givenName', $users->first()->getFirstAttribute('givenName')));
                    $entry->setFirstAttribute('sn', $request->get('sn', $users->first()->getFirstAttribute('sn')));
                    $entry->setFirstAttribute('cn', trim(($request->get('givenName', $users->first()->getFirstAttribute('givenName'))) . ' ' . ($request->get('sn', $users->first()->getFirstAttribute('sn')))));
                    $entry->setFirstAttribute('mail', $request->get('mail', $users->first()->getFirstAttribute('mail')));
                    $entry->setFirstAttribute('employeeNumber', $users->first()->getFirstAttribute('employeeNumber'));
                    if ($request->has('userPassword')) {
                        $entry->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                    } else {
                        $entry->setFirstAttribute('userPassword', $users->first()->getFirstAttribute('userPassword'));
                    }
                    $entry->setFirstAttribute('ou', $unit['ou']);
                    $entry->setAttribute('employeeType', [$role]);
                    $entry->setAttribute('objectClass', [
                        'top',
                        'person',
                        'organizationalPerson',
                        'inetOrgPerson',
                    ]);
                    $entry->setDn("uid={$uid},ou={$unit['ou']},{$baseDn}");
                    $entry->save();
                }
            }

            // Atualizar atributos comuns nos casos em que nenhuma OU informada (apenas email, nome etc.)
            if ($units->isEmpty()) {
                foreach ($users as $user) {
                    if ($request->has('givenName')) $user->setFirstAttribute('givenName', $request->givenName);
                    if ($request->has('sn'))       $user->setFirstAttribute('sn',       $request->sn);
                    if ($request->has('mail'))     $user->setFirstAttribute('mail',     $request->mail);
                    
                    if ($request->has('userPassword') && !empty($request->userPassword)) {
                        $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                    }

                    if ($request->has('givenName') || $request->has('sn')) {
                        $newCn = trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? ''));
                        $this->setSafeAttribute($user, 'cn', $newCn);
            }
            $user->save();
                }
            }

            // Construir resumo e diferenças (apenas campos alterados)
            $ouList = ($units->isEmpty() ? $users->map(fn($u)=>$this->extractOu($u))->filter()->unique()->join(',') : $units->pluck('ou')->unique()->join(','));
            $labelMap = [
                'givenName' => 'Nome',
                'sn' => 'Sobrenome',
                'mail' => 'E-mail',
                'userPassword' => 'Senha',
                'organizationalUnits' => 'Organização',
            ];
            $changes = [];
            $summaryParts = [];
            foreach (['givenName','sn','mail'] as $field) {
                if ($request->has($field)) {
                    $old = $originalValues[$field] ?? null;
                    $new = $request->input($field);
                    if ($old !== $new) {
                        $changes[$field] = ['old' => $old, 'new' => $new];
                        $summaryParts[] = $labelMap[$field] . ": '" . ($old ?? '-') . "' → '" . ($new ?? '-') . "'";
                    }
                }
            }
            if ($request->has('userPassword') && !empty($request->userPassword)) {
                // Não registrar valores, apenas que houve alteração
                $changes['userPassword'] = ['changed' => true];
                $summaryParts[] = 'Senha alterada';
            }
            if (!$units->isEmpty()) {
                $newUnits = $units->values();
                // Separar comparações por OU e Role
                $oldOUs = $originalUnits->pluck('ou')->join(', ');
                $newOUs = $newUnits->pluck('ou')->join(', ');
                $oldRoles = $originalUnits->pluck('role')->join(', ');
                $newRoles = $newUnits->pluck('role')->join(', ');
                
                if ($oldOUs !== $newOUs) {
                    $changes['ou'] = [
                        'old' => $oldOUs,
                        'new' => $newOUs,
                    ];
                    $summaryParts[] = 'Organização: \'' . $oldOUs . '\' → \'' . $newOUs . '\'';
                }
                
                if ($oldRoles !== $newRoles) {
                    $changes['employeeType'] = [
                        'old' => $oldRoles,
                        'new' => $newRoles,
                    ];
                    $summaryParts[] = 'Papel: \'' . $oldRoles . '\' → \'' . $newRoles . '\'';
                }
            }
            $changesSummary = empty($summaryParts) ? 'Sem alterações de dados' : implode('; ', $summaryParts);

            OperationLog::create([
                'operation' => 'update_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => $ouList,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => $changesSummary,
                'changes' => $changes,
                'description' => 'Usuário ' . $uid . ' atualizado',
            ]);

            // Retornar primeira entrada consolidada
            $first = $users->first();
            $ous = $users->map(fn ($e) => $this->extractOu($e))->filter()->unique()->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $first->getDn(),
                    'uid' => $first->getFirstAttribute('uid'),
                    'givenName' => $first->getFirstAttribute('givenName'),
                    'sn' => $first->getFirstAttribute('sn'),
                    'cn' => $first->getFirstAttribute('cn'),
                    'fullName' => trim(($first->getFirstAttribute('givenName') ?? '') . ' ' . ($first->getFirstAttribute('sn') ?? '')),
                    'mail' => $first->getFirstAttribute('mail'),
                    'employeeNumber' => $first->getFirstAttribute('employeeNumber'),
                    'organizationalUnits' => $ous,
                ],
                'message' => 'Usuário atualizado com sucesso'
            ]);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'update_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => $users->map(fn($u)=>$this->extractOu($u))->filter()->unique()->join(',') ?? null,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao atualizar usuário',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $uid): JsonResponse
    {
        $this->checkRootAccess(request());
        
        try {
            $users = LdapUserModel::where('uid', $uid)->get();
            $role = RoleResolver::resolve(auth()->user());

            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $belongs = $users->every(function($u) use ($adminOu){
                    return strtolower($this->extractOu($u) ?? '') === strtolower($adminOu);
                });
                if (!$belongs) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Acesso negado: usuário fora da sua OU'
                    ], 403);
                }
            }

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            foreach ($users as $user) {
            $user->delete();
            }

            OperationLog::create([
                'operation' => 'delete_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => $users->map(fn($u)=>$this->extractOu($u))->filter()->unique()->join(','),
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => 'Remoção de todas as entradas do usuário',
                'description' => 'Usuário ' . $uid . ' excluído',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuário removido com sucesso de todas as OUs'
            ]);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'delete_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao excluir usuário',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organizational units
     */
    public function getOrganizationalUnits(): JsonResponse
    {
        $this->checkRootAccess(request());
        
        try {
            $role = RoleResolver::resolve(auth()->user());
            
            // Apenas usuários root podem visualizar OUs
            if ($role !== RoleResolver::ROLE_ROOT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado: apenas usuários root podem visualizar unidades organizacionais'
                ], 403);
            }

            $ous = OrganizationalUnit::all();

            $formattedOus = $ous->map(function ($ou) {
                return [
                    'ou' => $ou->getFirstAttribute('ou'),
                    'description' => $ou->getFirstAttribute('description'),
                    'dn' => $ou->getDn(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedOus,
                'message' => 'Unidades organizacionais carregadas com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar unidades organizacionais: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create organizational unit
     */
    public function createOrganizationalUnit(Request $request): JsonResponse
    {
        $this->checkRootAccess($request);
        
        try {
            $role = RoleResolver::resolve(auth()->user());
            
            // Apenas usuários root podem criar OUs
            if ($role !== RoleResolver::ROLE_ROOT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado: apenas usuários root podem criar unidades organizacionais'
                ], 403);
            }

            $request->validate([
                'ou' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ]);

            // Verificar se a OU já existe
            $existingOu = OrganizationalUnit::where('ou', $request->ou)->first();
            if ($existingOu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade organizacional já existe'
                ], 422);
            }

            $baseDn = config('ldap.connections.default.base_dn');

            $ou = new OrganizationalUnit();
            $ou->setFirstAttribute('ou', $request->ou);
            if ($request->has('description') && !empty($request->description)) {
                $ou->setFirstAttribute('description', $request->description);
            }
            $ou->setAttribute('objectClass', [
                'top',
                'organizationalUnit',
            ]);
            $ou->setDn("ou={$request->ou},{$baseDn}");
            $ou->save();

            OperationLog::create([
                'operation' => 'create_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $request->ou,
                'ou' => $request->ou,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => 'Criação da OU',
                'description' => 'Unidade organizacional ' . $request->ou . ' criada',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'ou' => $request->ou,
                    'description' => $request->description ?? null,
                ],
                'message' => 'Unidade organizacional criada com sucesso'
            ], 201);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'create_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $request->ou ?? null,
                'ou' => $request->ou ?? null,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao criar unidade organizacional',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar unidade organizacional: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update organizational unit
     */
    public function updateOrganizationalUnit(Request $request, string $ouName): JsonResponse
    {
        $this->checkRootAccess($request);
        
        try {
            $role = RoleResolver::resolve(auth()->user());
            
            // Apenas usuários root podem editar OUs
            if ($role !== RoleResolver::ROLE_ROOT) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado: apenas usuários root podem editar unidades organizacionais'
                ], 403);
            }

            $request->validate([
                'description' => 'nullable|string|max:255',
            ]);

            $ou = OrganizationalUnit::where('ou', $ouName)->first();
            if (!$ou) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade organizacional não encontrada'
                ], 404);
            }

            // Capturar valor antigo antes da alteração
            $oldDescription = $ou->getFirstAttribute('description');
            if ($request->has('description')) {
                $ou->setFirstAttribute('description', $request->description);
            }
            $ou->save();

            // Preparar diff apenas se houver mudança
            $changes = [];
            $summary = 'Sem alterações de descrição';
            if ($request->has('description') && $oldDescription !== $request->description) {
                $changes['description'] = [
                    'old' => $oldDescription,
                    'new' => $request->description,
                ];
                $summary = "Descrição: '" . ($oldDescription ?? '-') . "' → '" . ($request->description ?? '-') . "'";
            }

            OperationLog::create([
                'operation' => 'update_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $ouName,
                'ou' => $ouName,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => $summary,
                'changes' => $changes,
                'description' => 'Unidade organizacional ' . $ouName . ' atualizada',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'ou' => $ouName,
                    'description' => $request->description ?? null,
                ],
                'message' => 'Unidade organizacional atualizada com sucesso'
            ]);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'update_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $ouName,
                'ou' => $ouName,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao atualizar unidade organizacional',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar unidade organizacional: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get operation logs
     */
    public function getOperationLogs(): JsonResponse
    {
        $this->checkRootAccess(request());
        
        try {
            $role = RoleResolver::resolve(auth()->user());
            
            // Se for ROOT, vê todos os logs
            if ($role === RoleResolver::ROLE_ROOT) {
                $logs = OperationLog::orderBy('created_at', 'desc')->get();
            } else {
                // Se for admin de OU, vê apenas logs da sua OU
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $logs = OperationLog::where('ou', $adminOu)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // Mapeamento de rótulos amigáveis
            $roleLabels = [
                'root' => 'Root',
                'admin' => 'Administrador de Organização',
                'user' => 'Usuário',
            ];
            $operationLabels = [
                'create_user' => 'Criou usuário',
                'update_user' => 'Atualizou usuário',
                'delete_user' => 'Excluiu usuário',
                'update_password' => 'Redefiniu senha',
                'create_organizational_unit' => 'Criou organização',
                'update_organizational_unit' => 'Atualizou organização',
                'create_ou' => 'Criou organização',
                'update_ou' => 'Atualizou organização',
                'apply_ldif' => 'Aplicou LDIF',
            ];

            $formatted = $logs->map(function ($log) use ($roleLabels, $operationLabels) {
                $operationKey = (string) ($log->operation ?? '');
                $action = $operationLabels[$operationKey] ?? ucfirst(str_replace('_', ' ', $operationKey));
                $actorRoleKey = $log->actor_role ?? null;
                $actorRole = $roleLabels[$actorRoleKey] ?? ($actorRoleKey ?: null);
                $actorUid = $log->actor_uid ?? '-';
                $actor = $actorRole ? ($actorUid . ' (' . $actorRole . ')') : $actorUid;

                $entity = (string) ($log->entity ?? '-');
                $entityId = (string) ($log->entity_id ?? '-');
                if ($entity === 'User') {
                    $target = ($entityId !== '' ? $entityId : '-');
                } elseif ($entity === 'OrganizationalUnit') {
                    $target = 'Organização: ' . ($entityId !== '' ? $entityId : '-');
                } else {
                    $target = ($entity !== '' ? $entity : 'Entidade') . ': ' . ($entityId !== '' ? $entityId : '-');
                }

                $result = (string) ($log->result ?? 'success');
                $resultLabel = $result === 'failure' ? 'Falha' : 'Sucesso';

                // Normalizar mudanças (antes/depois) com rótulos amigáveis
                $labelMap = [
                    'uid' => 'UID (Login)',
                    'givenName' => 'Nome',
                    'sn' => 'Sobrenome',
                    'cn' => 'Nome completo',
                    'mail' => 'E-mail',
                    'employeeNumber' => 'CPF',
                    'employeeType' => 'Papel',
                    'organizationalUnits' => 'Organização',
                    'ou' => 'Organização',
                    'description' => 'Descrição',
                    'userPassword' => 'Senha',
                ];

                $rawChanges = $log->changes ?? null;
                if (is_string($rawChanges)) {
                    $decoded = json_decode($rawChanges, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $rawChanges = $decoded;
                    }
                }

                $normalizedChanges = [];
                if (is_array($rawChanges)) {
                    foreach ($rawChanges as $attr => $change) {
                        $label = $labelMap[$attr] ?? ucfirst(str_replace('_', ' ', (string) $attr));
                        if ($attr === 'userPassword') {
                            // Verificar se realmente houve mudança de senha
                            if (is_array($change) && isset($change['changed']) && $change['changed']) {
                                $normalizedChanges[] = [
                                    'field' => $label,
                                    'note' => 'Senha alterada',
                                ];
                            }
                            continue;
                        }

                        $oldRaw = null;
                        $newRaw = null;
                        if (is_array($change) && array_key_exists('old', $change)) {
                            $oldRaw = $change['old'];
                        }
                        if (is_array($change) && array_key_exists('new', $change)) {
                            $newRaw = $change['new'];
                        }
                        // Caso tenha vindo apenas valor simples
                        if ($oldRaw === null && $newRaw === null && !is_array($change)) {
                            $newRaw = $change;
                        }

                        // Se old e new existem e são iguais, ignorar (não exibir)
                        $bothDefined = ($oldRaw !== null && $newRaw !== null);
                        $areEqual = $bothDefined && (json_encode($oldRaw, JSON_UNESCAPED_UNICODE) === json_encode($newRaw, JSON_UNESCAPED_UNICODE));
                        if ($areEqual) {
                            continue;
                        }
                        // Se ambos nulos, ignorar
                        if ($oldRaw === null && $newRaw === null) {
                            continue;
                        }

                        // Converter para string legível
                        $toString = function ($value) {
                            if (is_null($value)) return null;
                            if (is_scalar($value)) return (string) $value;
                            return json_encode($value, JSON_UNESCAPED_UNICODE);
                        };
                        $normalizedChanges[] = [
                            'field' => $label,
                            'old' => $toString($oldRaw),
                            'new' => $toString($newRaw),
                        ];
                    }
                }

                return [
                    'id' => $log->id,
                    'actor' => $actor, // Quem fez
                    'action' => $action, // O que fez
                    'target' => $target, // Em quem fez
                    'ou' => $log->ou ?? null,
                    'result' => $resultLabel,
                    'when' => optional($log->created_at)->toIso8601String(),
                    'description' => $log->description ?? null,
                    'changes_summary' => $log->changes_summary ?? null,
                    'changes' => $normalizedChanges,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formatted,
                'message' => 'Logs carregados com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request, string $uid): JsonResponse
    {
        $this->checkRootAccess($request);
        
        try {
            $request->validate([
                'userPassword' => 'required|string|min:6',
            ]);

            $users = LdapUserModel::where('uid', $uid)->get();
            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            foreach ($users as $user) {
                $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                $user->save();
            }

            OperationLog::create([
                'operation' => 'update_password',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => $users->map(fn($u)=>$this->extractOu($u))->filter()->unique()->join(','),
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'success',
                'changes_summary' => 'Senha redefinida',
                'description' => 'Senha do usuário ' . $uid . ' alterada',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);

        } catch (\Exception $e) {
            OperationLog::create([
                'operation' => 'update_password',
                'entity' => 'User',
                'entity_id' => $uid,
                'actor_uid' => auth()->user()?->getFirstAttribute('uid'),
                'actor_role' => \App\Services\RoleResolver::resolve(auth()->user()),
                'result' => 'failure',
                'error_message' => $e->getMessage(),
                'description' => 'Falha ao alterar senha',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar senha: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gera um LDIF para criação de usuário em múltiplas OUs
     */
    public function generateUserLdif(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $this->checkRootAccess($request);

        try {
            $request->validate([
                'uid' => 'required|string|max:255',
                'givenName' => 'required|string|max:255',
                'sn' => 'required|string|max:255',
                'employeeNumber' => 'required|string|max:255',
                'mail' => 'required|email',
                'userPassword' => 'required|string|min:6',
                'organizationalUnits' => 'required|array|min:1',
                'organizationalUnits.*' => 'required',
                'download' => 'boolean',
            ]);

            $userData = [
                'uid' => $request->uid,
                'givenName' => $request->givenName,
                'sn' => $request->sn,
                'employeeNumber' => $request->employeeNumber,
                'mail' => $request->mail,
                'userPassword' => $request->userPassword,
            ];

            $organizationalUnits = collect($request->organizationalUnits)->map(function ($item) {
                if (is_string($item)) {
                    return ['ou' => $item, 'role' => 'user'];
                }
                return $item;
            })->toArray();

            $ldifContent = $this->ldifService->generateUserLdif($userData, $organizationalUnits);

            // Se solicitado download, retornar arquivo
            if ($request->get('download', false)) {
                $filename = "usuario_{$request->uid}_" . now()->format('Y-m-d_H-i-s') . ".ldif";
                
                return Response::make($ldifContent, 200, [
                    'Content-Type' => 'text/plain',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ]);
            }

            // Caso contrário, retornar JSON com o conteúdo
            return response()->json([
                'success' => true,
                'data' => [
                    'ldif' => $ldifContent,
                    'filename' => "usuario_{$request->uid}_" . now()->format('Y-m-d_H-i-s') . ".ldif",
                ],
                'message' => 'LDIF gerado com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao gerar LDIF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplica um LDIF no sistema
     */
    public function applyLdif(Request $request): JsonResponse
    {
        $this->checkRootAccess($request);

        try {
            $request->validate([
                'ldif_content' => 'required|string',
            ]);

            $results = $this->ldifService->applyLdif($request->ldif_content);

            $successCount = collect($results)->where('success', true)->count();
            $errorCount = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => $errorCount === 0,
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($results),
                        'success' => $successCount,
                        'errors' => $errorCount,
                    ]
                ],
                'message' => $errorCount === 0 
                    ? "LDIF aplicado com sucesso ({$successCount} entradas processadas)"
                    : "LDIF aplicado com {$errorCount} erro(s) de {$successCount} entradas"
            ], $errorCount === 0 ? 200 : 207); // 207 Multi-Status para respostas parciais

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aplicar LDIF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload e aplicação de arquivo LDIF
     */
    public function uploadLdif(Request $request): JsonResponse
    {
        $this->checkRootAccess($request);

        try {
            $request->validate([
                'ldif_file' => 'required|file|mimes:ldif,txt|max:2048', // Max 2MB
            ]);

            $file = $request->file('ldif_file');
            $ldifContent = file_get_contents($file->getPathname());

            if (empty($ldifContent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arquivo LDIF está vazio'
                ], 422);
            }

            $results = $this->ldifService->applyLdif($ldifContent);

            $successCount = collect($results)->where('success', true)->count();
            $errorCount = collect($results)->where('success', false)->count();

            return response()->json([
                'success' => $errorCount === 0,
                'data' => [
                    'filename' => $file->getClientOriginalName(),
                    'results' => $results,
                    'summary' => [
                        'total' => count($results),
                        'success' => $successCount,
                        'errors' => $errorCount,
                    ]
                ],
                'message' => $errorCount === 0 
                    ? "Arquivo LDIF processado com sucesso ({$successCount} entradas)"
                    : "Arquivo LDIF processado com {$errorCount} erro(s) de {$successCount} entradas"
            ], $errorCount === 0 ? 200 : 207);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar arquivo LDIF: ' . $e->getMessage()
            ], 500);
        }
    }
}
