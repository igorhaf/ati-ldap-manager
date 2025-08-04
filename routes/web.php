<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\IsOUAdmin;

Route::get('/', function () {
    return redirect()->route('ldap.manager');
});

// As rotas da API foram movidas para routes/api.php



// Rota principal do gerenciador
Route::get('/ldap-manager', function () {
    return view('ldap-simple', [
        'userRole' => \App\Services\RoleResolver::resolve(auth()->user())
    ]);
})->middleware(['auth', IsOUAdmin::class])->name('ldap.manager');

// Página de troca de senha para usuários comuns
Route::get('/password-change', function () {
    return view('password-change', [
        'uid' => auth()->user()->getFirstAttribute('uid') ?? ''
    ]);
})->middleware(['auth'])->name('password.change');

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

// Rota para testar detecção de host (apenas para debug)
Route::get('/debug-host', function (Illuminate\Http\Request $request) {
    $host = $request->getHttpHost();
    $host = explode(':', $host)[0]; // Remove porta
    
    $parts = explode('.', $host);
    $ou = count($parts) >= 2 ? strtolower($parts[0]) : null;
    
    return response()->json([
        'url' => $request->url(),
        'host' => $host,
        'ou' => $ou,
        'partes' => $parts
    ]);
})->name('debug.host');

// Rota para versão original (debug)
Route::get('/ldap-original', function () {
    return view('ldap-manager');
})->name('ldap.original');

// Rotas de autenticação LDAP
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
