<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste LDAP Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Teste - Gerenciador LDAP</h1>
            
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Status do Sistema</h2>
                <p class="text-gray-600">Esta é uma página de teste para verificar se o Laravel está funcionando corretamente.</p>
                
                <div class="mt-6">
                    <button onclick="testApi()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Testar API
                    </button>
                </div>
                
                <div id="result" class="mt-4 p-4 border rounded hidden">
                    <!-- Resultado do teste será mostrado aqui -->
                </div>
            </div>
        </div>
    </div>

    <script>
        async function testApi() {
            const resultDiv = document.getElementById('result');
            resultDiv.classList.remove('hidden');
            resultDiv.innerHTML = '<p class="text-gray-600">Testando conexão...</p>';
            
            try {
                const response = await fetch('/api/ldap/users');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="text-green-700">
                            <p class="font-semibold">✅ Sucesso!</p>
                            <p>Encontrados ${data.data.length} usuários</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="text-red-700">
                            <p class="font-semibold">❌ Erro de API</p>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="text-red-700">
                        <p class="font-semibold">❌ Erro de Rede</p>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
    </script>
</body>
</html> 