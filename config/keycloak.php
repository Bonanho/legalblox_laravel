<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Keycloak Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para integração com Keycloak para autenticação
    | baseada em JWT tokens.
    |
    */

    'server_url'          => env('KEYCLOAK_SERVER_URL', 'http://localhost:8080'),
    'internal_server_url' => env('KEYCLOAK_INTERNAL_SERVER_URL', env('KEYCLOAK_SERVER_URL', 'http://localhost:8080')),
    'realm'               => env('KEYCLOAK_REALM', 'master'),
    'client_id'           => env('KEYCLOAK_CLIENT_ID', 'legalblox-backend'),
    'client_secret'       => env('KEYCLOAK_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configurações adicionais de autenticação:
    | - allow_mock_user_switching: Permite superusuários alternarem entre usuários
    |   (apenas para debugging)
    |
    */

    'allow_mock_user_switching' => env('KEYCLOAK_ALLOW_MOCK_USER_SWITCHING', false),
];

