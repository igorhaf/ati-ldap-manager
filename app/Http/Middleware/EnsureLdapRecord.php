<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LdapRecord\Container;
use LdapRecord\Connection;
use Symfony\Component\HttpFoundation\Response;

class EnsureLdapRecord
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Garantir que LdapRecord está inicializado antes de processar requisições LDAP
        $this->ensureLdapRecordInitialized();

        return $next($request);
    }

    /**
     * Garante que o LdapRecord Container está inicializado
     */
    private function ensureLdapRecordInitialized(): void
    {
        try {
            $connection = Container::getDefaultConnection();
            
            if (!$connection) {
                \Log::info('EnsureLdapRecord: Inicializando LdapRecord Container');
                
                $config = config('ldap.connections.default');
                if ($config && !empty($config['hosts'][0])) {
                    $connection = new Connection($config);
                    Container::addConnection($connection, 'default');
                    Container::setDefaultConnection('default');
                    
                    \Log::info('EnsureLdapRecord: LdapRecord Container inicializado', [
                        'host' => $config['hosts'][0]
                    ]);
                } else {
                    \Log::warning('EnsureLdapRecord: Configuração LDAP inválida ou incompleta');
                }
            }
        } catch (\Exception $e) {
            \Log::error('EnsureLdapRecord: Erro ao inicializar LdapRecord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 