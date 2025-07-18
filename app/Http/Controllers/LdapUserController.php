<?php

namespace App\Http\Controllers;

use App\Ldap\LdapUserModel;
use App\Ldap\OrganizationalUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OperationLog;
use App\Traits\ChecksRootAccess;

use LdapRecord\Connection;
use LdapRecord\Container;
use App\Services\RoleResolver;
use App\Utils\LdapUtils;

class LdapUserController extends Controller
{
    use ChecksRootAccess;

    /**
     * Safely get an attribute that might not be supported by the LDAP schema
     */
    private function safeGetAttribute($user, $attribute)
    {
        return $user->getFirstAttribute($attribute);
    }

    /**
     * Garantir que a entrada possua o objectClass necessário para gravar
     * atributos de roteamento de email (ex.: mailForwardingAddress).
     */
    private function ensureInetLocalMailRecipient($model): void
    {
        $classes = array_map('strtolower', $model->getAttribute('objectClass') ?? []);
        if (!in_array('inetlocalmailrecipient', $classes)) {
            $model->addAttribute('objectClass', 'inetLocalMailRecipient');
        }
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
                'uid' => 'required|string|max:255',
                'givenName' => 'required|string|max:255',
                'sn' => 'required|string|max:255',
                'employeeNumber' => 'required|string|max:255',
                'mail' => 'required|email',
                'userPassword' => 'required|string|min:6',
                'organizationalUnits' => 'array',
                // Cada item pode ser string (OU) ou objeto {ou, role}
                'organizationalUnits.*' => 'required',
            ]);

            $role = RoleResolver::resolve(auth()->user());
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $request->merge([
                    'organizationalUnits' => [$adminOu],
            ]);
            }

            // Verificar se já existem entradas com UID e mesma OU
            $existingEntries = LdapUserModel::where('uid', $request->uid)->get();

            $unitsInput = collect($request->organizationalUnits)->map(function($i){return is_string($i)? $i : ($i['ou'] ?? null);})->filter();

            foreach ($existingEntries as $entry){
                $existingOu = strtolower($entry->getFirstAttribute('ou'));
                if ($unitsInput->contains(fn($ou)=> strtolower($ou) === $existingOu)){
                return response()->json([
                    'success' => false,
                        'message' => "Usuário já existe na OU {$existingOu}"
                ], 422);
                }
            }

            // Verificar se a matrícula já existe
            $existingEmployee = LdapUserModel::where('employeeNumber', $request->employeeNumber)->first();
            if ($existingEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matrícula já cadastrada'
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

                $entry->setDn("uid={$request->uid},ou={$ou},{$baseDn}");
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
                'userPassword' => 'sometimes|required|string|min:6',
                'organizationalUnits' => 'sometimes|array',
                // aceitar string ou objeto {ou, role}
                'organizationalUnits.*' => 'required',
            ]);

            $users = LdapUserModel::where('uid', $uid)->get();
            $role = RoleResolver::resolve(auth()->user());

            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $belongs = $users->every(function($u) use ($adminOu){
                    return strtolower($u->getFirstAttribute('ou')) === strtolower($adminOu);
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

            // Mapear entradas existentes por OU (lowercase)
            $existingByOu = $users->keyBy(fn($u) => strtolower($u->getFirstAttribute('ou')));

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
                    
                    if ($request->has('userPassword')) {
                        $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                    }

                    // Nome completo
                    $user->setFirstAttribute('cn', trim(($request->givenName ?? $user->getFirstAttribute('givenName')) . ' ' . ($request->sn ?? $user->getFirstAttribute('sn'))));

                    // Papel
                    $user->setAttribute('employeeType', [$role]);

                    $user->save();
                
                    // Criar nova entrada nessa OU
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
                    
                    if ($request->has('userPassword')) {
                        $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($request->userPassword));
                    }

                    if ($request->has('givenName') || $request->has('sn')) {
                        $user->setFirstAttribute('cn', trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? '')));
            }
            $user->save();
                }
            }

            OperationLog::create([
                'operation' => 'update_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'ou' => ($units->isEmpty() ? $users->map(fn($u)=>$u->getFirstAttribute('ou'))->unique()->join(',') : $units->pluck('ou')->unique()->join(',')),
                'description' => 'Usuário ' . $uid . ' atualizado',
            ]);

            // Retornar primeira entrada consolidada
            $first = $users->first();
            $ous = $users->map(fn ($e) => $e->getFirstAttribute('ou'))->filter()->unique()->values();

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
                    return strtolower($u->getFirstAttribute('ou')) === strtolower($adminOu);
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
                'ou' => $users->map(fn($u)=>$u->getFirstAttribute('ou'))->unique()->join(','),
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
            $ous = OrganizationalUnit::all();

            // Se admin de OU, filtrar apenas sua própria OU
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $ous = $ous->filter(function ($ou) use ($adminOu) {
                    return strtolower($ou->getFirstAttribute('ou')) === strtolower($adminOu);
                });
            }

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
            $request->validate([
                'ou' => 'required|string|max:255',
                'description' => 'required|string|max:255',
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
            $ou->setFirstAttribute('description', $request->description);
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
                    'description' => $request->description,
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
            $request->validate([
                'description' => 'required|string|max:255',
            ]);

            $ou = OrganizationalUnit::where('ou', $ouName)->first();
            if (!$ou) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade organizacional não encontrada'
                ], 404);
            }

            $ou->setFirstAttribute('description', $request->description);
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
                    'description' => $request->description,
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
            $logs = OperationLog::orderBy('created_at', 'desc')->get();

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
                'ou' => $users->map(fn($u)=>$u->getFirstAttribute('ou'))->unique()->join(','),
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
}
