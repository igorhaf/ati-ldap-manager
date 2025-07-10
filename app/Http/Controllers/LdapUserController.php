<?php

namespace App\Http\Controllers;

use App\Ldap\User;
use App\Ldap\OrganizationalUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\OperationLog;

use LdapRecord\Connection;
use LdapRecord\Container;
use App\Services\RoleResolver;

class LdapUserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(): JsonResponse
    {
        try {
            $role = RoleResolver::resolve(auth()->user());

            $users = User::all();

            // Se admin de OU, filtrar apenas entradas da sua OU
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $users = $users->filter(function ($u) use ($adminOu) {
                    return strtolower($u->getFirstAttribute('ou')) === strtolower($adminOu);
                });
            }
            
            // Agrupar por UID e consolidar as OUs para evitar duplicação de usuários na grid
            $formattedUsers = $users->groupBy(fn ($u) => $u->getFirstAttribute('uid'))
                ->map(function ($entries) {
                    $first = $entries->first();
                    $ous = $entries->map(fn ($e) => $e->getFirstAttribute('ou'))
                                    ->filter()
                                    ->unique()
                                    ->values();

                    return [
                        'dn' => $first->getDn(),
                        'uid' => $first->getFirstAttribute('uid'),
                        'givenName' => $first->getFirstAttribute('givenName'),
                        'sn' => $first->getFirstAttribute('sn'),
                        'cn' => $first->getFirstAttribute('cn'),
                        'fullName' => trim(($first->getFirstAttribute('givenName') ?? '') . ' ' . ($first->getFirstAttribute('sn') ?? '')),
                        'mail' => $first->getAttribute('mail') ?? [],
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
        try {
            $request->validate([
                'uid' => 'required|string|max:255',
                'givenName' => 'required|string|max:255',
                'sn' => 'required|string|max:255',
                'employeeNumber' => 'required|string|max:255',
                'mail' => 'required|array',
                'mail.*' => 'email',
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
            $existingEntries = User::where('uid', $request->uid)->get();

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
            $existingEmployee = User::where('employeeNumber', $request->employeeNumber)->first();
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

            foreach ($units as $unit) {
                $ou = $unit['ou'];
                $role = $unit['role'] ?? 'user';

                $entry = new User();
                $entry->setFirstAttribute('uid', $request->uid);
                $entry->setFirstAttribute('givenName', $request->givenName);
                $entry->setFirstAttribute('sn', $request->sn);
                $entry->setFirstAttribute('cn', $request->givenName . ' ' . $request->sn);
                $entry->setAttribute('mail', $request->mail);
                $entry->setFirstAttribute('employeeNumber', $request->employeeNumber);
                $entry->setFirstAttribute('userPassword', $request->userPassword);
                $entry->setFirstAttribute('ou', $ou);
                $entry->setAttribute('employeeType', [$role]);
                $entry->setDn("uid={$request->uid},ou={$ou},{$baseDn}");
                $entry->save();
            }

            OperationLog::create([
                'operation' => 'create_user',
                'entity' => 'User',
                'entity_id' => $request->uid,
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
        try {
            $user = User::where('uid', $uid)->first();
            
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
                    'mail' => $user->getAttribute('mail') ?? [],
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
        try {
            $request->validate([
                'givenName' => 'sometimes|required|string|max:255',
                'sn' => 'sometimes|required|string|max:255',
                'mail' => 'sometimes|required|array',
                'mail.*' => 'email',
                'userPassword' => 'sometimes|required|string|min:6',
                'organizationalUnits' => 'sometimes|array',
                'organizationalUnits.*' => 'string',
            ]);

            $users = User::where('uid', $uid)->get();
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

            // Aplicar alterações em todas as entradas do usuário
            foreach ($users as $user) {
                if ($request->has('givenName')) {
                    $user->setFirstAttribute('givenName', $request->givenName);
                }

                if ($request->has('sn')) {
                    $user->setFirstAttribute('sn', $request->sn);
                }

                if ($request->has('givenName') || $request->has('sn')) {
                    $givenName = $user->getFirstAttribute('givenName') ?? '';
                    $sn = $user->getFirstAttribute('sn') ?? '';
                    $user->setFirstAttribute('cn', trim($givenName . ' ' . $sn));
                }

                if ($request->has('mail')) {
                    $user->setAttribute('mail', $request->mail);
                }

                if ($request->has('userPassword')) {
                    $user->setFirstAttribute('userPassword', $request->userPassword);
                }

                // Atualizar atributo 'ou' apenas se fornecido; cada entrada possui sua própria OU
                if ($request->has('organizationalUnits')) {
                    // Se esta entrada não estiver mais incluída, podemos optar por excluir ou manter; por simplicidade, manteremos
                }

                $user->save();
            }

            OperationLog::create([
                'operation' => 'update_user',
                'entity' => 'User',
                'entity_id' => $uid,
                'description' => 'Usuário ' . $uid . ' atualizado em todas as OUs',
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
                    'mail' => $first->getAttribute('mail') ?? [],
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
        try {
            $users = User::where('uid', $uid)->get();
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
                'description' => 'Usuário ' . $uid . ' excluído de todas as OUs',
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
        try {
            $ous = OrganizationalUnit::all();

            $role = RoleResolver::resolve(auth()->user());
            if ($role === RoleResolver::ROLE_OU_ADMIN) {
                $adminOu = RoleResolver::getUserOu(auth()->user());
                $ous = $ous->filter(fn($ou) => strtolower($ou->getFirstAttribute('ou')) === strtolower($adminOu));
            }
            
            $formattedOus = $ous->map(function ($ou) {
                return [
                    'dn' => $ou->getDn(),
                    'ou' => $ou->getFirstAttribute('ou'),
                    'description' => $ou->getFirstAttribute('description'),
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
        try {
            $request->validate([
                'ou' => 'required|string|max:255',
                'description' => 'sometimes|string|max:255',
            ]);

            $ou = new OrganizationalUnit();
            $ou->setFirstAttribute('ou', $request->ou);
            
            if ($request->has('description')) {
                $ou->setFirstAttribute('description', $request->description);
            }

            $baseDn = config('ldap.connections.default.base_dn');
            $dn = "ou={$request->ou},{$baseDn}";
            $ou->setDn($dn);
            $ou->save();

            OperationLog::create([
                'operation' => 'create_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $ou->getFirstAttribute('ou'),
                'description' => 'OU ' . $ou->getFirstAttribute('ou') . ' criada',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $ou->getDn(),
                    'ou' => $ou->getFirstAttribute('ou'),
                    'description' => $ou->getFirstAttribute('description'),
                ],
                'message' => 'Unidade organizacional criada com sucesso'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\LdapRecord\LdapRecordException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro LDAP: ' . $e->getMessage()
            ], 503);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar unidade organizacional: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified organizational unit.
     */
    public function updateOrganizationalUnit(Request $request, string $ou): JsonResponse
    {
        try {
            $request->validate([
                'ou' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string|max:255',
            ]);

            // Buscar OU existente pelo atributo 'ou'
            $organizationalUnit = OrganizationalUnit::where('ou', $ou)->first();
            if (!$organizationalUnit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade organizacional não encontrada'
                ], 404);
            }

            // Atualizar atributos
            if ($request->has('ou')) {
                $organizationalUnit->setFirstAttribute('ou', $request->ou);
            }
            if ($request->has('description')) {
                $organizationalUnit->setFirstAttribute('description', $request->description);
            }

            // Se o nome mudou, atualizar o DN
            if ($request->has('ou')) {
                $baseDn = config('ldap.connections.default.base_dn');
                $organizationalUnit->setDn("ou={$request->ou},{$baseDn}");
            }

            $organizationalUnit->save();

            OperationLog::create([
                'operation' => 'update_ou',
                'entity' => 'OrganizationalUnit',
                'entity_id' => $organizationalUnit->getFirstAttribute('ou'),
                'description' => 'OU ' . $organizationalUnit->getFirstAttribute('ou') . ' atualizada',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $organizationalUnit->getDn(),
                    'ou' => $organizationalUnit->getFirstAttribute('ou'),
                    'description' => $organizationalUnit->getFirstAttribute('description'),
                ],
                'message' => 'Unidade organizacional atualizada com sucesso'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
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
        $logs = OperationLog::orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }
}
