<!DOCTYPE html>
<html>
<head>
    <title>LDAP Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 800px; margin: 0 auto; }
        .error { background: #fee; border: 1px solid #fcc; padding: 20px; margin: 20px 0; border-radius: 5px; }
        .success { background: #efe; border: 1px solid #cfc; padding: 20px; margin: 20px 0; border-radius: 5px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a87; }
        .loading { color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciador LDAP - Versão Simples</h1>
        <p>Esta é uma versão simplificada para debug e testes básicos.</p>
        
        <button onclick="testConnection()">Testar Conexão LDAP</button>
        
        <div id="status"></div>
        
        <div id="users"></div>
    </div>

    <script>
        function showStatus(message, type = 'loading') {
            const statusDiv = document.getElementById('status');
            statusDiv.className = type;
            statusDiv.innerHTML = message;
        }

        async function testConnection() {
            showStatus('Testando conexão com o servidor LDAP...', 'loading');
            
            try {
                const response = await fetch('/api/ldap/users');
                const data = await response.json();
                
                if (data.success) {
                    showStatus(`✅ Conexão bem-sucedida! Encontrados ${data.data.length} usuários.`, 'success');
                    displayUsers(data.data);
                } else {
                    let errorMsg = '❌ Erro na conexão LDAP:<br>';
                    
                    if (data.message.includes('Invalid credentials')) {
                        errorMsg += `
                            <strong>Credenciais inválidas</strong><br>
                            <br>
                            <strong>Possíveis soluções:</strong><br>
                            • Verifique LDAP_USERNAME e LDAP_PASSWORD no arquivo .env<br>
                            • Confirme se o usuário tem permissões adequadas<br>
                            • Teste com uma ferramenta externa como Apache Directory Studio<br>
                            <br>
                            <strong>Configuração atual esperada:</strong><br>
                            • Host: ${window.location.hostname || 'localhost'}<br>
                            • Porta: 389<br>
                            • Base DN: dc=example,dc=com<br>
                        `;
                    } else {
                        errorMsg += data.message;
                    }
                    
                    showStatus(errorMsg, 'error');
                }
            } catch (error) {
                showStatus(`❌ Erro de rede: ${error.message}`, 'error');
            }
        }

        function displayUsers(users) {
            const usersDiv = document.getElementById('users');
            if (users.length === 0) {
                usersDiv.innerHTML = '<p>Nenhum usuário encontrado.</p>';
                return;
            }
            
            let html = '<h3>Usuários encontrados:</h3><ul>';
            users.forEach(user => {
                html += `<li><strong>${user.fullName}</strong> (${user.uid}) - ${user.mail.join(', ')}</li>`;
            });
            html += '</ul>';
            usersDiv.innerHTML = html;
        }

        // Teste automático ao carregar a página
        window.onload = function() {
            showStatus('Página carregada. Clique no botão para testar a conexão.', 'success');
        };
    </script>
</body>
</html> 