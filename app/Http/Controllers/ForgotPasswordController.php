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

        // Verificar existência do e-mail de redirecionamento no LDAP (campo description)
        $user = \App\Ldap\LdapUserModel::where('description', strtolower($validated['email']))->first();

        if ($user) {
            $plainToken = $service->createTokenForEmail($validated['email']);
            $resetUrl = 'https://contas.trocasenha.sei.pe.gov.br/' . $plainToken;
            Mail::to($validated['email'])->send(new PasswordResetLink($resetUrl));
        }

        // Mensagem genérica para evitar enumeração de usuários
        return back()->with('status', 'Se o e-mail existir, enviamos um link para redefinição.');
    }

    public function testEmail(Request $request)
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            $testUrl = 'https://contas.trocasenha.sei.pe.gov.br/TESTE123';
            Mail::to($validated['test_email'])->send(new PasswordResetLink($testUrl));

            $result = "✅ E-mail enviado com sucesso para {$validated['test_email']}";
            \Log::info('Teste de e-mail realizado', [
                'email' => $validated['test_email'],
                'mailer' => config('mail.default'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port')
            ]);
        } catch (\Exception $e) {
            $result = "❌ Erro ao enviar e-mail: " . $e->getMessage();
            \Log::error('Erro no teste de e-mail', [
                'email' => $validated['test_email'],
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port')
            ]);
        }

        return back()->with('test_result', $result);
    }
}
