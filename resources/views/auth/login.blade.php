<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login LDAP</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded shadow w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Login LDAP</h1>
        @if ($errors->any())
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4">
                {{ $errors->first() }}
            </div>
        @endif
        <form action="{{ route('login.submit') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700">UID</label>
                <input name="uid" value="{{ old('uid') }}" required class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Senha</label>
                <input type="password" name="password" required class="w-full border rounded px-3 py-2" />
            </div>
            <div class="flex items-center">
                <input type="checkbox" name="remember" id="remember" class="mr-2" />
                <label for="remember" class="text-sm text-gray-700">Lembrar-me</label>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">Entrar</button>
        </form>
    </div>
</body>
</html> 