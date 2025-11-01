<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

    /**
     * Helper para simular autenticação de um usuário
     */
    protected function authenticateUser(User $user): void
    {
        $tokenInfo = [
            'active' => true,
            'email' => $user->email,
            'sub' => $user->id_user_keycloak ?? 'kc-' . $user->id,
            'name' => $user->name,
            'realm_access' => [
                'roles' => array_filter([
                    $user->is_superuser ? 'platform_admin' : null,
                    $user->is_org_superuser ? 'org_admin' : null,
                ])
            ],
            'groups' => $user->organization_id ? ['/' . $user->organization_id] : [],
        ];

        Http::fake([
            'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
        ]);
    }

    public function test_superuser_can_list_all_users()
    {
        $superuser = User::factory()->superuser()->create();
        $user1 = User::factory()->create(['organization_id' => 'org-123']);
        $user2 = User::factory()->create(['organization_id' => 'org-456']);

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJsonCount(3); // superuser + user1 + user2
    }

    public function test_org_superuser_can_only_list_users_from_own_organization()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        
        $user1 = User::factory()->create(['organization_id' => 'org-123']);
        $user2 = User::factory()->create(['organization_id' => 'org-456']);

        $this->authenticateUser($orgSuperuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJsonCount(2); // orgSuperuser + user1
        $this->assertNotContains($user2->id, collect($response->json())->pluck('id')->toArray());
    }

    public function test_regular_user_can_only_list_users_from_own_organization()
    {
        $user = User::factory()->create(['organization_id' => 'org-123']);
        $sameOrgUser = User::factory()->create(['organization_id' => 'org-123']);
        $otherOrgUser = User::factory()->create(['organization_id' => 'org-456']);

        $this->authenticateUser($user);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
        $response->assertJsonCount(2); // user + sameOrgUser
    }

    public function test_can_get_current_user()
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'name' => 'Current User',
        ]);

        $this->authenticateUser($user);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson('/api/v1/users/me');

        $response->assertStatus(200);
        $response->assertJson([
            'email' => 'current@example.com',
            'name' => 'Current User',
        ]);
    }

    public function test_superuser_can_view_any_user()
    {
        $superuser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create(['organization_id' => 'org-123']);

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $targetUser->id,
            'email' => $targetUser->email,
        ]);
    }

    public function test_user_cannot_view_user_from_different_organization()
    {
        $user = User::factory()->create(['organization_id' => 'org-123']);
        $otherUser = User::factory()->create(['organization_id' => 'org-456']);

        $this->authenticateUser($user);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->getJson("/api/v1/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    public function test_superuser_can_create_user()
    {
        $superuser = User::factory()->superuser()->create();

        $this->authenticateUser($superuser);

        $userData = [
            'email' => 'new@example.com',
            'name' => 'New User',
            'password' => 'password123',
            'organization_id' => 'org-123',
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'organization_id' => 'org-123',
        ]);
    }

    public function test_org_superuser_can_create_user_in_own_organization()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);

        $this->authenticateUser($orgSuperuser);

        $userData = [
            'email' => 'new@example.com',
            'name' => 'New User',
            'password' => 'password123',
            'organization_id' => 'org-123', // Mesma organização
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(201);
    }

    public function test_org_superuser_cannot_create_superuser()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);

        $this->authenticateUser($orgSuperuser);

        $userData = [
            'email' => 'new@example.com',
            'name' => 'New User',
            'password' => 'password123',
            'is_superuser' => true, // Tentando criar superuser
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(403);
    }

    public function test_only_superuser_can_delete_user()
    {
        $superuser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->deleteJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    public function test_org_superuser_cannot_delete_user()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        $targetUser = User::factory()->create(['organization_id' => 'org-123']);

        $this->authenticateUser($orgSuperuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->deleteJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(403);
    }

    public function test_cannot_delete_own_account()
    {
        $superuser = User::factory()->superuser()->create();

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->deleteJson("/api/v1/users/{$superuser->id}");

        $response->assertStatus(400);
    }

    public function test_can_update_own_profile()
    {
        $user = User::factory()->create();

        $this->authenticateUser($user);

        $profileData = [
            'nm_full_name' => 'Full Name',
            'nm_telefone_pais' => '+55',
            'nm_telefone_ddd' => '11',
            'nm_telefone_numero' => '987654321',
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->putJson('/api/v1/users/me/profile', $profileData);

        $response->assertStatus(200);
        $this->assertEquals('Full Name', $user->fresh()->nm_full_name);
    }

    public function test_superuser_can_update_any_user()
    {
        $superuser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create();

        $this->authenticateUser($superuser);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->putJson("/api/v1/users/{$targetUser->id}", $updateData);

        $response->assertStatus(200);
        $this->assertEquals('Updated Name', $targetUser->fresh()->name);
    }

    public function test_org_superuser_cannot_modify_superuser_status()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        $targetUser = User::factory()->create(['organization_id' => 'org-123']);

        $this->authenticateUser($orgSuperuser);

        $updateData = [
            'is_superuser' => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->putJson("/api/v1/users/{$targetUser->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_can_activate_user()
    {
        $superuser = User::factory()->superuser()->create();
        $inactiveUser = User::factory()->create(['is_active' => false]);

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->postJson("/api/v1/users/{$inactiveUser->id}/activate");

        $response->assertStatus(200);
        $this->assertTrue($inactiveUser->fresh()->is_active);
    }

    public function test_can_deactivate_user()
    {
        $superuser = User::factory()->superuser()->create();
        $activeUser = User::factory()->create(['is_active' => true]);

        $this->authenticateUser($superuser);

        $response = $this->withHeader('Authorization', 'Bearer token')
            ->postJson("/api/v1/users/{$activeUser->id}/deactivate");

        $response->assertStatus(200);
        $this->assertFalse($activeUser->fresh()->is_active);
    }
}

