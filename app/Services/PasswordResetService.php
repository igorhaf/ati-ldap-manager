<?php

namespace App\Services;

use App\Models\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PasswordResetService
{
    public const EXPIRATION_MINUTES = 60; // Pode ser movido para config

    public function createTokenForEmail(string $email): string
    {
        // Remover tokens antigos do mesmo email
        PasswordReset::where('email', $email)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(64);
        $hash = hash('sha256', $plainToken);

        PasswordReset::create([
            'email' => strtolower(trim($email)),
            'token_hash' => $hash,
            'expires_at' => now()->addMinutes(static::EXPIRATION_MINUTES),
        ]);

        return $plainToken;
    }

    public function findValidByToken(string $plainToken): ?PasswordReset
    {
        $hash = hash('sha256', $plainToken);

        $record = PasswordReset::where('token_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (!$record) {
            return null;
        }

        if (now()->greaterThan($record->expires_at)) {
            return null;
        }

        return $record;
    }

    public function markAsUsed(PasswordReset $record): void
    {
        $record->used_at = now();
        $record->save();
    }
}


