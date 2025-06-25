<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;

Route::get('/', function () {
    return redirect()->route('ldap.manager');
});

// Rotas para o gerenciador de usuários LDAP
Route::prefix('api/ldap')->group(function () {
    // Rotas para usuários
    Route::get('/users', [LdapUserController::class, 'index']);
    Route::post('/users', [LdapUserController::class, 'store']);
    Route::get('/users/{uid}', [LdapUserController::class, 'show']);
    Route::put('/users/{uid}', [LdapUserController::class, 'update']);
    Route::delete('/users/{uid}', [LdapUserController::class, 'destroy']);
    
    // Rotas para unidades organizacionais
    Route::get('/organizational-units', [LdapUserController::class, 'getOrganizationalUnits']);
    Route::post('/organizational-units', [LdapUserController::class, 'createOrganizationalUnit']);
});

// Rota principal do gerenciador
Route::get('/ldap-manager', function () {
    return view('ldap-simple');
})->name('ldap.manager');

// Rota para phpinfo (apenas para debug)
Route::get('/phpinfo', function () {
    return response(phpinfo(), 200, ['Content-Type' => 'text/html']);
})->name('phpinfo');

// Rota de teste simples
Route::get('/test', function () {
    return view('ldap-test');
})->name('ldap.test');

// Rota de debug
Route::get('/debug', function () {
    return view('debug');
})->name('debug');

// Rota para versão original (debug)
Route::get('/ldap-original', function () {
    return view('ldap-manager');
})->name('ldap.original');
