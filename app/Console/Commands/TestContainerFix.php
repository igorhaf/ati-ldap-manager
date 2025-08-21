<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LdapRecord\Container;
use LdapRecord\Connection;

class TestContainerFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:container-fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Testa se a correção do LdapRecord Container funcionou';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Teste da Correção do Container');
        $this->info('=================================');

        try {
            // 1. Limpar container
            $this->info('1️⃣ Limpando container...');
            Container::flush();
            $this->info('✅ Container limpo');

            // 2. Obter configuração
            $this->info('2️⃣ Obtendo configuração...');
            $config = config('ldap.connections.default');
            $this->line("Host: " . $config['hosts'][0]);
            $this->info('✅ Configuração obtida');

            // 3. Criar conexão
            $this->info('3️⃣ Criando objeto Connection...');
            $connection = new Connection($config);
            $this->info('✅ Objeto Connection criado');

            // 4. Adicionar ao container
            $this->info('4️⃣ Adicionando ao Container...');
            Container::addConnection($connection, 'default');
            Container::setDefaultConnection('default');
            $this->info('✅ Connection adicionado ao Container');

            // 5. Testar obtenção da conexão
            $this->info('5️⃣ Testando obtenção da conexão...');
            $retrievedConnection = Container::getDefaultConnection();
            
            if ($retrievedConnection) {
                $this->info('✅ Conexão obtida com sucesso');
                $this->line('   Tipo: ' . get_class($retrievedConnection));
            } else {
                $this->error('❌ Falha ao obter conexão');
                return 1;
            }

            // 6. Testar conectividade
            $this->info('6️⃣ Testando conectividade...');
            $retrievedConnection->connect();
            
            if ($retrievedConnection->isConnected()) {
                $this->info('✅ Conectado ao LDAP');
            } else {
                $this->warn('⚠️  Estado da conexão indefinido');
            }

            // 7. Testar autenticação
            $this->info('7️⃣ Testando autenticação...');
            $auth = $retrievedConnection->auth()->attempt($config['username'], $config['password']);
            
            if ($auth) {
                $this->info('✅ Autenticação bem-sucedida');
            } else {
                $this->error('❌ Falha na autenticação');
                return 1;
            }

            $this->info("\n🎉 Correção do Container funcionou perfeitamente!");
            $this->line('📋 Agora os outros comandos devem funcionar:');
            $this->line('   • sudo ./vendor/bin/sail artisan test:ldap-record');
            $this->line('   • sudo ./vendor/bin/sail artisan debug:ldap-structure');

        } catch (\Exception $e) {
            $this->error("❌ Erro: " . $e->getMessage());
            $this->error("   Classe: " . get_class($e));
            $this->error("   Arquivo: " . $e->getFile() . ':' . $e->getLine());
            return 1;
        }

        return 0;
    }
} 