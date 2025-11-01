<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_superuser_can_manage_users()
    {
        $user = User::factory()->superuser()->create();
        
        $this->assertTrue($user->canManageUsers());
    }

    public function test_org_superuser_can_manage_users()
    {
        $user = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        
        $this->assertTrue($user->canManageUsers());
    }

    public function test_regular_user_cannot_manage_users()
    {
        $user = User::factory()->create();
        
        $this->assertFalse($user->canManageUsers());
    }

    public function test_superuser_can_manage_users_from_any_organization()
    {
        $superuser = User::factory()->superuser()->create();
        
        $this->assertTrue($superuser->canManageUsersFromOrg('org-123'));
        $this->assertTrue($superuser->canManageUsersFromOrg('org-456'));
        $this->assertTrue($superuser->canManageUsersFromOrg(null));
    }

    public function test_org_superuser_can_only_manage_users_from_own_organization()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        
        $this->assertTrue($orgSuperuser->canManageUsersFromOrg('org-123'));
        $this->assertFalse($orgSuperuser->canManageUsersFromOrg('org-456'));
        $this->assertFalse($orgSuperuser->canManageUsersFromOrg(null));
    }

    public function test_regular_user_cannot_manage_users_from_any_organization()
    {
        $user = User::factory()->create(['organization_id' => 'org-123']);
        
        $this->assertFalse($user->canManageUsersFromOrg('org-123'));
        $this->assertFalse($user->canManageUsersFromOrg('org-456'));
    }

    public function test_superuser_can_view_users_from_any_organization()
    {
        $superuser = User::factory()->superuser()->create();
        
        $this->assertTrue($superuser->canViewUsersFromOrg('org-123'));
        $this->assertTrue($superuser->canViewUsersFromOrg('org-456'));
        $this->assertTrue($superuser->canViewUsersFromOrg(null));
    }

    public function test_user_can_view_users_from_own_organization()
    {
        $user = User::factory()->create(['organization_id' => 'org-123']);
        
        $this->assertTrue($user->canViewUsersFromOrg('org-123'));
        $this->assertFalse($user->canViewUsersFromOrg('org-456'));
    }

    public function test_user_without_organization_cannot_view_any_users()
    {
        $user = User::factory()->create(['organization_id' => null]);
        
        $this->assertFalse($user->canViewUsersFromOrg('org-123'));
        $this->assertFalse($user->canViewUsersFromOrg(null));
    }

    public function test_superuser_can_modify_any_user()
    {
        $superuser = User::factory()->superuser()->create();
        $targetUser = User::factory()->create(['organization_id' => 'org-123']);
        
        $this->assertTrue($superuser->canModifyUser($targetUser));
    }

    public function test_org_superuser_can_modify_users_from_own_organization()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        
        $targetUser = User::factory()->create(['organization_id' => 'org-123']);
        $otherUser = User::factory()->create(['organization_id' => 'org-456']);
        
        $this->assertTrue($orgSuperuser->canModifyUser($targetUser));
        $this->assertFalse($orgSuperuser->canModifyUser($otherUser));
    }

    public function test_only_superuser_can_delete_users()
    {
        $superuser = User::factory()->superuser()->create();
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        $regularUser = User::factory()->create(['organization_id' => 'org-123']);
        
        $targetUser = User::factory()->create();
        
        $this->assertTrue($superuser->canDeleteUser($targetUser));
        $this->assertFalse($orgSuperuser->canDeleteUser($targetUser));
        $this->assertFalse($regularUser->canDeleteUser($targetUser));
    }

    public function test_only_superuser_can_modify_superuser_status()
    {
        $superuser = User::factory()->superuser()->create();
        $orgSuperuser = User::factory()->orgSuperuser()->create();
        $regularUser = User::factory()->create();
        
        $this->assertTrue($superuser->canModifySuperuserStatus());
        $this->assertFalse($orgSuperuser->canModifySuperuserStatus());
        $this->assertFalse($regularUser->canModifySuperuserStatus());
    }

    public function test_only_superuser_can_modify_user_organization()
    {
        $superuser = User::factory()->superuser()->create();
        $orgSuperuser = User::factory()->orgSuperuser()->create();
        
        $targetUser = User::factory()->create();
        
        $this->assertTrue($superuser->canModifyUserOrganization($targetUser));
        $this->assertFalse($orgSuperuser->canModifyUserOrganization($targetUser));
    }

    public function test_superuser_returns_null_for_manageable_organization_id()
    {
        $superuser = User::factory()->superuser()->create();
        
        $this->assertNull($superuser->getManageableOrganizationId());
    }

    public function test_org_superuser_returns_own_organization_id()
    {
        $orgSuperuser = User::factory()
            ->orgSuperuser()
            ->create(['organization_id' => 'org-123']);
        
        $this->assertEquals('org-123', $orgSuperuser->getManageableOrganizationId());
    }

    public function test_regular_user_returns_own_organization_id()
    {
        $user = User::factory()->create(['organization_id' => 'org-123']);
        
        $this->assertEquals('org-123', $user->getManageableOrganizationId());
    }

    public function test_can_create_user_from_keycloak_data()
    {
        $keycloakData = [
            'email' => 'test@example.com',
            'keycloak_user_id' => 'kc-user-123',
            'name' => 'Test User',
            'is_superuser' => true,
            'is_org_superuser' => false,
            'organization_id' => 'org-123',
        ];

        $user = User::createFromKeycloak($keycloakData);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'id_user_keycloak' => 'kc-user-123',
            'name' => 'Test User',
            'is_superuser' => true,
            'is_org_superuser' => false,
            'organization_id' => 'org-123',
            'is_active' => true,
        ]);
        
        $this->assertNull($user->password);
    }

    public function test_creating_user_from_keycloak_requires_email()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email é obrigatório para criação de usuário');

        User::createFromKeycloak([
            'keycloak_user_id' => 'kc-123',
        ]);
    }

    public function test_creating_user_from_keycloak_requires_keycloak_user_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ID do Keycloak é obrigatório para criação de usuário');

        User::createFromKeycloak([
            'email' => 'test@example.com',
        ]);
    }

    public function test_can_create_new_user_with_password()
    {
        $userData = [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'password' => 'password123',
            'is_active' => true,
            'is_superuser' => false,
        ];

        $user = User::createNew($userData);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'is_active' => true,
            'is_superuser' => false,
        ]);
        
        $this->assertNotNull($user->password);
        $this->assertNotEquals('password123', $user->password); // Deve estar hasheado
    }

    public function test_can_update_user_profile()
    {
        $user = User::factory()->create();

        $user->updateProfile([
            'nm_full_name' => 'Full Name',
            'nm_telefone_pais' => '+55',
            'nm_telefone_ddd' => '11',
            'nm_telefone_numero' => '987654321',
            'nu_cpf' => '12345678901',
        ]);

        $this->assertEquals('Full Name', $user->nm_full_name);
        $this->assertEquals('+55', $user->nm_telefone_pais);
        $this->assertEquals('11', $user->nm_telefone_ddd);
        $this->assertEquals('987654321', $user->nm_telefone_numero);
        $this->assertEquals('12345678901', $user->nu_cpf);
    }

    public function test_can_update_admin_data()
    {
        $user = User::factory()->create();

        $user->updateAdminData([
            'email' => 'newemail@example.com',
            'name' => 'New Name',
            'organization_id' => 'org-456',
            'is_superuser' => true,
            'is_org_superuser' => false,
            'is_active' => false,
        ]);

        $this->assertEquals('newemail@example.com', $user->email);
        $this->assertEquals('New Name', $user->name);
        $this->assertEquals('org-456', $user->organization_id);
        $this->assertTrue($user->is_superuser);
        $this->assertFalse($user->is_org_superuser);
        $this->assertFalse($user->is_active);
    }

    public function test_can_set_user_active_status()
    {
        $user = User::factory()->create(['is_active' => true]);

        $user->setActive(false);
        $this->assertFalse($user->is_active);

        $user->setActive(true);
        $this->assertTrue($user->is_active);
    }
}

