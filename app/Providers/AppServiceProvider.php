<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LdapRecord\Container;
use LdapRecord\Connection;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inicializar LdapRecord Container corretamente
        $this->initializeLdapRecord();
    }

    /**
     * Inicializa o LdapRecord Container com a configuração correta
     */
    private function initializeLdapRecord(): void
    {
        try {
            // Obter configuração LDAP
            $ldapConfig = config('ldap.connections.default');
            
            if ($ldapConfig && !empty($ldapConfig['hosts'][0])) {
                // Limpar conexões existentes
                Container::flush();
                
                // Criar objeto Connection e adicionar ao Container
                $connection = new Connection($ldapConfig);
                Container::addConnection($connection, 'default');
                Container::setDefaultConnection('default');
                
                // Log para debug
                if (config('ldap.logging.enabled', false)) {
                    \Log::info('LdapRecord Container inicializado', [
                        'host' => $ldapConfig['hosts'][0],
                        'base_dn' => $ldapConfig['base_dn']
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Log do erro mas não falha a aplicação
            \Log::error('Erro ao inicializar LdapRecord Container', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
