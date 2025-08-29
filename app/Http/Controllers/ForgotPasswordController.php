<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Services\PasswordResetService;
use App\Services\RecaptchaVerifier;
use App\Mail\PasswordResetLink;

class ForgotPasswordController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request, PasswordResetService $service, RecaptchaVerifier $captcha)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'g-recaptcha-response' => ['required', 'string'],
        ]);

        $captchaOk = $captcha->verify($validated['g-recaptcha-response'], $request->ip());
        if (!$captchaOk) {
            return back()->withErrors(['email' => 'Falha na verificação do reCAPTCHA.'])->withInput();
        }

        // Verificar existência do e-mail no LDAP
        $user = \App\Ldap\LdapUserModel::where('mail', strtolower($validated['email']))->first();
        if (!$user) {
            return back()->withErrors(['email' => 'E-mail não encontrado.'])->withInput();
        }

        $plainToken = $service->createTokenForEmail($validated['email']);
        $resetUrl = 'https://contas.trocasenha.pe.gov.br/' . $plainToken;

        Mail::to($validated['email'])->send(new PasswordResetLink($resetUrl));

        return back()->with('status', 'Se o e-mail existir, enviamos um link para redefinição.');
    }
}


