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
                <label class="block text-sm font-medium text-gray-700 mb-1">Código de verificação</label>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <input type="text" name="captcha" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Digite o código">
                    </div>
                    <div class="flex-shrink-0">
                        {!! captcha_img('flat') !!}
                    </div>
                </div>
            </div>
            <button type="submit" class="w-full py-2.5 bg-blue-600 text-white rounded-md hover:bg-blue-700">Enviar link</button>
        </form>
        
        <!-- Teste de envio de e-mail -->
        <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <h3 class="text-sm font-medium text-yellow-800 mb-2">🧪 Teste de E-mail</h3>
            <form method="POST" action="{{ route('password.test-email') }}" class="space-y-3">
                @csrf
                <div>
                    <input type="email" name="test_email" placeholder="E-mail para teste" required class="w-full px-3 py-2 border border-yellow-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 text-sm">
                </div>
                <button type="submit" class="w-full py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm">Testar Envio</button>
            </form>
            @if (session('test_result'))
                <div class="mt-2 p-2 bg-white rounded text-xs">
                    <strong>Resultado:</strong> {{ session('test_result') }}
                </div>
            @endif
        </div>
        <p class="text-xs text-gray-500 mt-4">Você receberá um link para redefinir sua senha, caso o e-mail exista.</p>
    </div>


</body>
</html>


