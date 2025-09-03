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

        \Log::info('Password Reset - Iniciando processo', [
            'email' => $validated['email'],
            'mailer' => env('MAIL_MAILER'),
            'smtp_host' => env('MAIL_HOST'),
            'smtp_port' => env('MAIL_PORT'),
            'smtp_encryption' => env('MAIL_ENCRYPTION'),
            'from_address' => env('MAIL_FROM_ADDRESS'),
            'from_name' => env('MAIL_FROM_NAME')
        ]);

        // Verificar existência do e-mail de redirecionamento no LDAP (campo description)
        $user = \App\Ldap\LdapUserModel::where('description', strtolower($validated['email']))->first();

        if ($user) {
            \Log::info('Password Reset - Usuário encontrado no LDAP', [
                'email' => $validated['email'],
                'user_dn' => $user->getDn()
            ]);

            try {
                $plainToken = $service->createTokenForEmail($validated['email']);
                $resetUrl = 'https://contas.trocasenha.sei.pe.gov.br/' . $plainToken;

                \Log::info('Password Reset - Enviando e-mail', [
                    'email' => $validated['email'],
                    'reset_url' => $resetUrl
                ]);

                Mail::to($validated['email'])->send(new PasswordResetLink($resetUrl));

                \Log::info('Password Reset - E-mail enviado com sucesso', [
                    'email' => $validated['email']
                ]);
            } catch (\Exception $e) {
                \Log::error('Password Reset - Erro ao enviar e-mail', [
                    'email' => $validated['email'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            \Log::info('Password Reset - Usuário não encontrado no LDAP', [
                'email' => $validated['email']
            ]);
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
                'mailer' => env('MAIL_MAILER'),
                'smtp_host' => env('MAIL_HOST'),
                'smtp_port' => env('MAIL_PORT')
            ]);
        } catch (\Exception $e) {
            $result = "❌ Erro ao enviar e-mail: " . $e->getMessage();
            \Log::error('Erro no teste de e-mail', [
                'email' => $validated['test_email'],
                'error' => $e->getMessage(),
                'mailer' => env('MAIL_MAILER'),
                'smtp_host' => env('MAIL_HOST'),
                'smtp_port' => env('MAIL_PORT')
            ]);
        }

        return back()->with('test_result', $result);
    }
}
