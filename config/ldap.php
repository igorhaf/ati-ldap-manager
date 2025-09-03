<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default LDAP Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the LDAP connections below you wish
    | to use as your default connection for all LDAP operations. Of
    | course you may add as many connections you'd like below.
    |
    */

    'default' => env('LDAP_CONNECTION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | LDAP Connections
    |--------------------------------------------------------------------------
    |
    | Below you may configure each LDAP connection your application requires
    | access to. Be sure to include a valid base DN - otherwise you may
    | not receive any results when performing LDAP search operations.
    |
    */

    'connections' => [

        'default' => [
            'hosts' => [env('LDAP_HOST', '127.0.0.1')],
            'username' => env('LDAP_USERNAME', 'cn=user,dc=local,dc=com'),
            'password' => env('LDAP_PASSWORD', 'secret'),
            'port' => env('LDAP_PORT', 389),
            'base_dn' => env('LDAP_BASE_DN', 'dc=local,dc=com'),
            'timeout' => env('LDAP_TIMEOUT', 10),
            'use_ssl' => env('LDAP_SSL', false),
            'use_tls' => env('LDAP_TLS', false),
            'use_sasl' => env('LDAP_SASL', false),
            'sasl_options' => [
                // 'mech' => 'GSSAPI',
            ],
            // Opções adicionais para resolver problemas de conexão em produção
            'options' => [
                // Protocolo LDAP (sempre usar versão 3)
                LDAP_OPT_PROTOCOL_VERSION => 3,
                // Timeout de rede
                LDAP_OPT_NETWORK_TIMEOUT => env('LDAP_NETWORK_TIMEOUT', 30),
                // Seguir referrals (geralmente deve ser false)
                LDAP_OPT_REFERRALS => env('LDAP_FOLLOW_REFERRALS', false),
                // Configurações TLS/SSL flexíveis baseadas no env
                LDAP_OPT_X_TLS_REQUIRE_CERT => env('LDAP_TLS_REQUIRE_CERT') === 'never' ? LDAP_OPT_X_TLS_NEVER : (env('LDAP_TLS_REQUIRE_CERT') === 'allow' ? LDAP_OPT_X_TLS_ALLOW : (env('LDAP_TLS_REQUIRE_CERT') === 'try' ? LDAP_OPT_X_TLS_TRY : LDAP_OPT_X_TLS_HARD)),
                // Restart TLS automaticamente se a conexão cair
                LDAP_OPT_RESTART => true,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Logging
    |--------------------------------------------------------------------------
    |
    | When LDAP logging is enabled, all LDAP search and authentication
    | operations are logged using the default application logging
    | driver. This can assist in debugging issues and more.
    |
    */

    'logging' => [
        'enabled' => env('LDAP_LOGGING', true),
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => env('LOG_LEVEL', 'info'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Cache
    |--------------------------------------------------------------------------
    |
    | LDAP caching enables the ability of caching search results using the
    | query builder. This is great for running expensive operations that
    | may take many seconds to complete, such as a pagination request.
    |
    */

    'cache' => [
        'enabled' => env('LDAP_CACHE', false),
        'driver' => env('CACHE_DRIVER', 'file'),
    ],

];
