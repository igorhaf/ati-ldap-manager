<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;

Route::get('/', function () {
    return redirect()->route('ldap.manager');
});

// As rotas da API foram movidas para routes/api.php



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
