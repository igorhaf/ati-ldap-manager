<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador LDAP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="app">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Gerenciador LDAP</h1>
                        <p class="text-gray-600">Gerenciamento de Usuários e Unidades Organizacionais</p>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showCreateUserModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            ➕ Novo Usuário
                        </button>
                        <button @click="showCreateOuModal = true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            📁 Nova OU
                        </button>
                    </div>
                </div>
            </div>
        </header>

                 <!-- Status Panel -->
         <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
             <div v-if="systemStatus" class="mb-8">
                 <div :class="systemStatus.type === 'error' ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'" class="border rounded-lg p-4">
                     <div class="flex items-start">
                         <div class="flex-shrink-0">
                             <span v-if="systemStatus.type === 'error'" class="text-red-400 text-xl">❌</span>
                             <span v-else class="text-green-400 text-xl">✅</span>
                         </div>
                         <div class="ml-3 flex-1">
                             <h3 :class="systemStatus.type === 'error' ? 'text-red-800' : 'text-green-800'" class="text-sm font-medium">
                                 @{{ systemStatus.title }}
                             </h3>
                             <div :class="systemStatus.type === 'error' ? 'text-red-700' : 'text-green-700'" class="mt-1 text-sm">
                                 <p>@{{ systemStatus.message }}</p>
                                 <div v-if="systemStatus.details" class="mt-2">
                                     <p class="font-medium">Detalhes:</p>
                                     <ul class="mt-1 list-disc list-inside space-y-1">
                                         <li v-for="detail in systemStatus.details" :key="detail">@{{ detail }}</li>
                                     </ul>
                                 </div>
                                 <div v-if="systemStatus.suggestions" class="mt-3">
                                     <p class="font-medium">Sugestões:</p>
                                     <ul class="mt-1 list-disc list-inside space-y-1">
                                         <li v-for="suggestion in systemStatus.suggestions" :key="suggestion">@{{ suggestion }}</li>
                                     </ul>
                                 </div>
                             </div>
                         </div>
                         <div class="ml-auto pl-3">
                             <button @click="systemStatus = null" class="text-gray-500 hover:text-gray-700">✖️</button>
                         </div>
                     </div>
                 </div>
             </div>

             <!-- Tabs -->
             <div class="border-b border-gray-200 mb-8">
                 <nav class="-mb-px flex space-x-8">
                     <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                         👥 Usuários
                     </button>
                     <button @click="activeTab = 'organizational-units'" :class="activeTab === 'organizational-units' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                         🏢 Unidades Organizacionais
                     </button>
                 </nav>
             </div>

             <!-- Users Tab -->
             <div v-if="activeTab === 'users'" class="space-y-6">
                 <!-- Search and Filters -->
                 <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                     <div class="flex flex-col sm:flex-row gap-4">
                         <div class="flex-1">
                             <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar Usuários</label>
                             <input v-model="searchTerm" type="text" id="search" placeholder="Buscar por nome, UID ou matrícula..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                         </div>
                         <div class="flex items-end">
                             <button @click="loadUsers" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-medium">
                                 🔄 Atualizar
                             </button>
                         </div>
                     </div>
                 </div>

                 <!-- Users Table -->
                 <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h3 class="text-lg font-medium text-gray-900">Lista de Usuários (@{{ filteredUsers.length }})</h3>
                     </div>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">UID</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matrícula</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Emails</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <tr v-for="user in filteredUsers" :key="user.uid" class="hover:bg-gray-50">
                                     <td class="px-6 py-4 text-sm font-medium text-gray-900">@{{ user.uid }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ user.fullName }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ user.employeeNumber }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">
                                         <div v-for="email in user.mail" :key="email" class="text-xs">@{{ email }}</div>
                                     </td>
                                     <td class="px-6 py-4 text-sm font-medium">
                                         <button @click="editUser(user)" class="text-blue-600 hover:text-blue-900 mr-3">✏️ Editar</button>
                                         <button @click="deleteUser(user.uid)" class="text-red-600 hover:text-red-900">🗑️ Excluir</button>
                                     </td>
                                 </tr>
                                 <tr v-if="filteredUsers.length === 0">
                                     <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                         <div v-if="users.length === 0">📭 Nenhum usuário encontrado</div>
                                         <div v-else>🔍 Nenhum usuário corresponde à busca</div>
                                     </td>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 </div>
             </div>

             <!-- Organizational Units Tab -->
             <div v-if="activeTab === 'organizational-units'" class="space-y-6">
                 <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h3 class="text-lg font-medium text-gray-900">Unidades Organizacionais (@{{ organizationalUnits.length }})</h3>
                     </div>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DN</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <tr v-for="ou in organizationalUnits" :key="ou.dn" class="hover:bg-gray-50">
                                     <td class="px-6 py-4 text-sm font-medium text-gray-900">@{{ ou.ou }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ ou.description || '-' }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-500 font-mono">@{{ ou.dn }}</td>
                                 </tr>
                                 <tr v-if="organizationalUnits.length === 0">
                                     <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                         📁 Nenhuma unidade organizacional encontrada
                                     </td>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 </div>
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
                        activeTab: 'users',
                        users: [],
                        organizationalUnits: [],
                        searchTerm: '',
                        showCreateUserModal: false,
                        showCreateOuModal: false,
                        systemStatus: {
                            type: 'success',
                            title: 'Sistema Inicializado',
                            message: 'Vue.js carregado com sucesso! Carregando dados do LDAP...'
                        },
                        notification: {
                            show: false,
                            message: '',
                            type: 'success'
                        }
                    }
                },
                computed: {
                    filteredUsers() {
                        if (!this.searchTerm) return this.users;
                        
                        const term = this.searchTerm.toLowerCase();
                        return this.users.filter(user => 
                            user.uid.toLowerCase().includes(term) ||
                            user.fullName.toLowerCase().includes(term) ||
                            user.employeeNumber.toLowerCase().includes(term)
                        );
                    }
                },
                mounted() {
                    console.log('✅ LDAP Manager montado com sucesso!');
                    this.loadUsers();
                    this.loadOrganizationalUnits();
                },
                methods: {
                    async loadUsers() {
                        console.log('🔄 Carregando usuários...');
                        try {
                            const response = await fetch('/api/ldap/users');
                            const data = await response.json();
                            
                            if (data.success) {
                                this.users = data.data;
                                this.systemStatus = null;
                                console.log('✅ Usuários carregados:', data.data.length);
                            } else {
                                console.log('⚠️ Erro na API:', data.message);
                                this.handleApiError('Erro de Conexão LDAP', data.message);
                            }
                        } catch (error) {
                            console.log('❌ Erro de rede:', error);
                            this.handleNetworkError('Erro ao carregar usuários', error);
                        }
                    },
                    
                    async loadOrganizationalUnits() {
                        console.log('🔄 Carregando OUs...');
                        try {
                            const response = await fetch('/api/ldap/organizational-units');
                            const data = await response.json();
                            
                            if (data.success) {
                                this.organizationalUnits = data.data;
                                console.log('✅ OUs carregadas:', data.data.length);
                            } else {
                                console.log('⚠️ Erro na API OU:', data.message);
                                this.handleApiError('Erro de Conexão LDAP', data.message);
                            }
                        } catch (error) {
                            console.log('❌ Erro de rede OU:', error);
                            this.handleNetworkError('Erro ao carregar unidades organizacionais', error);
                        }
                    },
                    
                    async deleteUser(uid) {
                        if (!confirm('Tem certeza que deseja excluir este usuário?')) return;
                        
                        try {
                            const response = await fetch(`/api/ldap/users/${uid}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.showNotification('Usuário excluído com sucesso', 'success');
                                this.loadUsers();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao excluir usuário', 'error');
                        }
                    },
                    
                    editUser(user) {
                        this.showNotification('Funcionalidade de edição será implementada em breve', 'success');
                    },
                    
                    showNotification(message, type = 'success') {
                        this.notification = {
                            show: true,
                            message,
                            type
                        };
                        
                        setTimeout(() => {
                            this.notification.show = false;
                        }, 3000);
                    },

                    handleApiError(title, message) {
                        let suggestions = [];
                        let details = [];

                        if (message.includes('Invalid credentials')) {
                            title = 'Credenciais LDAP Inválidas';
                            details = [
                                'O servidor LDAP rejeitou as credenciais de conexão',
                                'Verifique as configurações no arquivo .env'
                            ];
                            suggestions = [
                                'Verifique se LDAP_USERNAME e LDAP_PASSWORD estão corretos',
                                'Confirme se o usuário tem permissões para acessar o diretório LDAP',
                                'Teste a conexão com phpLDAPadmin: http://localhost:8080'
                            ];
                        } else if (message.includes('Connection refused') || message.includes('timeout')) {
                            title = 'Erro de Conexão com Servidor LDAP';
                            details = [
                                'Não foi possível estabelecer conexão com o servidor LDAP',
                                'Host: localhost:389',
                                'Verifique se o Docker está rodando'
                            ];
                            suggestions = [
                                'Execute: docker-compose up -d',
                                'Verifique se a porta 389 não está bloqueada',
                                'Confirme se o container OpenLDAP está funcionando'
                            ];
                        } else {
                            details = [message];
                            suggestions = [
                                'Verifique os logs do Laravel para mais detalhes',
                                'Confirme se todas as configurações LDAP estão corretas'
                            ];
                        }

                        this.systemStatus = {
                            type: 'error',
                            title,
                            message: 'Não foi possível conectar ao servidor LDAP.',
                            details,
                            suggestions
                        };
                    },

                    handleNetworkError(title, error) {
                        this.systemStatus = {
                            type: 'error',
                            title,
                            message: 'Erro de comunicação com o servidor.',
                            details: [
                                'Falha na comunicação HTTP com o servidor Laravel',
                                error.message || 'Erro desconhecido'
                            ],
                            suggestions: [
                                'Verifique se o servidor Laravel está em execução',
                                'Confirme sua conexão de rede',
                                'Recarregue a página e tente novamente'
                            ]
                        };
                    }
                }
            }).mount('#app');
        }
    </script>
</body>
</html>
