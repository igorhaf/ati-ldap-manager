<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LDAP Manager - Simples</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="app">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 py-6">
                <h1 class="text-3xl font-bold text-gray-900">LDAP Manager - Versão Simples</h1>
                <p class="text-gray-600">Testando Vue.js sem conflitos</p>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-8">
            <div v-if="message" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
                <p class="text-blue-800">@{{ message }}</p>
            </div>

            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-medium mb-4">Testes</h2>
                <button @click="test" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Testar Vue
                </button>
                <button @click="loadData" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 ml-2">
                    Testar API
                </button>
            </div>
        </main>
    </div>

    <script>
        console.log('Vue disponível:', typeof window.Vue);
        
        if (window.Vue) {
            const { createApp } = window.Vue;
            
            createApp({
                data() {
                    return {
                        message: 'Vue.js carregado e funcionando!'
                    }
                },
                methods: {
                    test() {
                        this.message = 'Teste executado às ' + new Date().toLocaleTimeString();
                    },
                    async loadData() {
                        try {
                            const response = await fetch('/api/ldap/users');
                            const data = await response.json();
                            this.message = 'API respondeu: ' + (data.success ? 'Sucesso' : data.message);
                        } catch (error) {
                            this.message = 'Erro na API: ' + error.message;
                        }
                    }
                }
            }).mount('#app');
        }
    </script>
</body>
</html>
