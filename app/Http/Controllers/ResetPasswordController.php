<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PasswordResetService;

use App\Utils\LdapUtils;

class ResetPasswordController extends Controller
{
    private function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{64}$/', $token);
    }

    public function showResetForm(string $token)
    {
        if (!$this->isValidTokenFormat($token)) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'Link inválido ou expirado.']);
        }
        return view('auth.reset-password', ['token' => $token]);
    }

    public function reset(Request $request, PasswordResetService $service, string $token)
    {
        if (!$this->isValidTokenFormat($token)) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'Link inválido ou expirado.']);
        }
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'captcha' => ['required', 'captcha'],
        ]);

        $record = $service->findValidByToken($token);
        if (!$record) {
            return redirect()->route('password.forgot')->withErrors(['email' => 'Link inválido ou expirado.']);
        }

        // Encontrar usuário no LDAP pelo e-mail de redirecionamento salvo (campo description)
        $user = \App\Ldap\LdapUserModel::where('description', strtolower($record->email))->first();
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
