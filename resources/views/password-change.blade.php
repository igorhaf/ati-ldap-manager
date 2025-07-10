<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alterar Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        window.USER_UID = "{{ $uid }}";
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div id="app" class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Alterar Senha</h1>

        <form @submit.prevent="changePassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nova Senha</label>
                <input v-model="password" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha</label>
                <input v-model="confirmPassword" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div v-if="error" class="text-red-600 text-sm">@{{ error }}</div>
            <div v-if="success" class="text-green-600 text-sm">@{{ success }}</div>
            <div class="flex justify-end space-x-3">
                <button type="button" @click="cancel" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
            </div>
        </form>
    </div>

    <script>
        const { createApp } = Vue;
        createApp({
            data() {
                return {
                    password: '',
                    confirmPassword: '',
                    error: '',
                    success: ''
                }
            },
            methods: {
                async logoutAndRedirect() {
                    await fetch('/logout', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    window.location.href = '/login';
                },

                async changePassword() {
                    this.error = this.success = '';
                    if (this.password !== this.confirmPassword) {
                        this.error = 'As senhas não coincidem';
                        return;
                    }
                    try {
                        const response = await fetch(`/api/ldap/users/${window.USER_UID}/password`, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ userPassword: this.password })
                        });
                        const data = await response.json();
                        if (data.success) {
                            await this.logoutAndRedirect();
                        } else {
                            this.error = data.message || 'Erro ao alterar senha';
                        }
                    } catch (e) {
                        this.error = 'Erro de rede ao alterar senha';
                    }
                },

                async cancel() {
                    await this.logoutAndRedirect();
                }
            }
        }).mount('#app');
    </script>
</body>
</html> 