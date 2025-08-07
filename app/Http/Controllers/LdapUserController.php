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
     * Verifica se um CPF já está em uso no sistema, excluindo opcionalmente um usuário específico
     */
    private function isCpfAlreadyUsed(string $cpf, ?string $excludeUid = null): array
    {
        $existingUsers = LdapUserModel::where('employeeNumber', $cpf)->get();
        
        if ($excludeUid) {
            // Filtrar para excluir o usuário que está sendo editado
            $existingUsers = $existingUsers->reject(function($user) use ($excludeUid) {
                return $user->getFirstAttribute('uid') === $excludeUid;
            });
        }
        
        if ($existingUsers->isEmpty()) {
            return ['exists' => false, 'user' => null];
        }
        
        $conflictUser = $existingUsers->first();
        $conflictOus = $existingUsers->map(fn($u) => $this->extractOu($u))->filter()->unique()->values();
        
        return [
            'exists' => true,
            'user' => $conflictUser,
            'uid' => $conflictUser->getFirstAttribute('uid'),
            'name' => trim(($conflictUser->getFirstAttribute('givenName') ?? '') . ' ' . ($conflictUser->getFirstAttribute('sn') ?? '')),
            'ous' => $conflictOus->toArray()
        ];
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

            // Verificar se o CPF já está em uso no sistema
            $cpfCheck = $this->isCpfAlreadyUsed($request->employeeNumber);
            if ($cpfCheck['exists']) {
                $conflictUser = $cpfCheck['user'];
                $conflictName = $cpfCheck['name'];
                $conflictUid = $cpfCheck['uid'];
                $conflictOus = implode(', ', $cpfCheck['ous']);
                
                return response()->json([
                    'success' => false,
                    'message' => "CPF {$request->employeeNumber} já está cadastrado para o usuário '{$conflictName}' (UID: {$conflictUid}) na(s) OU(s): {$conflictOus}"
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
                'employeeNumber' => 'sometimes|required|string|max:255',
                'userPassword' => 'sometimes|nullable|string|min:6',
                'organizationalUnits' => 'sometimes|array',
                // aceitar string ou objeto {ou, role}
                'organizationalUnits.*' => 'required',
            ]);

            // Verificar se o CPF já está em uso por outro usuário (se CPF foi fornecido)
            if ($request->has('employeeNumber')) {
                $cpfCheck = $this->isCpfAlreadyUsed($request->employeeNumber, $uid);
                if ($cpfCheck['exists']) {
                    $conflictName = $cpfCheck['name'];
                    $conflictUid = $cpfCheck['uid'];
                    $conflictOus = implode(', ', $cpfCheck['ous']);
                    
                    return response()->json([
                        'success' => false,
                        'message' => "CPF {$request->employeeNumber} já está cadastrado para o usuário '{$conflictName}' (UID: {$conflictUid}) na(s) OU(s): {$conflictOus}"
                    ], 422);
                }
            }

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

            // Mapear entradas existentes por OU (lowercase)
            $existingByOu = $users->keyBy(fn($u) => strtolower($this->extractOu($u) ?? ''));

            $baseDn = config('ldap.connections.default.base_dn');

            // Organizações enviadas na requisição
            $units = collect($request->organizationalUnits ?? [])->map(function($i){
                if (is_string($i)) return ['ou'=>$i,'role'=>'user'];
                return [
                    'ou' => $i['ou'],
                    'role' => $i['role'] ?? 'user'
                ];
            });

            // Loop organizações solicitadas
            foreach ($units as $unit) {
                $ouLower = strtolower($unit['ou']);
                $role     = $unit['role'];

                if ($existingByOu->has($ouLower)) {
                    // Atualizar entrada existente
                    $user = $existingByOu[$ouLower];

                    if ($request->has('givenName')) $user->setFirstAttribute('givenName', $request->givenName);
                    if ($request->has('sn'))       $user->setFirstAttribute('sn',       $request->sn);
                    if ($request->has('mail'))     $user->setFirstAttribute('mail',     $request->mail);
                    if ($request->has('employeeNumber')) $user->setFirstAttribute('employeeNumber', $request->employeeNumber);
                    
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
                    $entry->setFirstAttribute('employeeNumber', $request->get('employeeNumber', $users->first()->getFirstAttribute('employeeNumber')));
                    if ($request->has('userPassword') && !empty($request->userPassword)) {
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
                    if ($request->has('employeeNumber')) $user->setFirstAttribute('employeeNumber', $request->employeeNumber);
                    
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

            OperationLog::create([
                'operation' => 'update_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => ($units->isEmpty() ? $users->map(fn($u)=>$this->extractOu($u))->filter()->unique()->join(',') : $units->pluck('ou')->unique()->join(',')),
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
                'description' => 'Usuário ' . $uid . ' excluído',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuário removido com sucesso de todas as OUs'
            ]);

        } catch (\Exception $e) {
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
                    'message' => 'Acesso negado: apenas usuários root podem visualizar organizações'
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
                'message' => 'Organizações carregadas com sucesso'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar organizações: ' . $e->getMessage()
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
                    'message' => 'Acesso negado: apenas usuários root podem criar organizações'
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
                    'message' => 'Acesso negado: apenas usuários root podem editar organizações'
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

            if ($request->has('description')) {
                $ou->setFirstAttribute('description', $request->description);
            }
            $ou->save();

            OperationLog::create([
                'operation' => 'update_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $ouName,
                'ou' => $ouName,
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

            return response()->json([
                'success' => true,
                'data' => $logs,
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
                'description' => 'Senha do usuário ' . $uid . ' alterada',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Senha alterada com sucesso'
            ]);

        } catch (\Exception $e) {
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
