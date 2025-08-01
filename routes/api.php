<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;
use App\Http\Middleware\IsRootUser;
use App\Http\Middleware\IsOUAdmin;
use App\Http\Middleware\IsSelfAccess;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rotas para o gerenciador de usuários LDAP
Route::middleware(['web','auth'])->prefix('ldap')->group(function () {

    // Rotas acessíveis apenas ao ROOT
    Route::middleware(IsRootUser::class)->group(function () {
        // Unidades organizacionais
        Route::post('/organizational-units', [LdapUserController::class, 'createOrganizationalUnit']);
        Route::put('/organizational-units/{ou}', [LdapUserController::class, 'updateOrganizationalUnit']);
        // Logs
        Route::get('/logs', [LdapUserController::class, 'getOperationLogs']);
    });

    // Rotas de leitura de OUs (root ou admin OU)
    Route::middleware(IsOUAdmin::class)->get('/organizational-units', [LdapUserController::class, 'getOrganizationalUnits']);

    // Rotas de usuários (root e admin OU)
    Route::middleware(IsOUAdmin::class)->group(function () {
    Route::get('/users', [LdapUserController::class, 'index']);
    Route::post('/users', [LdapUserController::class, 'store']);
    Route::put('/users/{uid}', [LdapUserController::class, 'update']);
    Route::delete('/users/{uid}', [LdapUserController::class, 'destroy']);
    });
    
    // Rotas LDIF (apenas ROOT)
    Route::middleware(IsRootUser::class)->group(function () {
        Route::post('/users/generate-ldif', [LdapUserController::class, 'generateUserLdif']);
        Route::post('/ldif/apply', [LdapUserController::class, 'applyLdif']);
        Route::post('/ldif/upload', [LdapUserController::class, 'uploadLdif']);
    });
    
    // Rota para usuário comum acessar seu próprio perfil (ou root/admin OU também passam)
    Route::middleware(IsSelfAccess::class)->get('/users/{uid}', [LdapUserController::class, 'show']);

    // Rota para usuário comum alterar sua própria senha
    Route::middleware(IsSelfAccess::class)->put('/users/{uid}/password', [LdapUserController::class, 'updatePassword']);
}); 