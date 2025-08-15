<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SEI LDAP Admin</title>
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
    <style>
        /* Transição suave para o drawer */
        .slide-in-enter-active, .slide-in-leave-active {
            transition: transform 0.3s ease-out, opacity 0.3s ease-out;
        }
        .slide-in-enter-from {
            transform: translateX(100%);
            opacity: 0;
        }
        .slide-in-leave-to {
            transform: translateX(100%);
            opacity: 0;
        }
    </style>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script>
        window.USER_ROLE = "{{ $userRole ?? 'user' }}";
        window.USER_UID = "{{ auth()->user()->getFirstAttribute('uid') ?? '' }}";
        window.USER_CN = "{{ auth()->user()->getFirstAttribute('cn') ?? '' }}";
        window.USER_MAIL = "{{ auth()->user()->getFirstAttribute('mail') ?? '' }}";
        
        // Debug do usuário autenticado
        console.log('🔐 Usuário autenticado:', {
            role: window.USER_ROLE,
            uid: window.USER_UID,
            cn: window.USER_CN,
            mail: window.USER_MAIL
        });
        
        // Verificar se UID está vazio
        if (!window.USER_UID || window.USER_UID.trim() === '') {
            console.error('❌ CRITICAL: window.USER_UID está vazio!');
            console.error('🔍 Verifique se o usuário está autenticado e tem UID no LDAP');
        }
    </script>
