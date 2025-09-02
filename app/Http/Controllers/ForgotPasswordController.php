<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Services\PasswordResetService;

use App\Mail\PasswordResetLink;

class ForgotPasswordController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, PasswordResetService $service)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'captcha' => ['required', 'captcha'],
        ]);

        // Verificar existência do e-mail no LDAP
        $user = \App\Ldap\LdapUserModel::where('mail', strtolower($validated['email']))->first();

        if ($user) {
            $plainToken = $service->createTokenForEmail($validated['email']);
            $resetUrl = 'https://contas.trocasenha.sei.pe.gov.br/' . $plainToken;
            Mail::to($validated['email'])->send(new PasswordResetLink($resetUrl));
        }

        // Mensagem genérica para evitar enumeração de usuários
        return back()->with('status', 'Se o e-mail existir, enviamos um link para redefinição.');
    }
}
