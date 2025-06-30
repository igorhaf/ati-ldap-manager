<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rotas para o gerenciador de usuários LDAP
Route::prefix('ldap')->group(function () {
    // Rotas para usuários
    Route::get('/users', [LdapUserController::class, 'index']);
    Route::post('/users', [LdapUserController::class, 'store']);
    Route::get('/users/{uid}', [LdapUserController::class, 'show']);
    Route::put('/users/{uid}', [LdapUserController::class, 'update']);
    Route::delete('/users/{uid}', [LdapUserController::class, 'destroy']);
    
    // Rotas para unidades organizacionais
    Route::get('/organizational-units', [LdapUserController::class, 'getOrganizationalUnits']);
    Route::post('/organizational-units', [LdapUserController::class, 'createOrganizationalUnit']);
    Route::put('/organizational-units/{ou}', [LdapUserController::class, 'updateOrganizationalUnit']);
}); 