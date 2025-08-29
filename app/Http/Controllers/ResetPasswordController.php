<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PasswordResetService;
use App\Services\RecaptchaVerifier;
use App\Utils\LdapUtils;

class ResetPasswordController extends Controller
{
    public function showResetForm(string $token)
    {
        return view('auth.reset-password', ['token' => $token]);
    }

    public function reset(Request $request, PasswordResetService $service, RecaptchaVerifier $captcha, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'g-recaptcha-response' => ['required', 'string'],
        ]);

        $captchaOk = $captcha->verify($validated['g-recaptcha-response'], $request->ip());
        if (!$captchaOk) {
            return back()->withErrors(['password' => 'Falha na verificação do reCAPTCHA.'])->withInput();
        }

        $record = $service->findValidByToken($token);
        if (!$record) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'Link inválido ou expirado.']);
        }

        // Encontrar usuário no LDAP pelo e-mail salvo
        $user = \App\Ldap\LdapUserModel::where('mail', strtolower($record->email))->first();
        if (!$user) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'E-mail não encontrado.']);
        }

        $user->setFirstAttribute('userPassword', LdapUtils::hashSsha($validated['password']));
        $user->save();

        $service->markAsUsed($record);

        return redirect()->route('password.reset.success');
    }

    public function success()
    {
        return view('auth.reset-success');
    }
}


