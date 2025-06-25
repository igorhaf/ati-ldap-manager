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
                    'uid' => $user->uid,
                    'givenName' => $user->givenName,
                    'sn' => $user->sn,
                    'cn' => $user->cn,
                    'fullName' => $user->fullName,
                    'mail' => $user->mail,
                    'employeeNumber' => $user->employeeNumber,
                    'emailForwardAddress' => $user->emailForwardAddress,
                    'organizationalUnits' => $user->organizationalUnits,
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
                'emailForwardAddress' => 'array',
                'emailForwardAddress.*' => 'email',
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
            $user->uid = $request->uid;
            $user->givenName = $request->givenName;
            $user->sn = $request->sn;
            $user->cn = $request->givenName . ' ' . $request->sn;
            $user->mail = $request->mail;
            $user->employeeNumber = $request->employeeNumber;
            $user->userPassword = $request->userPassword;
            
            if ($request->has('organizationalUnits')) {
                $user->organizationalUnits = $request->organizationalUnits;
            }
            
            if ($request->has('emailForwardAddress')) {
                $user->emailForwardAddress = $request->emailForwardAddress;
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
                    'uid' => $user->uid,
                    'givenName' => $user->givenName,
                    'sn' => $user->sn,
                    'cn' => $user->cn,
                    'fullName' => $user->fullName,
                    'mail' => $user->mail,
                    'employeeNumber' => $user->employeeNumber,
                    'emailForwardAddress' => $user->emailForwardAddress,
                    'organizationalUnits' => $user->organizationalUnits,
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
                    'uid' => $user->uid,
                    'givenName' => $user->givenName,
                    'sn' => $user->sn,
                    'cn' => $user->cn,
                    'fullName' => $user->fullName,
                    'mail' => $user->mail,
                    'employeeNumber' => $user->employeeNumber,
                    'emailForwardAddress' => $user->emailForwardAddress,
                    'organizationalUnits' => $user->organizationalUnits,
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
                'emailForwardAddress' => 'sometimes|array',
                'emailForwardAddress.*' => 'email',
            ]);

            $user = User::where('uid', $uid)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ($request->has('givenName')) {
                $user->givenName = $request->givenName;
            }

            if ($request->has('sn')) {
                $user->sn = $request->sn;
            }

            if ($request->has('givenName') || $request->has('sn')) {
                $user->cn = ($user->givenName ?? '') . ' ' . ($user->sn ?? '');
            }

            if ($request->has('mail')) {
                $user->mail = $request->mail;
            }

            if ($request->has('userPassword')) {
                $user->userPassword = $request->userPassword;
            }

            if ($request->has('organizationalUnits')) {
                $user->organizationalUnits = $request->organizationalUnits;
            }

            if ($request->has('emailForwardAddress')) {
                $user->emailForwardAddress = $request->emailForwardAddress;
            }

            $user->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $user->getDn(),
                    'uid' => $user->uid,
                    'givenName' => $user->givenName,
                    'sn' => $user->sn,
                    'cn' => $user->cn,
                    'fullName' => $user->fullName,
                    'mail' => $user->mail,
                    'employeeNumber' => $user->employeeNumber,
                    'emailForwardAddress' => $user->emailForwardAddress,
                    'organizationalUnits' => $user->organizationalUnits,
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
                    'ou' => $ou->ou,
                    'description' => $ou->description,
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

            $existingOu = OrganizationalUnit::where('ou', $request->ou)->first();
            if ($existingOu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unidade organizacional já existe'
                ], 422);
            }

            $ou = new OrganizationalUnit();
            $ou->ou = $request->ou;
            
            if ($request->has('description')) {
                $ou->description = $request->description;
            }

            $baseDn = config('ldap.connections.default.base_dn');
            $ou->setDn("ou={$request->ou},{$baseDn}");

            $ou->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'dn' => $ou->getDn(),
                    'ou' => $ou->ou,
                    'description' => $ou->description,
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
}
