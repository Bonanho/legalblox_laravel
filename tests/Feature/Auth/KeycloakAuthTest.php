<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\KeycloakService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeycloakAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configurar Keycloak para testes
        Config::set('keycloak', [
            'server_url' => 'http://keycloak.test:8080',
            'realm' => 'test-realm',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);
    }

    public function test_rejects_request_without_token()
    {
        $response = $this->getJson('/api/v1/users/me');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Token não fornecido',
        ]);
    }

    public function test_rejects_request_with_invalid_token()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([
                'active' => false,
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Token inválido',
        ]);
    }

    public function test_authenticates_user_with_valid_token()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'test@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Test User',
            'realm_access' => [
                'roles' => []
            ],
            'groups' => ['/org-123'],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'id_user_keycloak' => 'kc-user-123',
        ]);
    }

    public function test_performs_jit_provisioning_for_new_user()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'newuser@example.com',
            'sub' => 'kc-new-user-123',
            'name' => 'New User',
            'realm_access' => [
                'roles' => ['platform_admin']
            ],
            'groups' => ['/org-123'],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'newuser@example.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'id_user_keycloak' => 'kc-new-user-123',
            'name' => 'New User',
            'is_superuser' => true,
            'organization_id' => 'org-123',
            'is_active' => true,
        ]);
    }

    public function test_syncs_user_data_from_keycloak_on_each_request()
    {
        // Criar usuário existente
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'id_user_keycloak' => 'kc-user-123',
            'is_superuser' => false,
            'is_org_superuser' => false,
            'organization_id' => 'org-123',
        ]);

        // Keycloak agora retorna que o usuário é superuser
        $tokenInfo = [
            'active' => true,
            'email' => 'existing@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Updated Name',
            'realm_access' => [
                'roles' => ['platform_admin', 'org_admin']
            ],
            'groups' => ['/org-456'], // Mudou de organização
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);

        // Verificar que os dados foram sincronizados
        $user->refresh();
        $this->assertTrue($user->is_superuser);
        $this->assertTrue($user->is_org_superuser);
        $this->assertEquals('org-456', $user->organization_id);
        $this->assertEquals('Updated Name', $user->name);
    }

    public function test_rejects_inactive_user()
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'id_user_keycloak' => 'kc-user-123',
            'is_active' => false,
        ]);

        $tokenInfo = [
            'active' => true,
            'email' => 'inactive@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Inactive User',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Usuário inativo',
        ]);
    }

    public function test_accepts_token_without_bearer_prefix()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'test@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Test User',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $response = $this->withHeader('Authorization', 'valid-token-directly')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
    }

    public function test_handles_keycloak_server_error_gracefully()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([], 500),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
    }

    public function test_handles_missing_user_data_in_token()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([
                'active' => true,
                // Sem email ou sub
            ], 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Informações do usuário inválidas',
        ]);
    }

    public function test_sets_authenticated_user_correctly()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'auth@example.com',
            'sub' => 'kc-auth-123',
            'name' => 'Authenticated User',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer valid-token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJson([
            'email' => 'auth@example.com',
        ]);
    }
}

