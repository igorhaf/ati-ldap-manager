<?php

namespace App\Http\Controllers;

use App\Ldap\User;
use App\Ldap\OrganizationalUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use LdapRecord\Connection;
use LdapRecord\Container;

class LdapUserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(): JsonResponse
    {
        try {
            $users = User::all();
            
            $formattedUsers = $users->map(function ($user) {
                return [
                    'dn' => $user->getDn(),
                    'uid' => $user->getFirstAttribute('uid'),
                    'givenName' => $user->getFirstAttribute('givenName'),
                    'sn' => $user->getFirstAttribute('sn'),
                    'cn' => $user->getFirstAttribute('cn'),
                    'fullName' => trim(($user->getFirstAttribute('givenName') ?? '') . ' ' . ($user->getFirstAttribute('sn') ?? '')),
                    'mail' => $user->getAttribute('mail') ?? [],
                    'employeeNumber' => $user->getFirstAttribute('employeeNumber'),
                    'organizationalUnits' => $user->getAttribute('ou') ?? [],
                ];
            });

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
                'organizationalUnits.*' => 'string',
            ]);

            // Verificar se o UID já existe
            $existingUser = User::where('uid', $request->uid)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário com este UID já existe'
                ], 422);
            }

            // Verificar se a matrícula já existe
            $existingEmployee = User::where('employeeNumber', $request->employeeNumber)->first();
            if ($existingEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Matrícula já cadastrada'
                ], 422);
            }

            $user = new User();
            $user->setFirstAttribute('uid', $request->uid);
            $user->setFirstAttribute('givenName', $request->givenName);
            $user->setFirstAttribute('sn', $request->sn);
            $user->setFirstAttribute('cn', $request->givenName . ' ' . $request->sn);
            $user->setAttribute('mail', $request->mail);
            $user->setFirstAttribute('employeeNumber', $request->employeeNumber);
            $user->setFirstAttribute('userPassword', $request->userPassword);
            
            if ($request->has('organizationalUnits')) {
                $user->setAttribute('ou', $request->organizationalUnits);
            }

            // Definir DN baseado na primeira unidade organizacional ou DN padrão
            $baseDn = config('ldap.connections.default.base_dn');
            $ou = $request->organizationalUnits[0] ?? 'users';
            $user->setDn("uid={$request->uid},ou={$ou},{$baseDn}");

            $user->save();

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

            $user = User::where('uid', $uid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

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

            if ($request->has('organizationalUnits')) {
                $user->setAttribute('ou', $request->organizationalUnits);
            }

            $user->save();

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
            $user = User::where('uid', $uid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Usuário removido com sucesso'
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
}
