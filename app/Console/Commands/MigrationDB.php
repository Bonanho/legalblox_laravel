<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Users;

class MigrationDB extends Command
{
    
    protected $signature = 'db:migration';

    protected $description = 'Command description';

    public function handle()
    {
        $users = Users::all();

        $teste = [
            'server_url'          => env('KEYCLOAK_SERVER_URL', 'http://localhost:8080'),
            'internal_server_url' => env('KEYCLOAK_INTERNAL_SERVER_URL', env('KEYCLOAK_SERVER_URL', 'http://localhost:8080')),
            'realm'               => env('KEYCLOAK_REALM', 'master'),
            'client_id'           => env('KEYCLOAK_CLIENT_ID', 'legalblox-backend'),
            'client_secret'       => env('KEYCLOAK_CLIENT_SECRET_KEY', env('KEYCLOAK_CLIENT_SECRET', '')),
            'allow_mock_user_switching' => env('KEYCLOAK_ALLOW_MOCK_USER_SWITCHING', false),
        ];
        dd( $teste );
    }
}
