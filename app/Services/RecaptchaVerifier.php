<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RecaptchaVerifier
{
    public function verify(string $token, string $ip = null): bool
    {
        $secret = env('RECAPTCHA_SECRET_KEY');
        if (!$secret) {
            // Se não configurado, considerar falha para segurança
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]);

        if (!$response->ok()) {
            return false;
        }

        $json = $response->json();
        return isset($json['success']) && $json['success'] === true;
    }
}


