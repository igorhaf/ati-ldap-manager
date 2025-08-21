<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Meu Perfil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        window.PROFILE_UID = "{{ $uid }}";
    </script>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-blue-50 min-h-screen">
    <div id="app" class="max-w-3xl mx-auto py-10 px-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 p-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Meu Perfil</h1>
                <a href="/ldap-manager" class="text-blue-600 hover:text-blue-800">Voltar</a>
            </div>

            <form @submit.prevent="saveProfile" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuário (Login)</label>
                        <input v-model="form.uid" type="text" disabled class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input v-model="form.mail" type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                        <input v-model="form.givenName" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sobrenome</label>
                        <input v-model="form.sn" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                        <input v-model="form.employeeNumber" @input="maskCpf" inputmode="numeric" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="000.000.000-00">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nova Senha</label>
                        <div class="relative">
                            <input v-model="password" :type="showNew ? 'text' : 'password'" minlength="6" class="w-full pr-10 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" @click="showNew=!showNew" class="absolute inset-y-0 right-2 flex items-center text-gray-500 hover:text-gray-700">
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha</label>
                        <div class="relative">
                            <input v-model="passwordConfirm" :type="showConfirm ? 'text' : 'password'" minlength="6" class="w-full pr-10 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" @click="showConfirm=!showConfirm" class="absolute inset-y-0 right-2 flex items-center text-gray-500 hover:text-gray-700">
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
                </div>

                <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">@{{ error }}</div>
                <div v-if="success" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">@{{ success }}</div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                    <button type="button" @click="voltar" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 font-medium">Cancelar</button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const { createApp } = Vue;
        createApp({
            data(){
                return {
                    form: {
                        uid: window.PROFILE_UID,
                        givenName: '',
                        sn: '',
                        mail: '',
                        employeeNumber: ''
                    },
                    password: '',
                    passwordConfirm: '',
                    showNew: false,
                    showConfirm: false,
                    error: '',
                    success: ''
                }
            },
            mounted(){
                this.loadProfile();
            },
            methods:{
                async loadProfile(){
                    try{
                        const resp = await fetch(`/api/ldap/users/${encodeURIComponent(this.form.uid)}`);
                        const data = await resp.json();
                        if(data.success){
                            const u = data.data;
                            this.form.givenName = u.givenName || '';
                            this.form.sn = u.sn || '';
                            this.form.mail = u.mail || '';
                            this.form.employeeNumber = this.formatCpf(u.employeeNumber || '');
                        } else {
                            this.error = data.message || 'Erro ao carregar perfil';
                        }
                    } catch(e){
                        this.error = 'Erro de rede ao carregar perfil';
                    }
                },
                async saveProfile(){
                    this.error = this.success = '';
                    // Validação senha
                    if(this.password || this.passwordConfirm){
                        if(this.password.length < 6){
                            this.error = 'A senha deve ter pelo menos 6 caracteres';
                            return;
                        }
                        if(this.password !== this.passwordConfirm){
                            this.error = 'As senhas não coincidem';
                            return;
                        }
                    }

                    try{
                        const payload = {
                            givenName: this.form.givenName,
                            sn: this.form.sn,
                            mail: this.form.mail,
                            employeeNumber: (this.form.employeeNumber || '').replace(/\D+/g, '')
                        };
                        const resp = await fetch(`/api/ldap/users/${encodeURIComponent(this.form.uid)}`,{
                            method:'PUT',
                            headers:{
                                'Content-Type':'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await resp.json();
                        if(!data.success){
                            this.error = data.message || 'Erro ao salvar perfil';
                            return;
                        }

                        if(this.password){
                            const respPwd = await fetch(`/api/ldap/users/${encodeURIComponent(this.form.uid)}/password`,{
                                method:'PUT',
                                headers:{
                                    'Content-Type':'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({ userPassword: this.password })
                            });
                            const dataPwd = await respPwd.json();
                            if(!dataPwd.success){
                                this.error = dataPwd.message || 'Erro ao alterar senha';
                                return;
                            }
                        }

                        this.success = 'Perfil atualizado com sucesso';
                    }catch(e){
                        this.error = 'Erro de rede ao salvar perfil';
                    }
                },
                voltar(){
                    window.location.href = '/ldap-manager';
                },
                formatCpf(cpf){
                    const d = (cpf || '').replace(/\D+/g,'');
                    if(d.length===11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/,'$1.$2.$3-$4');
                    return cpf;
                },
                maskCpf(){
                    let digits = (this.form.employeeNumber || '').replace(/\D+/g, '');
                    if (digits.length > 11) digits = digits.slice(0,11);
                    let formatted = digits;
                    if (digits.length > 9) formatted = digits.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, (m,a,b,c,d)=> d?`${a}.${b}.${c}-${d}`:`${a}.${b}.${c}`);
                    else if (digits.length > 6) formatted = digits.replace(/(\d{3})(\d{3})(\d{0,3})/, (m,a,b,c)=> c?`${a}.${b}.${c}`:`${a}.${b}`);
                    else if (digits.length > 3) formatted = digits.replace(/(\d{3})(\d{0,3})/, (m,a,b)=> b?`${a}.${b}`:`${a}`);
                    this.form.employeeNumber = formatted;
                }
            }
        }).mount('#app');
    </script>
</body>
</html>

