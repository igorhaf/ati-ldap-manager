<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - LDAP Manager</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test { 
            margin: 10px 0; 
            padding: 10px; 
            border-left: 4px solid #007cba; 
            background: #f8f9fa; 
        }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .success { border-left-color: #28a745; background: #d4edda; }
        button { 
            background: #007cba; 
            color: white; 
            border: none; 
            padding: 8px 16px; 
            border-radius: 4px; 
            cursor: pointer; 
            margin: 5px; 
        }
        button:hover { background: #005a87; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Diagnóstico - LDAP Manager</h1>
        <p>Esta página vai nos ajudar a identificar o problema da página em branco.</p>
        
        <div class="test success">
            <strong>✅ Laravel Funcionando</strong><br>
            Se você está vendo esta página, o Laravel está carregando views corretamente.
        </div>
        
        <div class="test">
            <strong>🧪 Testes JavaScript</strong><br>
            <button onclick="testBasicJS()">Testar JavaScript Básico</button>
            <button onclick="testVueLoading()">Testar Vue.js</button>
            <button onclick="testAPI()">Testar API LDAP</button>
            <button onclick="testFetch()">Testar Fetch</button>
        </div>
        
        <div id="results"></div>
        
        <div class="test">
            <strong>📋 Informações do Sistema</strong><br>
            <strong>PHP:</strong> {{ phpversion() }}<br>
            <strong>Laravel:</strong> {{ app()->version() }}<br>
            <strong>Timestamp:</strong> {{ now() }}<br>
            <strong>Environment:</strong> {{ app()->environment() }}
        </div>
        
        <div class="test">
            <strong>🔗 Links de Navegação</strong><br>
            <a href="/">← Página Principal</a> | 
            <a href="/ldap-manager">LDAP Manager</a> | 
            <a href="/phpinfo">PHP Info</a>
        </div>
    </div>

    <!-- Teste de carregamento Vue.js -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    
    <script>
        function addResult(title, content, type = 'test') {
            const results = document.getElementById('results');
            const div = document.createElement('div');
            div.className = `test ${type}`;
            div.innerHTML = `<strong>${title}</strong><br>${content}`;
            results.appendChild(div);
        }

        function testBasicJS() {
            try {
                addResult('✅ JavaScript Básico', 'JavaScript está funcionando corretamente!', 'success');
                console.log('JavaScript funcionando');
            } catch (error) {
                addResult('❌ JavaScript Básico', `Erro: ${error.message}`, 'error');
            }
        }

        function testVueLoading() {
            try {
                if (typeof window.Vue !== 'undefined') {
                    addResult('✅ Vue.js Carregado', `Vue.js versão disponível. Tipo: ${typeof window.Vue.createApp}`, 'success');
                    
                    // Teste de criação de app Vue simples
                    try {
                        const { createApp } = window.Vue;
                        const testApp = createApp({
                            data() {
                                return { message: 'Vue funcionando!' }
                            }
                        });
                        addResult('✅ Vue.js App Creation', 'Vue.js pode criar aplicações corretamente', 'success');
                    } catch (vueError) {
                        addResult('❌ Vue.js App Creation', `Erro ao criar app Vue: ${vueError.message}`, 'error');
                    }
                } else {
                    addResult('❌ Vue.js Não Carregado', 'window.Vue não está disponível', 'error');
                }
            } catch (error) {
                addResult('❌ Vue.js Test', `Erro no teste: ${error.message}`, 'error');
            }
        }

        function testAPI() {
            addResult('🔄 API Test', 'Testando conexão com API...', 'test');
            
            fetch('/api/ldap/users')
                .then(response => {
                    addResult('✅ API Response', `Status: ${response.status} - ${response.statusText}`, 'success');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        addResult('✅ API Data', `Sucesso! ${data.data.length} usuários encontrados`, 'success');
                    } else {
                        addResult('⚠️ API Warning', `API respondeu mas com erro: ${data.message}`, 'test');
                    }
                })
                .catch(error => {
                    addResult('❌ API Error', `Erro na API: ${error.message}`, 'error');
                });
        }

        function testFetch() {
            addResult('🔄 Fetch Test', 'Testando capacidade de fetch...', 'test');
            
            fetch('/phpinfo')
                .then(response => {
                    addResult('✅ Fetch Working', `Fetch funcionando! Status: ${response.status}`, 'success');
                })
                .catch(error => {
                    addResult('❌ Fetch Error', `Erro no fetch: ${error.message}`, 'error');
                });
        }

        // Teste automático ao carregar
        window.addEventListener('load', function() {
            addResult('🎯 Página Carregada', 'DOM e recursos carregados com sucesso', 'success');
            
            // Verificar se há erros no console
            const originalError = console.error;
            const errors = [];
            console.error = function(...args) {
                errors.push(args.join(' '));
                originalError.apply(console, args);
            };
            
            setTimeout(() => {
                if (errors.length > 0) {
                    addResult('⚠️ Console Errors', `${errors.length} erros detectados:<br><pre>${errors.join('\n')}</pre>`, 'error');
                } else {
                    addResult('✅ Console Clean', 'Nenhum erro no console detectado', 'success');
                }
            }, 2000);
        });
    </script>
</body>
</html> 