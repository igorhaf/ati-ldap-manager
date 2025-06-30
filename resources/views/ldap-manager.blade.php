<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciador de Usuários LDAP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Vue.js com compilação de template -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div id="app">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Gerenciador LDAP</h1>
                        <p class="text-gray-600">Gerenciamento de Usuários e Unidades Organizacionais</p>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="showCreateUserModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Novo Usuário
                        </button>
                        <button @click="showCreateOuModal = true" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            Nova OU
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Status Panel -->
            <div v-if="systemStatus" class="mb-8">
                <div :class="systemStatus.type === 'error' ? 'bg-red-50 border-red-200' : systemStatus.type === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-green-50 border-green-200'" class="border rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <!-- Error Icon -->
                            <svg v-if="systemStatus.type === 'error'" class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <!-- Warning Icon -->
                            <svg v-else-if="systemStatus.type === 'warning'" class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <!-- Success Icon -->
                            <svg v-else class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 :class="systemStatus.type === 'error' ? 'text-red-800' : systemStatus.type === 'warning' ? 'text-yellow-800' : 'text-green-800'" class="text-sm font-medium">
                                @{{ systemStatus.title }}
                            </h3>
                            <div :class="systemStatus.type === 'error' ? 'text-red-700' : systemStatus.type === 'warning' ? 'text-yellow-700' : 'text-green-700'" class="mt-1 text-sm">
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
                            <div class="-mx-1.5 -my-1.5">
                                <button @click="systemStatus = null" :class="systemStatus.type === 'error' ? 'text-red-500 hover:bg-red-100' : systemStatus.type === 'warning' ? 'text-yellow-500 hover:bg-yellow-100' : 'text-green-500 hover:bg-green-100'" class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-8">
                <nav class="-mb-px flex space-x-8">
                    <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        Usuários
                    </button>
                    <button @click="activeTab = 'organizational-units'" :class="activeTab === 'organizational-units' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                        Unidades Organizacionais
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
                            <input v-model="searchTerm" type="text" id="search" placeholder="Buscar por nome, UID ou matrícula..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="flex items-end">
                            <button @click="loadUsers" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-medium transition-colors">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Atualizar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Lista de Usuários</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matrícula</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emails</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unidades</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in filteredUsers" :key="user.uid" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">@{{ user.uid }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">@{{ user.fullName }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">@{{ user.employeeNumber }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div v-for="email in user.mail" :key="email" class="text-xs">@{{ email }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div v-for="ou in user.organizationalUnits" :key="ou" class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mr-1 mb-1">@{{ ou }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="editUser(user)" class="text-blue-600 hover:text-blue-900 mr-3">Editar</button>
                                        <button @click="deleteUser(user.uid)" class="text-red-600 hover:text-red-900">Excluir</button>
                                    </td>
                                </tr>
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Nenhum usuário encontrado
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
                        <h3 class="text-lg font-medium text-gray-900">Unidades Organizacionais</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DN</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="ou in organizationalUnits" :key="ou.dn" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">@{{ ou.ou }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">@{{ ou.description || '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 font-mono">@{{ ou.dn }}</td>
                                </tr>
                                <tr v-if="organizationalUnits.length === 0">
                                    <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                        Nenhuma unidade organizacional encontrada
                                    </td>
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
                        <h3 class="text-lg font-medium text-gray-900">Criar Novo Usuário</h3>
                        <button @click="showCreateUserModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
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
                                    <button @click="removeEmail(index + 1)" type="button" class="px-3 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">Remover</button>
                                </div>
                                <button @click="addEmail" type="button" class="text-blue-600 hover:text-blue-800 text-sm">+ Adicionar Email</button>
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
                                    <button v-if="index > 0" @click="newUser.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
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

        <!-- Create OU Modal -->
        <div v-if="showCreateOuModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Criar Nova Unidade Organizacional</h3>
                        <button @click="showCreateOuModal = false" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <form @submit.prevent="createOu" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da OU</label>
                            <input v-model="newOu.ou" type="text" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea v-model="newOu.description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button @click="showCreateOuModal = false" type="button" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancelar</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Criar OU</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification -->
        <div v-if="notification.show" :class="notification.type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            @{{ notification.message }}
        </div>

        <!-- Modal Edit User -->
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
                            <button v-if="index > 0" @click="editUserData.mail.splice(index,1)" class="text-red-500">✖</button>
                        </div>
                        <button @click="editUserData.mail.push('')" class="mt-2 text-blue-600">+ adicionar email</button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Unidades Organizacionais</label>
                        <div v-for="(ou, index) in editUserData.organizationalUnits" :key="index" class="flex items-center space-x-2 mt-1">
                            <select v-model="editUserData.organizationalUnits[index]" class="flex-1 border rounded px-3 py-2">
                                <option value="" disabled>Selecione...</option>
                                <option v-for="ouOpt in organizationalUnits" :value="ouOpt.ou">@{{ ouOpt.ou }}</option>
                            </select>
                            <button v-if="index > 0" @click="editUserData.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
                        </div>
                        <button @click="editUserData.organizationalUnits.push('')" class="mt-2 text-blue-600">+ adicionar OU</button>
                    </div>
                    <div class="flex justify-end space-x-3 mt-4">
                        <button @click="showEditUserModal=false" class="px-4 py-2 bg-gray-200 rounded">Cancelar</button>
                        <button @click="updateUser" class="px-4 py-2 bg-blue-600 text-white rounded">Salvar</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Fim modal edit -->

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
    </div>

    <script>
        // Aguardar o carregamento do DOM e do Vue
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se já existe uma instância Vue montada
            const appContainer = document.getElementById('app');
            if (appContainer._vueApp) {
                console.log('Vue app já está montado, desmontando...');
                appContainer._vueApp.unmount();
            }
            
            // Verificar se o Vue está disponível
            if (typeof window.Vue !== 'undefined' && window.Vue.createApp) {
                const { createApp } = window.Vue;
                
                const app = createApp({
                    data() {
                        return {
                            activeTab: 'users',
                            users: [],
                            organizationalUnits: [],
                            searchTerm: '',
                            showCreateUserModal: false,
                            showCreateOuModal: false,
                            showEditUserModal: false,
                            showDeleteUserModal: false,
                            userToDelete: {},
                            systemStatus: null,
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
                            editUserData: {
                                uid: '',
                                givenName: '',
                                sn: '',
                                employeeNumber: '',
                                mail: [],
                                userPassword: '',
                                organizationalUnits: []
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
                        this.loadUsers();
                        this.loadOrganizationalUnits();
                    },
                    methods: {
                        async loadUsers() {
                            try {
                                const response = await fetch('/api/ldap/users');
                                const data = await response.json();
                                
                                if (data.success) {
                                    this.users = data.data;
                                    this.systemStatus = null;
                                } else {
                                    this.handleApiError('Erro de Conexão LDAP', data.message);
                                }
                            } catch (error) {
                                this.handleNetworkError('Erro ao carregar usuários', error);
                            }
                        },
                        
                        async loadOrganizationalUnits() {
                            try {
                                const response = await fetch('/api/ldap/organizational-units');
                                const data = await response.json();
                                
                                if (data.success) {
                                    this.organizationalUnits = data.data;
                                } else {
                                    this.handleApiError('Erro de Conexão LDAP', data.message);
                                }
                            } catch (error) {
                                this.handleNetworkError('Erro ao carregar unidades organizacionais', error);
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
                        
                        async deleteUser(uid) {
                            const user = this.users.find(u => u.uid === uid);
                            this.userToDelete = user;
                            this.showDeleteUserModal = true;
                        },
                        
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
                        
                        editUser(user) {
                            // Copia profunda para não alterar a lista diretamente
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
                        
                        resetEditUser() {
                            this.editUserData = {
                                uid: '',
                                givenName: '',
                                sn: '',
                                employeeNumber: '',
                                mail: [],
                                userPassword: '',
                                organizationalUnits: []
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
                                    'Teste a conexão com uma ferramenta externa como Apache Directory Studio'
                                ];
                            } else if (message.includes('Connection refused') || message.includes('timeout')) {
                                title = 'Erro de Conexão com Servidor LDAP';
                                details = [
                                    'Não foi possível estabelecer conexão com o servidor LDAP',
                                    `Host: ${window.ldapConfig?.host || 'não configurado'}`,
                                    `Porta: ${window.ldapConfig?.port || 'não configurada'}`
                                ];
                                suggestions = [
                                    'Verifique se o servidor LDAP está em execução',
                                    'Confirme se o host e porta estão corretos no .env',
                                    'Verifique se não há firewall bloqueando a conexão'
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
                });
                
                // Montar a aplicação e guardar referência
                appContainer._vueApp = app.mount('#app');
                console.log('Vue app montado com sucesso!');
            } else {
                console.error('Vue.js não está disponível. Verifique se os assets foram compilados corretamente.');
                document.getElementById('app').innerHTML = '<div class="p-8 text-center"><p class="text-red-600">Erro: Vue.js não foi carregado corretamente.</p><p class="text-gray-600 mt-2">Verifique se os assets foram compilados com: npm run build</p></div>';
            }
        });
    </script>
</body>
</html> 