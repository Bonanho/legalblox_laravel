<?php

namespace Tests\Unit\Services;

use App\Services\KeycloakService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KeycloakServiceTest extends TestCase
{
    protected KeycloakService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Este teste não precisa de banco de dados
        $this->service = new KeycloakService();
        
        // Configurar Keycloak para testes
        Config::set('keycloak', [
            'server_url' => 'http://keycloak.test:8080',
            'realm' => 'test-realm',
            'client_id' => 'test-client',
            'client_secret' => 'test-secret',
        ]);
    }

    public function test_validates_active_token_successfully()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([
                'active' => true,
                'email' => 'user@example.com',
                'sub' => 'kc-user-123',
                'realm_access' => [
                    'roles' => ['platform_admin']
                ],
                'groups' => ['/org-123'],
            ], 200),
        ]);

        $token = 'valid-jwt-token';
        $result = $this->service->validateToken($token);

        $this->assertNotNull($result);
        $this->assertTrue($result['active']);
        $this->assertEquals('user@example.com', $result['email']);
    }

    public function test_returns_null_for_inactive_token()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([
                'active' => false,
            ], 200),
        ]);

        $token = 'expired-token';
        $result = $this->service->validateToken($token);

        $this->assertNull($result);
    }

    public function test_returns_null_when_introspection_fails()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([], 401),
        ]);

        $token = 'invalid-token';
        $result = $this->service->validateToken($token);

        $this->assertNull($result);
    }

    public function test_returns_null_when_keycloak_server_is_unavailable()
    {
        Http::fake([
            'keycloak.test:8080/*' => Http::response([], 500),
        ]);

        $token = 'any-token';
        $result = $this->service->validateToken($token);

        $this->assertNull($result);
    }

    public function test_extracts_user_data_from_token_successfully()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Test User',
            'realm_access' => [
                'roles' => ['platform_admin', 'org_admin']
            ],
            'groups' => ['/org-123'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertNotNull($userData);
        $this->assertEquals('user@example.com', $userData['email']);
        $this->assertEquals('kc-user-123', $userData['keycloak_user_id']);
        $this->assertEquals('Test User', $userData['name']);
        $this->assertTrue($userData['is_superuser']);
        $this->assertTrue($userData['is_org_superuser']);
        $this->assertEquals('org-123', $userData['organization_id']);
    }

    public function test_identifies_superuser_from_platform_admin_role()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'admin@example.com',
            'sub' => 'kc-admin-123',
            'name' => 'Admin User',
            'realm_access' => [
                'roles' => ['platform_admin']
            ],
            'groups' => [],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertTrue($userData['is_superuser']);
        $this->assertFalse($userData['is_org_superuser']);
    }

    public function test_identifies_org_superuser_from_org_admin_role()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'orgadmin@example.com',
            'sub' => 'kc-org-admin-123',
            'name' => 'Org Admin',
            'realm_access' => [
                'roles' => ['org_admin']
            ],
            'groups' => ['/org-123'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertFalse($userData['is_superuser']);
        $this->assertTrue($userData['is_org_superuser']);
        $this->assertEquals('org-123', $userData['organization_id']);
    }

    public function test_regular_user_has_no_special_roles()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'name' => 'Regular User',
            'realm_access' => [
                'roles' => ['user']
            ],
            'groups' => ['/org-123'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertFalse($userData['is_superuser']);
        $this->assertFalse($userData['is_org_superuser']);
    }

    public function test_returns_null_when_email_is_missing()
    {
        $tokenInfo = [
            'active' => true,
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
        ];

        $result = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertNull($result);
    }

    public function test_returns_null_when_user_id_is_missing()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'realm_access' => ['roles' => []],
        ];

        $result = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertNull($result);
    }

    public function test_extracts_organization_id_from_groups()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
            'groups' => ['/org-123'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertEquals('org-123', $userData['organization_id']);
    }

    public function test_handles_groups_without_leading_slash()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
            'groups' => ['org-123'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertEquals('org-123', $userData['organization_id']);
    }

    public function test_returns_null_organization_when_no_groups()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertNull($userData['organization_id']);
    }

    public function test_uses_first_group_as_organization()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
            'groups' => ['/org-123', '/org-456'],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertEquals('org-123', $userData['organization_id']);
    }

    public function test_gets_realm_from_config()
    {
        $realm = $this->service->getRealm();

        $this->assertEquals('test-realm', $realm);
    }

    public function test_gets_server_url_from_config()
    {
        $serverUrl = $this->service->getServerUrl();

        $this->assertEquals('http://keycloak.test:8080', $serverUrl);
    }

    public function test_handles_empty_name_gracefully()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'name' => '',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertEquals('', $userData['name']);
    }

    public function test_handles_missing_name_gracefully()
    {
        $tokenInfo = [
            'active' => true,
            'email' => 'user@example.com',
            'sub' => 'kc-user-123',
            'realm_access' => ['roles' => []],
            'groups' => [],
        ];

        $userData = $this->service->extractUserDataFromToken($tokenInfo);

        $this->assertEquals('', $userData['name']);
    }
}

