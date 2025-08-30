<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LdapUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Middleware\IsOUAdmin;

// Fluxo de redefinição de senha - domínio dedicado (precisa vir antes da rota '/')
Route::domain('contas.trocasenha.sei.pe.gov.br')->group(function () {
    Route::get('/', [ForgotPasswordController::class, 'showRequestForm'])->name('password.forgot');
    Route::post('/forgot', [ForgotPasswordController::class, 'sendResetLink'])->name('password.forgot.submit');
    Route::get('/{token}', [ResetPasswordController::class, 'showResetForm'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('password.reset.form');
    Route::post('/{token}', [ResetPasswordController::class, 'reset'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->name('password.reset.submit');
    Route::get('/sucesso', [ResetPasswordController::class, 'success'])->name('password.reset.success');
});

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

// Página Meu Perfil (edição de dados do próprio usuário)
Route::get('/meu-perfil', function () {
    return view('profile', [
        'uid' => auth()->user()->getFirstAttribute('uid') ?? '',
        'userRole' => \App\Services\RoleResolver::resolve(auth()->user()),
    ]);
})->middleware(['auth'])->name('profile');

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

// Rotas de autenticação LDAP
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
