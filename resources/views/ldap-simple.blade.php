<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
                            📁 Nova Unidade Organizacional
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
                     <button @click="activeTab = 'logs'" :class="activeTab === 'logs' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                         📜 Logs
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
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unidades</th>
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
                                         <div v-for="ou in user.organizationalUnits" :key="ou" class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mr-1 mb-1">@{{ ou }}</div>
                                     </td>
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
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <tr v-for="ou in organizationalUnits" :key="ou.dn" class="hover:bg-gray-50">
                                     <td class="px-6 py-4 text-sm font-medium text-gray-900">@{{ ou.ou }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ ou.description || '-' }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-500 font-mono">@{{ ou.dn }}</td>
                                     <td class="px-6 py-4 text-sm font-medium">
                                         <button @click="editOu(ou)" class="text-blue-600 hover:text-blue-900">✏️ Editar</button>
                                     </td>
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

             <!-- Logs Tab -->
             <div v-if="activeTab === 'logs'" class="space-y-6">
                 <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h3 class="text-lg font-medium text-gray-900">Logs de Operações (@{{ logs.length }})</h3>
                     </div>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Operação</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entidade</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entidade ID</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                 <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50">
                                     <td class="px-6 py-4 text-sm font-medium text-gray-900">@{{ log.id }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.operation }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.entity }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.entity_id }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.description }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-500">@{{ new Date(log.created_at).toLocaleString() }}</td>
                                 </tr>
                                 <tr v-if="logs.length === 0">
                                     <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Nenhum log encontrado</td>
                                 </tr>
                             </tbody>
                         </table>
                     </div>
                 </div>
             </div>
         </main>

        <!-- Create User Modal -->
        <div v-if="showCreateUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">➕ Criar Novo Usuário</h3>
                        <button @click="showCreateUserModal = false" class="text-gray-400 hover:text-gray-600">
                            ✖️
                        </button>
                    </div>
                    
                    <form @submit.prevent="createUser" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UID (Login)</label>
                                <input v-model="newUser.uid" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Matrícula</label>
                                <input v-model="newUser.employeeNumber" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                <input v-model="newUser.givenName" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sobrenome</label>
                                <input v-model="newUser.sn" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                                <input v-model="newUser.userPassword" type="password" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Principal</label>
                                <input v-model="newUser.mail[0]" type="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Emails Adicionais</label>
                            <div class="space-y-2">
                                <div v-for="(email, index) in newUser.mail.slice(1)" :key="index" class="flex gap-2">
                                    <input v-model="newUser.mail[index + 1]" type="email" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button @click="removeEmail(index + 1)" type="button" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">🗑️</button>
                                </div>
                                <button @click="addEmail" type="button" class="text-blue-600 hover:text-blue-800 text-sm">➕ Adicionar Email</button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Organizacionais</label>
                            <div class="space-y-2">
                                <div v-for="(ou, index) in newUser.organizationalUnits" :key="index" class="flex items-center space-x-2 mt-1">
                                    <select v-model="newUser.organizationalUnits[index]" class="flex-1 border rounded px-3 py-2">
                                        <option value="" disabled>Selecione...</option>
                                        <option v-for="ouOpt in organizationalUnits" :value="ouOpt.ou">@{{ ouOpt.ou }}</option>
                                    </select>
                                    <button v-if="index>0" @click="newUser.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
                                </div>
                                <button @click="newUser.organizationalUnits.push('')" class="mt-2 text-blue-600">+ adicionar OU</button>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button @click="showCreateUserModal = false" type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Criar Usuário</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create UO Modal -->
        <div v-if="showCreateOuModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">📁 Criar Nova Unidade Organizacional</h3>
                        <button @click="showCreateOuModal = false" class="text-gray-400 hover:text-gray-600">
                            ✖️
                        </button>
                    </div>
                    
                    <form @submit.prevent="createOu" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Unidade Organizacional</label>
                            <input v-model="newOu.ou" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea v-model="newOu.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button @click="showCreateOuModal = false" type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Criar Unidade Organizacional</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div v-if="notification.show" :class="notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            @{{ notification.message }}
        </div>

        <!-- Modal edição usuário -->
        <div v-if="showEditUserModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white w-full max-w-lg rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-semibold mb-4">✏️ Editar Usuário</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">UID (não editável)</label>
                        <input type="text" v-model="editUserData.uid" class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100" disabled />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome</label>
                            <input type="text" v-model="editUserData.givenName" class="mt-1 block w-full border rounded px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sobrenome</label>
                            <input type="text" v-model="editUserData.sn" class="mt-1 block w-full border rounded px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Matrícula (não editável)</label>
                        <input type="text" v-model="editUserData.employeeNumber" class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100" disabled />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">E-mails</label>
                        <div v-for="(email, index) in editUserData.mail" :key="index" class="flex items-center space-x-2 mt-1">
                            <input type="email" v-model="editUserData.mail[index]" class="flex-1 border rounded px-3 py-2" />
                            <button v-if="index>0" @click="editUserData.mail.splice(index,1)" class="text-red-500">✖</button>
                        </div>
                        <button @click="editUserData.mail.push('')" class="mt-2 text-blue-600">+ adicionar email</button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Unidades Organizacionais</label>
                        <div v-for="(ou,index) in editUserData.organizationalUnits" :key="index" class="flex items-center space-x-2 mt-1">
                            <select v-model="editUserData.organizationalUnits[index]" class="flex-1 border rounded px-3 py-2">
                                <option value="" disabled>Selecione...</option>
                                <option v-for="ouOpt in organizationalUnits" :value="ouOpt.ou">@{{ ouOpt.ou }}</option>
                            </select>
                            <button v-if="index>0" @click="editUserData.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
                        </div>
                        <button @click="editUserData.organizationalUnits.push('')" class="mt-2 text-blue-600">+ adicionar OU</button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Senha</label>
                        <input type="password" v-model="editUserData.userPassword" class="mt-1 block w-full border rounded px-3 py-2" minlength="6" />
                    </div>
                    <div class="flex justify-end space-x-3 mt-4">
                        <button @click="showEditUserModal=false" class="px-4 py-2 bg-gray-200 rounded">Cancelar</button>
                        <button @click="updateUser" class="px-4 py-2 bg-blue-600 text-white rounded">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fim modal edição -->
        <!-- Delete User Confirmation Modal -->
        <div v-if="showDeleteUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg font-medium text-gray-900">Confirmar Exclusão</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">Tem certeza que deseja excluir o usuário <strong>@{{ userToDelete.fullName }}</strong>?</p>
                    </div>
                    <div class="mt-4 flex justify-center space-x-4">
                        <button @click="showDeleteUserModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancelar</button>
                        <button @click="confirmDeleteUser" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Excluir</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Edit Organizational Unit Modal -->
        <div v-if="showEditOuModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">✏️ Editar Unidade Organizacional</h3>
                        <button @click="showEditOuModal = false" class="text-gray-400 hover:text-gray-600">✖️</button>
                    </div>
                    <form @submit.prevent="updateOu" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Unidade Organizacional</label>
                            <input v-model="editOuData.ou" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea v-model="editOuData.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button @click="showEditOuModal = false" type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
                        logs: [],
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
                        },
                        newUser: {
                            uid: '',
                            givenName: '',
                            sn: '',
                            employeeNumber: '',
                            mail: [''],
                            userPassword: '',
                            organizationalUnits: ['']
                        },
                        newOu: {
                            ou: '',
                            description: ''
                        },
                        editUserData: null,
                        showEditUserModal: false,
                        showDeleteUserModal: false,
                        userToDelete: null,
                        showEditOuModal: false,
                        editOuData: { ou: '', description: '', dn: '' }
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
                watch: {
                    activeTab(newVal) {
                        if (newVal === 'logs') {
                            this.loadLogs();
                        }
                    }
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
                        console.log('🔄 Carregando Unidades Organizacionais...');
                        try {
                            const response = await fetch('/api/ldap/organizational-units');
                            const data = await response.json();
                            
                            if (data.success) {
                                this.organizationalUnits = data.data;
                                console.log('✅ Unidades Organizacionais carregadas:', data.data.length);
                            } else {
                                console.log('⚠️ Erro na API Unidade Organizacional:', data.message);
                                this.handleApiError('Erro de Conexão LDAP', data.message);
                            }
                        } catch (error) {
                            console.log('❌ Erro de rede Unidade Organizacional:', error);
                            this.handleNetworkError('Erro ao carregar unidades organizacionais', error);
                        }
                    },
                    
                    async deleteUser(uid) {
                        const user = this.users.find(u => u.uid === uid);
                        this.userToDelete = user;
                        this.showDeleteUserModal = true;
                    },
                    
                    editUser(user) {
                        this.editUserData = JSON.parse(JSON.stringify(user));
                        this.showEditUserModal = true;
                    },
                    
                    async updateUser() {
                        try {
                            const response = await fetch(`/api/ldap/users/${this.editUserData.uid}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(this.editUserData)
                            });
                            const data = await response.json();
                            if (data.success) {
                                this.showNotification('Usuário atualizado com sucesso', 'success');
                                this.showEditUserModal = false;
                                this.loadUsers();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao atualizar usuário', 'error');
                        }
                    },
                    
                    async createUser() {
                        try {
                            const response = await fetch('/api/ldap/users', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(this.newUser)
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.showNotification('Usuário criado com sucesso', 'success');
                                this.showCreateUserModal = false;
                                this.resetNewUser();
                                this.loadUsers();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao criar usuário', 'error');
                        }
                    },
                    
                    async createOu() {
                        try {
                            const response = await fetch('/api/ldap/organizational-units', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(this.newOu)
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.showNotification('Unidade organizacional criada com sucesso', 'success');
                                this.showCreateOuModal = false;
                                this.resetNewOu();
                                this.loadOrganizationalUnits();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao criar unidade organizacional', 'error');
                        }
                    },
                    
                    addEmail() {
                        this.newUser.mail.push('');
                    },
                    
                    removeEmail(index) {
                        this.newUser.mail.splice(index, 1);
                    },
                    
                    addOu() {
                        this.newUser.organizationalUnits.push('');
                    },
                    
                    removeOu(index) {
                        this.newUser.organizationalUnits.splice(index, 1);
                    },
                    
                    resetNewUser() {
                        this.newUser = {
                            uid: '',
                            givenName: '',
                            sn: '',
                            employeeNumber: '',
                            mail: [''],
                            userPassword: '',
                            organizationalUnits: ['']
                        };
                    },
                    
                    resetNewOu() {
                        this.newOu = {
                            ou: '',
                            description: ''
                        };
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
                    },

                    /**
                     * Confirma exclusão do usuário selecionado
                     */
                    async confirmDeleteUser() {
                        try {
                            const response = await fetch(`/api/ldap/users/${this.userToDelete.uid}`, {
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
                        } finally {
                            this.showDeleteUserModal = false;
                        }
                    },

                    /**
                     * Abre o modal de edição de OU
                     */
                    editOu(ou) {
                        this.editOuData = JSON.parse(JSON.stringify(ou));
                        this.showEditOuModal = true;
                    },

                    /**
                     * Atualiza a unidade organizacional selecionada
                     */
                    async updateOu() {
                        try {
                            const response = await fetch(`/api/ldap/organizational-units/${encodeURIComponent(this.editOuData.ou)}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify({
                                    ou: this.editOuData.ou,
                                    description: this.editOuData.description
                                })
                            });
                            const data = await response.json();
                            if (data.success) {
                                this.showNotification('Unidade organizacional atualizada com sucesso', 'success');
                                this.showEditOuModal = false;
                                this.loadOrganizationalUnits();
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao atualizar unidade organizacional', 'error');
                        }
                    },

                    /**
                     * Carrega logs de operações
                     */
                    async loadLogs() {
                        try {
                            const response = await fetch('/api/ldap/logs');
                            const data = await response.json();
                            if (data.success) {
                                this.logs = data.data;
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao carregar logs', 'error');
                        }
                    }
                }
            }).mount('#app');
        }
    </script>
</body>
</html>
