<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Alterar Senha</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', '-apple-system', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        window.USER_UID = "{{ $uid }}";
    </script>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-blue-50 min-h-screen flex items-center justify-center">
    <div id="app" class="bg-white p-10 rounded-2xl shadow-2xl w-full max-w-md border border-gray-100">
        <h1 class="text-3xl font-bold mb-8 text-center text-gray-900 flex items-center justify-center gap-3">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m0 0a2 2 0 012 2 2 2 0 01-2 2 2 2 0 01-2-2m0-4H9m6 0V5a2 2 0 00-2-2H9a2 2 0 00-2 2v2m0 4H7a2 2 0 00-2 2v4a2 2 0 002 2h10a2 2 0 002-2v-4a2 2 0 00-2-2H9z" />
            </svg>
            Alterar Senha
        </h1>

        <form @submit.prevent="changePassword" class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Nova Senha</label>
                <div class="relative">
                    <input v-model="password" :type="showNew ? 'text' : 'password'" required minlength="6" class="w-full pr-10 px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <button type="button" @click="showNew=!showNew" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700">
                        <svg v-if="!showNew" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.664 20.664 0 0 1 5.06-5.94m3.02-1.51A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a20.72 20.72 0 0 1-3.22 4.31M1 1l22 22"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirmar Senha</label>
                <div class="relative">
                    <input v-model="confirmPassword" :type="showConfirm ? 'text' : 'password'" required minlength="6" class="w-full pr-10 px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <button type="button" @click="showConfirm=!showConfirm" class="absolute inset-y-0 right-3 flex items-center text-gray-500 hover:text-gray-700">
                        <svg v-if="!showConfirm" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a20.664 20.664 0 0 1 5.06-5.94m3.02-1.51A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a20.72 20.72 0 0 1-3.22 4.31M1 1l22 22"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @{{ error }}
            </div>
            <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                @{{ success }}
            </div>
            <div class="flex gap-4 pt-4">
                <button type="button" @click="cancel" class="flex-1 px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium transition-all duration-200 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Cancelar
                </button>
                <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium transition-all duration-200 hover:shadow-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Salvar
                </button>
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
                    showNew: false,
                    showConfirm: false,
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