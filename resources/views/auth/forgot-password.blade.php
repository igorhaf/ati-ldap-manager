<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SEI Contas - Redefinir Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-indigo-50 to-blue-50 min-h-screen flex items-center justify-center">
    <div class="bg-white p-10 rounded-2xl shadow-2xl w-full max-w-md border border-gray-100">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Redefinir senha</h1>
        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 text-red-700 rounded">
                <ul class="list-disc ml-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('status'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 rounded text-sm">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('password.forgot.submit') }}" class="space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                @if (env('RECAPTCHA_SITE_KEY'))
                    <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_SITE_KEY') }}"></div>
                @else
                    <div class="border border-gray-300 rounded p-3 text-sm text-gray-600 bg-gray-50">
                        reCAPTCHA de exemplo (dev): marque a caixa para continuar
                    </div>
                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" class="h-4 w-4 border-gray-300 rounded" required>
                        Não sou um robô (exemplo)
                    </label>
                    <input type="hidden" name="g-recaptcha-response" value="mock">
                @endif
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white rounded-md hover:bg-blue-700">Enviar link</button>
        </form>
        <p class="text-xs text-gray-500 mt-4">Você receberá um link para redefinir sua senha, caso o e-mail exista.</p>
    </div>

    @if (env('RECAPTCHA_SITE_KEY'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</body>
</html>