</head>
<body class="bg-gradient-to-br from-indigo-50 to-blue-50 min-h-screen">
    <div id="app">
        <header class="bg-gradient-to-r from-indigo-600 to-blue-600 shadow-md">
            <div class="max-w-full mx-auto px-6 xl:px-12">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.56-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.56.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd" />
                            </svg>
                            SEI LDAP Admin
                        </h1>
                        <p class="text-blue-100">Gerenciamento de Usuários e Unidades Organizacionais</p>
                    </div>
                    <div class="flex space-x-3">
                                                    <button v-if="canManageUsers" @click="openCreateUserModal" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 hover:shadow-lg hover:scale-105 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Novo Usuário
                        </button>
                        <button v-if="isRoot" @click="showCreateOuModal = true" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 hover:shadow-lg hover:scale-105 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Nova OU
                        </button>
                        <button @click="logout" class="bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 hover:shadow-lg backdrop-blur-sm flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Sair
                        </button>
                    </div>
                </div>
            </div>
        </header>

                 <!-- Status Panel -->
         <main class="max-w-full mx-auto px-6 xl:px-12 py-8">
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
                 <nav class="-mb-px inline-flex bg-white rounded-2xl p-2 gap-1 shadow-lg border border-gray-200">
                     <button @click="activeTab = 'users'" :class="activeTab === 'users' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'" class="whitespace-nowrap px-6 py-3 rounded-xl transition-all duration-200 font-medium text-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Usuários
                     </button>
                     <button v-if="isRoot" @click="activeTab = 'organizational-units'" :class="activeTab === 'organizational-units' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'" class="whitespace-nowrap px-6 py-3 rounded-xl transition-all duration-200 font-medium text-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Unidades
                     </button>
                     <button v-if="canManageUsers" @click="activeTab = 'logs'" :class="activeTab === 'logs' ? 'bg-blue-600 text-white shadow-md' : 'text-gray-600 hover:text-blue-600 hover:bg-gray-50'" class="whitespace-nowrap px-6 py-3 rounded-xl transition-all duration-200 font-medium text-sm flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Logs
                     </button>
                 </nav>
             </div>

             <!-- Users Tab -->
             <div v-if="activeTab === 'users'" class="space-y-6">
                 <!-- Search and Filters -->
                 <div class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                     <div class="flex flex-col sm:flex-row gap-4">
                         <div class="flex-1">
                             <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar Usuários</label>
                                                             <input v-model="searchTerm" type="text" id="search" placeholder="Buscar por nome, UID ou CPF..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                         </div>
                         <div class="flex items-end">
                             <button @click="loadUsers" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-200 hover:shadow-lg flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Atualizar
                             </button>
                         </div>
                     </div>
                 </div>

                 <!-- Users Table -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h3 class="text-lg font-medium text-gray-900">Lista de Usuários (@{{ filteredUsers.length }})</h3>
                     </div>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                 <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">@{{ isRoot ? 'Unidades' : 'Perfil' }}</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                    <th v-if="canManageUsers" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="user in paginatedUsers" :key="user.uid" :class="[user.uid === 'root' ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : 'hover:bg-gray-50 odd:bg-gray-50']">
                                    <td class="px-6 py-4 text-sm font-medium" :class="user.uid === 'root' ? 'text-gray-500' : 'text-gray-900'">@{{ user.uid }}</td>
                                    <td class="px-6 py-4 text-sm" :class="user.uid === 'root' ? 'text-gray-500' : 'text-gray-900'">@{{ user.fullName }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">
                                        <!-- Para usuários root: mostrar todas as OUs -->
                                        <div v-if="isRoot" v-for="unit in user.organizationalUnits" :key="unit.ou ?? unit" @click="setOuFilter(typeof unit==='string'?unit:unit.ou)" :class="['inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-full mr-2 mb-1 border cursor-pointer select-none', ((typeof unit==='string'?unit:unit.ou)===activeOuFilter) ? 'bg-blue-600 text-white border-blue-600' : 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800 border-blue-300/30 hover:brightness-90']">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                            </svg>
                                            @{{ unit.ou ?? unit }}
                                            <span v-if="(unit.role ?? 'user') === 'admin'" class="ml-1 bg-orange-500 text-white text-xs px-1.5 py-0.5 rounded-full font-medium">Admin</span>
                                        </div>
                                        
                                        <!-- Para admins de OU: mostrar apenas o perfil -->
                                        <div v-else v-for="unit in user.organizationalUnits" :key="unit.ou ?? unit" @click="setRoleFilter(unit.role ?? 'user')" :class="['inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-full mr-2 mb-1 border cursor-pointer select-none', (unit.role ?? 'user') === activeRoleFilter ? ((unit.role ?? 'user') === 'admin' ? 'bg-orange-500 text-white border-orange-500' : 'bg-blue-600 text-white border-blue-600') : ((unit.role ?? 'user') === 'admin' ? 'bg-gradient-to-r from-orange-100 to-orange-200 text-orange-800 border-orange-300/30 hover:brightness-90' : 'bg-gradient-to-r from-blue-100 to-blue-200 text-blue-800 border-blue-300/30 hover:brightness-90')]">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            @{{ (unit.role ?? 'user') === 'admin' ? 'Admin' : 'Usuário' }}
                                        </div>
                                     </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="text-sm font-medium text-gray-900">@{{ user.mail }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm" :class="user.uid === 'root' ? 'text-gray-500' : 'text-gray-900'">@{{ user.employeeNumber }}</td>
                                    <td v-if="canManageUsers" class="px-6 py-4 text-sm font-medium">
                                        <template v-if="user.uid !== 'root'">
                                            <button @click="openEditUserModal(user)" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-900 mr-4 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Editar
                                            </button>
                                            <button @click="deleteUser(user.uid)" class="inline-flex items-center gap-1 text-red-600 hover:text-red-900 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Excluir
                                            </button>
                                        </template>
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
                    <!-- Controles de paginação Usuários -->
                    <div class="flex justify-center items-center mt-4 mb-8 space-x-1" v-if="totalUsersPages > 1">
                        <button @click="prevPage('users')" :disabled="usersPage === 1" class="px-2 py-1 border rounded disabled:opacity-50">«</button>
                        <button v-for="n in pageNumbers(totalUsersPages)" :key="'u'+n" @click="setPage('users',n)" :class="['px-3 py-1 rounded-full', usersPage===n ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white hover:bg-gray-100']">@{{ n }}</button>
                        <button @click="nextPage('users')" :disabled="usersPage === totalUsersPages" class="px-2 py-1 border rounded disabled:opacity-50">»</button>
                    </div>
                 </div>
             </div>

             <!-- Organizational Units Tab -->
             <div v-if="activeTab === 'organizational-units' && isRoot" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
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
                                <tr v-for="ou in paginatedOus" :key="ou.dn" class="hover:bg-gray-50">
                                     <td class="px-6 py-4 text-sm font-medium text-gray-900">@{{ ou.ou }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ ou.description || '-' }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-500 font-mono">@{{ ou.dn }}</td>
                                     <td class="px-6 py-4 text-sm font-medium">
                                        <button @click="editOu(ou)" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-900 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Editar
                                        </button>
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
                    <!-- Paginação OUs -->
                    <div class="flex justify-center items-center mt-4 mb-8 space-x-1" v-if="totalOusPages > 1">
                        <button @click="prevPage('ous')" :disabled="ousPage === 1" class="px-2 py-1 border rounded disabled:opacity-50">«</button>
                        <button v-for="n in pageNumbers(totalOusPages)" :key="'o'+n" @click="setPage('ous',n)" :class="['px-3 py-1 rounded-full', ousPage===n ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white hover:bg-gray-100']">@{{ n }}</button>
                        <button @click="nextPage('ous')" :disabled="ousPage === totalOusPages" class="px-2 py-1 border rounded disabled:opacity-50">»</button>
                    </div>
                 </div>
             </div>

             <!-- Logs Tab -->
             <div v-if="activeTab === 'logs' && canManageUsers" class="space-y-6">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                     <div class="px-6 py-4 border-b border-gray-200">
                         <h3 class="text-lg font-medium text-gray-900">Logs de Operações (@{{ logs.length }})</h3>
                     </div>
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200">
                             <thead class="bg-gray-50">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Executor</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuário afetado</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organização</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="log in paginatedLogs" :key="log.id" class="hover:bg-gray-50 cursor-pointer" @click="openLogDrawer(log)">
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.actor }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.action }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.target }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.ou || '-' }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.result }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-500">@{{ new Date(log.when).toLocaleString() }}</td>
                                     <td class="px-6 py-4 text-sm text-gray-900">@{{ log.description }}</td>
                                   </tr>
                                   <tr v-if="logs.length === 0">
                                      <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">Nenhum log encontrado</td>
                                   </tr>
                               </tbody>
                           </table>
                     </div>
                    <!-- Paginação Logs -->
                    <div class="flex justify-center items-center mt-4 mb-8 space-x-1" v-if="totalLogPages > 1">
                        <button @click="prevPage('logs')" :disabled="logsPage === 1" class="px-2 py-1 border rounded disabled:opacity-50">«</button>
                        <button v-for="n in pageNumbers(totalLogPages)" :key="'l'+n" @click="setPage('logs',n)" :class="['px-3 py-1 rounded-full', logsPage===n ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white hover:bg-gray-100']">@{{ n }}</button>
                        <button @click="nextPage('logs')" :disabled="logsPage === totalLogPages" class="px-2 py-1 border rounded disabled:opacity-50">»</button>
                    </div>
                 </div>
             </div>
         </main>

        <!-- Drawer simples para logs -->
        <transition name="slide-in">
            <div v-if="showRightDrawer" class="fixed inset-0 z-50" @click="closeLogDrawer">
                <div class="fixed top-0 right-0 h-full w-full md:w-80 bg-white shadow-2xl border-l border-gray-200 overflow-y-auto" @click.stop>
                    <div class="p-4 border-b flex items-center justify-between">
                        <h4 class="text-base font-semibold">Detalhes do Log</h4>
                        <button class="text-gray-500 hover:text-gray-700" @click="closeLogDrawer">✕</button>
                    </div>
                    <div class="p-4 space-y-4" v-if="selectedLog">
                        <!-- Executor -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">Executor</div>
                            <div class="text-sm text-gray-900">@{{ selectedLog.actor }}</div>
                        </div>

                        <!-- Ação e Resultado -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">Ação</div>
                                <div class="text-sm text-gray-900">@{{ selectedLog.action }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <div class="text-xs font-medium text-gray-500 uppercase mb-1">Resultado</div>
                                <div class="text-sm" :class="selectedLog.result === 'Sucesso' ? 'text-green-700 font-medium' : 'text-red-700 font-medium'">
                                    @{{ selectedLog.result }}
                                </div>
                            </div>
                        </div>

                        <!-- Usuário Afetado -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">Usuário Afetado</div>
                            <div class="text-sm text-gray-900">@{{ selectedLog.target }}</div>
                        </div>

                        <!-- Organização -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">Organização</div>
                            <div class="text-sm text-gray-900">@{{ selectedLog.ou || 'Não especificada' }}</div>
                        </div>

                        <!-- Data/Hora -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">Quando</div>
                            <div class="text-sm text-gray-900">@{{ new Date(selectedLog.when).toLocaleString('pt-BR') }}</div>
                        </div>

                        <!-- Descrição -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">Descrição</div>
                            <div class="text-sm text-gray-900">@{{ selectedLog.description || 'Sem descrição' }}</div>
                        </div>

                        <!-- Resumo das Mudanças -->
                        <div v-if="selectedLog.changes && selectedLog.changes.length" class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                            <div class="text-xs font-medium text-blue-700 uppercase mb-2">Resumo das Mudanças</div>
                            <ul class="list-disc list-inside space-y-1 text-sm text-blue-900">
                                <li v-for="(chg, idx) in selectedLog.changes" :key="idx">
                                    <template v-if="chg.note">
                                        <span class="font-medium">@{{ chg.field }}:</span> @{{ chg.note }}
                                    </template>
                                    <template v-else>
                                        <span class="font-medium">@{{ chg.field }}:</span>
                                        <span class="text-blue-800"> de </span>
                                        <span class="font-mono">@{{ chg.old ?? '-' }}</span>
                                        <span class="text-blue-800"> para </span>
                                        <span class="font-mono">@{{ chg.new ?? '-' }}</span>
                                    </template>
                                </li>
                            </ul>
                        </div>

                        <!-- ID do Log -->
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase mb-1">ID do Log</div>
                            <div class="text-xs text-gray-700 font-mono">#@{{ selectedLog.id }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Create User Modal -->
        <div v-if="showCreateUserModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm z-50">
            <div class="w-11/12 md:w-3/4 lg:w-1/2 bg-white rounded-2xl shadow-2xl border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Criar Novo Usuário
                        </h3>
                        <button @click="showCreateUserModal = false" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
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
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
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
                                <label for="mail" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                <input 
                                    v-model="newUser.mail" 
                                    type="email" 
                                    id="mail"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="exemplo@empresa.com"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Interface para ROOT: múltiplas OUs -->
                        <div v-if="isRoot">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Organizacionais</label>
                            <div class="space-y-2">
                                <div v-for="(unit, index) in newUser.organizationalUnits" :key="index" class="flex items-center space-x-2 mt-1">
                                    <select v-model="newUser.organizationalUnits[index].ou" class="flex-1 border rounded px-3 py-2">
                                        <option value="" disabled>Selecione OU...</option>
                                        <option v-for="ouOpt in organizationalUnits" :value="ouOpt.ou">@{{ ouOpt.ou }}</option>
                                    </select>
                                    <select v-model="newUser.organizationalUnits[index].role" class="border rounded px-2 py-2">
                                        <option value="user">Usuário</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button v-if="index > 0" @click="newUser.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
                                </div>
                                <button @click="newUser.organizationalUnits.push({ ou: '', role: 'user' })" class="mt-2 text-blue-600">+ adicionar OU</button>
                            </div>
                        </div>

                        <!-- Interface para Admin OU: apenas dropdown de papel -->
                        <div v-if="isOuAdmin">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Papel do usuário na sua OU</label>
                                <select v-model="newUserRole" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="user">Usuário Comum</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-8 border-t border-gray-200">
                            <button @click="showCreateUserModal = false" type="button" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium transition-colors">Cancelar</button>
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium transition-colors hover:shadow-lg">Criar Usuário</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create UO Modal -->
        <div v-if="showCreateOuModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm z-50">
            <div class="w-11/12 md:w-3/4 lg:w-1/2 bg-white rounded-2xl shadow-2xl border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Criar Nova Unidade Organizacional
                        </h3>
                        <button @click="showCreateOuModal = false" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
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

                        <div class="flex justify-end space-x-4 pt-8 border-t border-gray-200">
                            <button @click="showCreateOuModal = false" type="button" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium transition-colors">Cancelar</button>
                            <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 font-medium transition-colors hover:shadow-lg">Criar Unidade Organizacional</button>
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
        <div v-if="showEditUserModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm z-50">
            <div class="w-11/12 md:w-3/4 lg:w-1/2 bg-white rounded-2xl shadow-2xl border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar Usuário
                        </h3>
                        <button @click="showEditUserModal = false" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    
                    <form @submit.prevent="updateUser" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">UID (não editável)</label>
                                <input type="text" v-model="editUser.uid" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100" disabled />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF @{{ isRoot ? '' : '(não editável)' }}</label>
                                <input type="text" v-model="editUser.employeeNumber" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" :class="isRoot ? '' : 'bg-gray-100'" :disabled="!isRoot" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                <input v-model="editUser.givenName" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sobrenome</label>
                                <input v-model="editUser.sn" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Senha (deixe em branco para manter)</label>
                                <input v-model="editUser.userPassword" type="password" minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="edit-mail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input 
                                    v-model="editUser.mail" 
                                    type="email" 
                                    id="edit-mail"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="exemplo@empresa.com"
                                >
                            </div>
                        </div>

                        <!-- Interface para ROOT: múltiplas OUs -->
                        <div v-if="isRoot">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unidades Organizacionais</label>
                            <div class="space-y-2">
                                <div v-for="(unit, index) in editUser.organizationalUnits" :key="index" class="flex items-center space-x-2 mt-1">
                                    <select v-model="editUser.organizationalUnits[index].ou" class="flex-1 border rounded px-3 py-2">
                                        <option value="" disabled>Selecione OU...</option>
                                        <option v-for="ouOpt in organizationalUnits" :value="ouOpt.ou">@{{ ouOpt.ou }}</option>
                                    </select>
                                    <select v-model="editUser.organizationalUnits[index].role" class="border rounded px-2 py-2">
                                        <option value="user">Usuário</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                    <button v-if="index > 0" @click="editUser.organizationalUnits.splice(index,1)" class="text-red-500">✖</button>
                                </div>
                                <button @click="editUser.organizationalUnits.push({ ou: '', role: 'user' })" class="mt-2 text-blue-600">+ adicionar OU</button>
                            </div>
                        </div>

                        <!-- Interface para Admin OU: apenas dropdown de papel -->
                        <div v-if="isOuAdmin">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Papel do usuário na sua OU</label>
                                <select v-model="editUserRole" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="user">Usuário Comum</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-8 border-t border-gray-200">
                            <button @click="showEditUserModal = false" type="button" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium transition-colors">Cancelar</button>
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium transition-colors hover:shadow-lg">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Fim modal edição -->
        <!-- Delete User Confirmation Modal -->
        <div v-if="showDeleteUserModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
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
        <div v-if="showEditOuModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="w-11/12 md:w-3/4 lg:w-1/2 max-h-[90vh] overflow-y-auto bg-white rounded-2xl shadow-2xl border border-gray-100">
                <div class="p-8">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Editar Unidade Organizacional
                        </h3>
                        <button @click="showEditOuModal = false" class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
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

                        <div class="flex justify-end space-x-4 pt-8 border-t border-gray-200">
                            <button @click="showEditOuModal = false" type="button" class="px-6 py-3 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-50 font-medium transition-colors">Cancelar</button>
                            <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 font-medium transition-colors hover:shadow-lg">Salvar Alterações</button>
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
                        userRole: window.USER_ROLE,
                        activeTab: 'users',
                        users: [],
                        organizationalUnits: [],
                        logs: [],
                        // Paginação
                        itemsPerPage: 20,
                        usersPage: 1,
                        ousPage: 1,
                        logsPage: 1,
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
                        activeOuFilter: '',
                        activeRoleFilter: '',
                        adminOu: '',
                        newUserRole: 'user',
                        editUserRole: 'user',
                        newUser: {
                            uid: '',
                            givenName: '',
                            sn: '',
                            employeeNumber: '',
                            mail: '',
                            userPassword: '',
                            organizationalUnits: [{ou: '', role: 'user'}]
                        },
                        newOu: {
                            ou: '',
                            description: ''
                        },
                        editUser: {
                            uid: '',
                            givenName: '',
                            sn: '',
                            employeeNumber: '',
                            mail: '',
                            userPassword: '',
                            organizationalUnits: [{ou: '', role: 'user'}]
                        },
                        showEditUserModal: false,
                        showDeleteUserModal: false,
                        userToDelete: null,
                        showEditOuModal: false,
                        editOuData: { ou: '', description: '', dn: '' },
                        // Drawer à direita
                        showRightDrawer: false,
                        selectedLogId: null,
                        selectedLog: null
                    }
                },
                computed: {
                    isRoot() { return this.userRole === 'root'; },
                    isOuAdmin() { return this.userRole === 'admin'; },
                    canManageUsers() { return this.isRoot || this.isOuAdmin; },
                    filteredUsers() {
                        let list = this.users;

                        // Aplica filtro de OU se selecionado (apenas para root)
                        if (this.activeOuFilter && this.isRoot) {
                            list = list.filter(u => {
                                return (u.organizationalUnits || []).some(unit => {
                                    const ouName = typeof unit === 'string' ? unit : (unit.ou ?? unit);
                                    return ouName === this.activeOuFilter;
                                });
                            });
                        }

                        // Aplica filtro de role se selecionado (apenas para admin de OU)
                        if (this.activeRoleFilter && !this.isRoot) {
                            list = list.filter(u => {
                                return (u.organizationalUnits || []).some(unit => {
                                    const role = typeof unit === 'string' ? 'user' : (unit.role ?? 'user');
                                    return role === this.activeRoleFilter;
                                });
                            });
                        }

                        if (!this.searchTerm) return list;
                        
                        const term = this.searchTerm.toLowerCase();
                        return list.filter(user => 
                            user.uid.toLowerCase().includes(term) ||
                            user.fullName.toLowerCase().includes(term) ||
                            user.employeeNumber.toLowerCase().includes(term)
                        );
                    },
                    // Paginação usuários
                    paginatedUsers() {
                        const start = (this.usersPage - 1) * this.itemsPerPage;
                        return this.filteredUsers.slice(start, start + this.itemsPerPage);
                    },
                    totalUsersPages() { return Math.ceil(this.filteredUsers.length / this.itemsPerPage) || 1; },
                    // Paginação OUs
                    paginatedOus() {
                        const start = (this.ousPage - 1) * this.itemsPerPage;
                        return this.organizationalUnits.slice(start, start + this.itemsPerPage);
                    },
                    totalOusPages() { return Math.ceil(this.organizationalUnits.length / this.itemsPerPage) || 1; },
                    // Paginação logs
                    paginatedLogs() {
                        const start = (this.logsPage - 1) * this.itemsPerPage;
                        return this.logs.slice(start, start + this.itemsPerPage);
                    },
                    totalLogPages() { return Math.ceil(this.logs.length / this.itemsPerPage) || 1; },
                },
                mounted() {
                    console.log('✅ LDAP Manager montado com sucesso!');
                    this.loadUsers();
                    // Só carregar OUs se for root
                    if (this.isRoot) {
                        this.loadOrganizationalUnits();
                    }
                    // Se for admin de OU, obter a OU do usuário
                    if (this.isOuAdmin) {
                        this.getAdminOu();
                    }
                },
                watch: {
                    activeTab(newVal) {
                        if (newVal === 'logs' && this.canManageUsers) {
                            this.loadLogs();
                        }
                    }
                },
                methods: {
                    setOuFilter(ou){
                        if(this.activeOuFilter===ou){
                            this.activeOuFilter='';
                        } else {
                            this.activeOuFilter=ou;
                        }
                        this.usersPage=1; // reset page
                    },
                    setRoleFilter(role){
                        if(this.activeRoleFilter===role){
                            this.activeRoleFilter='';
                        } else {
                            this.activeRoleFilter=role;
                        }
                        this.usersPage=1; // reset page
                    },
                    // Navegação de página genérica
                    prevPage(section) {
                        if (section === 'users' && this.usersPage > 1) this.usersPage--;
                        if (section === 'ous' && this.ousPage > 1) this.ousPage--;
                        if (section === 'logs' && this.logsPage > 1) this.logsPage--;
                    },
                    setPage(section,page){
                        if(section==='users') this.usersPage = page;
                        if(section==='ous') this.ousPage = page;
                        if(section==='logs') this.logsPage = page;
                    },
                    nextPage(section) {
                        if (section === 'users' && this.usersPage < this.totalUsersPages) this.usersPage++;
                        if (section === 'ous' && this.ousPage < this.totalOusPages) this.ousPage++;
                        if (section === 'logs' && this.logsPage < this.totalLogPages) this.logsPage++;
                    },
                    async loadUsers() {
                        console.log('🔄 Carregando usuários...');
                        try {
                            const response = await fetch('/api/ldap/users');
                            const data = await response.json();
                            
                            if (data.success) {
                                // Garantir que organizationalUnits esteja no formato de objetos {ou, role}
                                this.users = data.data.map(u => {
                                    if (Array.isArray(u.organizationalUnits) && typeof u.organizationalUnits[0] === 'string') {
                                        u.organizationalUnits = u.organizationalUnits.map(o => ({ ou: o, role: 'user' }));
                                    }
                                    return u;
                                });
                                this.systemStatus = null;
                                console.log('✅ Usuários carregados:', data.data.length);
                                
                                // Se for admin de OU e ainda não obteve a OU, obter agora
                                if (this.isOuAdmin && !this.adminOu) {
                                    this.getAdminOu();
                                }
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
                                // Se for erro 403 (acesso negado), não mostrar erro de conexão LDAP
                                if (data.message && data.message.includes('Acesso negado')) {
                                    console.log('ℹ️ Acesso negado para carregar OUs (usuário não é root)');
                                    this.organizationalUnits = []; // Array vazio para não quebrar formulários
                                    return;
                                }
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
                    
                    openEditUserModal(user) {
                        this.editUser.uid = user.uid;
                        this.editUser.givenName = user.givenName;
                        this.editUser.sn = user.sn;
                        this.editUser.employeeNumber = user.employeeNumber;
                        this.editUser.mail = user.mail;
                        this.editUser.userPassword = '';
                        
                        // Garantir que organizationalUnits seja um array de objetos
                        if (Array.isArray(user.organizationalUnits)) {
                            if (typeof user.organizationalUnits[0] === 'string') {
                                this.editUser.organizationalUnits = user.organizationalUnits.map(ou => ({ ou, role: 'user' }));
                            } else {
                                this.editUser.organizationalUnits = JSON.parse(JSON.stringify(user.organizationalUnits));
                            }
                        } else {
                            this.editUser.organizationalUnits = [{ ou: '', role: 'user' }];
                        }
                        
                        // Para admin de OU, definir o papel atual do usuário na OU do admin
                        if (this.isOuAdmin) {
                            const adminOuEntry = user.organizationalUnits.find(unit => 
                                (typeof unit === 'string' ? unit : unit.ou) === this.adminOu
                            );
                            this.editUserRole = adminOuEntry ? 
                                (typeof adminOuEntry === 'string' ? 'user' : adminOuEntry.role) : 'user';
                        }
                        
                        this.showEditUserModal = true;
                    },
                    
                    async updateUser() {
                        try {
                            // Preparar dados baseado no tipo de usuário
                            let userData = { ...this.editUser };
                            
                            if (this.isOuAdmin) {
                                // Para admin de OU: usar apenas sua OU com o papel selecionado
                                userData.organizationalUnits = [{ ou: this.adminOu, role: this.editUserRole }];
                            }
                            
                            const response = await fetch(`/api/ldap/users/${this.editUser.uid}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(userData)
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
                            // Preparar dados baseado no tipo de usuário
                            let userData = { ...this.newUser };
                            
                            if (this.isOuAdmin) {
                                // Validar se adminOu está preenchida
                                if (!this.adminOu || this.adminOu.trim() === '') {
                                    this.showNotification('Erro: OU do administrador não definida. Recarregue a página.', 'error');
                                    console.error('❌ adminOu vazia:', this.adminOu);
                                    return;
                                }
                                
                                // Para admin de OU: usar apenas sua OU com o papel selecionado
                                userData.organizationalUnits = [{ 
                                    ou: this.adminOu.trim(), 
                                    role: this.newUserRole || 'user' 
                                }];
                                
                                console.log('🏢 Dados para admin OU:', {
                                    adminOu: this.adminOu,
                                    newUserRole: this.newUserRole,
                                    organizationalUnits: userData.organizationalUnits
                                });
                            } else {
                                // Para ROOT: validar se pelo menos uma OU foi selecionada
                                if (!userData.organizationalUnits || userData.organizationalUnits.length === 0 || 
                                    !userData.organizationalUnits[0].ou || userData.organizationalUnits[0].ou.trim() === '') {
                                    this.showNotification('Por favor, selecione pelo menos uma OU', 'error');
                                    return;
                                }
                            }
                            
                            console.log('📤 Enviando dados:', userData);
                            
                            const response = await fetch('/api/ldap/users', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                },
                                body: JSON.stringify(userData)
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
                            console.error('❌ Erro ao criar usuário:', error);
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
                                // Só recarregar OUs se for root
                                if (this.isRoot) {
                                    this.loadOrganizationalUnits();
                                }
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao criar unidade organizacional', 'error');
                        }
                    },
                    
                    async logout() {
                        try {
                            await fetch('/logout', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            });
                        } catch (e) {
                            console.error('Erro ao fazer logout', e);
                        } finally {
                            window.location.href = '/login';
                        }
                    },
                    
                    openCreateUserModal() {
                        this.resetNewUser();
                        
                        // Para admin de OU, verificar se adminOu está preenchida
                        if (this.isOuAdmin) {
                            console.log('🏢 Abrindo modal para admin OU. AdminOU atual:', this.adminOu);
                            
                            if (!this.adminOu || this.adminOu.trim() === '') {
                                console.warn('⚠️  adminOu vazia, tentando recarregar...');
                                
                                // Recarregar usuários e obter OU do admin
                                this.loadUsers().then(async () => {
                                    await this.getAdminOu();
                                    console.log('🔄 Após recarregar, adminOu:', this.adminOu);
                                    
                                    if (!this.adminOu || this.adminOu.trim() === '') {
                                        this.showNotification('Erro: Não foi possível determinar sua OU. Recarregue a página.', 'error');
                                        return;
                                    }
                                    this.showCreateUserModal = true;
                                }).catch(error => {
                                    console.error('❌ Erro ao recarregar dados:', error);
                                    this.showNotification('Erro ao carregar dados. Recarregue a página.', 'error');
                                });
                            } else {
                                this.showCreateUserModal = true;
                            }
                        } else {
                            // Para ROOT, abrir direto
                            this.showCreateUserModal = true;
                        }
                    },
                    
                    resetNewUser() {
                        this.newUser = {
                            uid: '',
                            givenName: '',
                            sn: '',
                            employeeNumber: '',
                            mail: '',
                            userPassword: '',
                            organizationalUnits: [{ou: '', role: 'user'}]
                        };
                        
                        // Para admin de OU, resetar também o papel selecionado
                        if (this.isOuAdmin) {
                            this.newUserRole = 'user';
                        }
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
                                // Só recarregar OUs se for root
                                if (this.isRoot) {
                                    this.loadOrganizationalUnits();
                                }
                            } else {
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            this.showNotification('Erro ao atualizar unidade organizacional', 'error');
                        }
                    },

                    /**
                     * Obtém a OU do administrador logado
                     */
                    async getAdminOu() {
                        try {
                            console.log('🔍 Iniciando getAdminOu...');
                            console.log('📋 Total de usuários carregados:', this.users.length);
                            console.log('🔑 USER_UID atual:', window.USER_UID);
                            
                            // Resetar adminOu no início
                            this.adminOu = '';
                            
                            // Verificar se USER_UID está definido
                            if (!window.USER_UID) {
                                console.error('❌ window.USER_UID não está definido!');
                                return;
                            }
                            
                            // Obtém a OU do admin a partir dos usuários carregados
                            const currentUser = this.users.find(u => u.uid === window.USER_UID);
                            console.log('👤 Usuário atual encontrado:', currentUser ? 'Sim' : 'Não');
                            
                            if (!currentUser) {
                                console.warn('⚠️  Usuário atual não encontrado na lista. Tentando buscar direto na API...');
                                await this.loadCurrentUserFromApi();
                                return;
                            }
                            
                            console.log('🏢 OUs do usuário:', currentUser.organizationalUnits);
                            
                            if (!currentUser.organizationalUnits || currentUser.organizationalUnits.length === 0) {
                                console.error('❌ Usuário não tem OUs definidas!');
                                return;
                            }
                            
                            // Buscar OU com role admin
                            const adminOuEntry = currentUser.organizationalUnits.find(unit => {
                                const role = typeof unit === 'string' ? 'user' : (unit.role || 'user');
                                console.log(`  📍 Verificando OU: ${typeof unit === 'string' ? unit : unit.ou}, Role: ${role}`);
                                return role === 'admin';
                            });
                            
                            if (adminOuEntry) {
                                this.adminOu = typeof adminOuEntry === 'string' ? adminOuEntry : adminOuEntry.ou;
                                console.log('✅ OU Admin encontrada:', this.adminOu);
                            } else {
                                // Fallback para a primeira OU
                                console.warn('⚠️  Não encontrou OU admin, usando primeira OU disponível...');
                                const firstOu = currentUser.organizationalUnits[0];
                                this.adminOu = typeof firstOu === 'string' ? firstOu : firstOu.ou;
                                console.log('🔄 Usando primeira OU como fallback:', this.adminOu);
                            }
                            
                            // Validação final
                            if (!this.adminOu || this.adminOu.trim() === '') {
                                console.error('❌ adminOu continua vazia após processamento!');
                            } else {
                                console.log('✅ OU do Admin definida com sucesso:', this.adminOu);
                            }
                            
                        } catch (error) {
                            console.error('❌ Erro ao obter OU do admin:', error);
                        }
                    },
                    
                    /**
                     * Busca dados do usuário atual diretamente da API
                     */
                    async loadCurrentUserFromApi() {
                        try {
                            console.log('🌐 Buscando usuário atual na API...');
                            
                            const response = await fetch('/api/ldap/users', {
                                method: 'GET',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            });
                            
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}`);
                            }
                            
                            const data = await response.json();
                            console.log('📥 Dados recebidos da API:', data);
                            
                            if (data.success && data.users) {
                                const currentUser = data.users.find(u => u.uid === window.USER_UID);
                                if (currentUser && currentUser.organizationalUnits && currentUser.organizationalUnits.length > 0) {
                                    const adminOuEntry = currentUser.organizationalUnits.find(unit => {
                                        const role = typeof unit === 'string' ? 'user' : (unit.role || 'user');
                                        return role === 'admin';
                                    });
                                    
                                    if (adminOuEntry) {
                                        this.adminOu = typeof adminOuEntry === 'string' ? adminOuEntry : adminOuEntry.ou;
                                        console.log('✅ OU Admin obtida da API:', this.adminOu);
                                    } else if (currentUser.organizationalUnits.length > 0) {
                                        const firstOu = currentUser.organizationalUnits[0];
                                        this.adminOu = typeof firstOu === 'string' ? firstOu : firstOu.ou;
                                        console.log('🔄 Primeira OU obtida da API:', this.adminOu);
                                    }
                                }
                            }
                        } catch (error) {
                            console.error('❌ Erro ao buscar usuário atual na API:', error);
                        }
                    },

                    /**
                     * Carrega logs de operações
                     */
                    async loadLogs() {
                        try {
                            console.log('🔄 Carregando logs...');
                            const response = await fetch('/api/ldap/logs');
                            const data = await response.json();
                            if (data.success) {
                                this.logs = data.data;
                                console.log('✅ Logs carregados:', data.data.length);
                            } else {
                                console.log('⚠️ Erro na API de logs:', data.message);
                                this.showNotification(data.message, 'error');
                            }
                        } catch (error) {
                            console.log('❌ Erro de rede ao carregar logs:', error);
                            this.showNotification('Erro ao carregar logs', 'error');
                        }
                    },
                    pageNumbers(total) {
                        const current = this.activeTab === 'users' ? this.usersPage : (this.activeTab === 'organizational-units' ? this.ousPage : this.logsPage);
                        const delta = 2;
                        const range = [];
                        for (let i = Math.max(1, current - delta); i <= Math.min(total, current + delta); i++) {
                            range.push(i);
                        }
                        if (range[0] > 1) range.unshift(1);
                        if (range[range.length -1] < total) range.push(total);
                        return range;
                    },
                    openLogDrawer(log) {
                        // Se clicar no mesmo registro, mantém aberto
                        if (this.selectedLogId === log.id && this.showRightDrawer) {
                            return;
                        }
                        this.selectedLogId = log.id;
                        this.selectedLog = log;
                        this.showRightDrawer = true;
                    },
                    closeLogDrawer() {
                        this.showRightDrawer = false;
                        this.selectedLogId = null;
                        this.selectedLog = null;
                    },
                }
            }).mount('#app');
        }
    </script>
</body>
</html>
