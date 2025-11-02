<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'hashed_password'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_superuser' => 'boolean',
            'is_org_superuser' => 'boolean',
        ];
    }

    ####################
    ### RELATIONSHIP ###
    public function Organization () {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    ###############
    ### METHODS ###
    ###############

    /**
     * Cria um usuário a partir dos dados do Keycloak (JIT Provisioning)
     * 
     * @param array $userData Dados do usuário extraídos do token Keycloak
     * @return self
     */
    public static function createFromKeycloak(array $userData): self
    {
        // Validação básica
        if (empty($userData['email'])) {
            throw new \InvalidArgumentException('Email é obrigatório para criação de usuário');
        }

        if (empty($userData['keycloak_user_id'])) {
            throw new \InvalidArgumentException('ID do Keycloak é obrigatório para criação de usuário');
        }

        // Cria instância e define propriedades explicitamente
        $user = new self();
        $user->email = $userData['email'];
        $user->nm_full_name = $userData['name'] ?? '';
        $user->id_user_keycloak = $userData['keycloak_user_id'];
        $user->is_active = true;
        $user->is_superuser = $userData['is_superuser'] ?? false;
        $user->is_org_superuser = $userData['is_org_superuser'] ?? false;
        $user->organization_id = $userData['organization_id'] ?? null;
        $user->password = null; // Keycloak gerencia a senha
        
        $user->save();
        
        return $user;
    }

    /**
     * Cria um novo usuário manualmente (usado em controllers)
     * 
     * @param array $userData Dados do usuário
     * @return self
     */
    public static function createNew(array $userData): self
    {
        // Validação básica
        if (empty($userData['email'])) {
            throw new \InvalidArgumentException('Email é obrigatório');
        }

        if (empty($userData['password'])) {
            throw new \InvalidArgumentException('Senha é obrigatória');
        }

        // Cria instância e define propriedades explicitamente
        $user = new self();
        $user->email = $userData['email'];
        $user->nm_full_name = $userData['name'] ?? '';
        $user->password = \Illuminate\Support\Facades\Hash::make($userData['password']);
        $user->is_active = $userData['is_active'] ?? true;
        $user->is_superuser = $userData['is_superuser'] ?? false;
        $user->is_org_superuser = $userData['is_org_superuser'] ?? false;
        $user->organization_id = $userData['organization_id'] ?? null;
        
        $user->save();
        
        return $user;
    }

    /**
     * Atualiza perfil do usuário (nome, telefone, CPF, etc)
     * 
     * @param array $profileData Dados do perfil a atualizar
     * @return self
     */
    public function updateProfile(array $profileData): self
    {
        // Atualiza campos permitidos explicitamente
        if (isset($profileData['nm_full_name'])) {
            $this->nm_full_name = $profileData['nm_full_name'];
        }
        if (isset($profileData['nm_telefone_pais'])) {
            $this->nm_telefone_pais = $profileData['nm_telefone_pais'];
        }
        if (isset($profileData['nm_telefone_ddd'])) {
            $this->nm_telefone_ddd = $profileData['nm_telefone_ddd'];
        }
        if (isset($profileData['nm_telefone_numero'])) {
            $this->nm_telefone_numero = $profileData['nm_telefone_numero'];
        }
        if (isset($profileData['nu_cpf'])) {
            $this->nu_cpf = $profileData['nu_cpf'];
        }
        
        $this->save();
        
        return $this;
    }

    /**
     * Atualiza dados administrativos do usuário
     * 
     * @param array $adminData Dados administrativos
     * @return self
     */
    public function updateAdminData(array $adminData): self
    {
        // Atualiza campos permitidos explicitamente
        if (isset($adminData['email'])) {
            $this->email = $adminData['email'];
        }
        if (isset($adminData['name'])) {
            $this->nm_full_name = $adminData['name'];
        }
        if (isset($adminData['organization_id'])) {
            $this->organization_id = $adminData['organization_id'];
        }
        if (isset($adminData['is_superuser'])) {
            $this->is_superuser = $adminData['is_superuser'];
        }
        if (isset($adminData['is_org_superuser'])) {
            $this->is_org_superuser = $adminData['is_org_superuser'];
        }
        if (isset($adminData['is_active'])) {
            $this->is_active = $adminData['is_active'];
        }
        
        $this->save();
        
        return $this;
    }

    /**
     * Alterna status ativo/inativo do usuário
     * 
     * @param bool $isActive
     * @return self
     */
    public function setActive(bool $isActive): self
    {
        $this->is_active = $isActive;
        $this->save();
        
        return $this;
    }

    #############################
    ### AUTHORIZATION METHODS ###
    #############################

    /**
     * Verifica se o usuário pode gerenciar usuários
     * (se é superuser global ou org superuser)
     */
    public function canManageUsers(): bool
    {
        return $this->is_superuser || $this->is_org_superuser;
    }

    /**
     * Verifica se o usuário pode gerenciar usuários de uma organização específica
     */
    public function canManageUsersFromOrg(?string $organizationId): bool
    {
        if ($this->is_superuser) {
            return true; // Superusers podem gerenciar qualquer organização
        }

        if ($this->is_org_superuser && $this->organization_id === $organizationId) {
            return true; // Org superusers podem gerenciar apenas sua própria org
        }

        return false;
    }

    /**
     * Verifica se o usuário pode visualizar usuários de uma organização específica
     */
    public function canViewUsersFromOrg(?string $organizationId): bool
    {
        if ($this->is_superuser) {
            return true; // Superusers podem ver qualquer organização
        }

        if (!$this->organization_id) {
            return false; // Usuário sem organização não pode ver ninguém
        }

        return $this->organization_id === $organizationId;
    }

    /**
     * Verifica se o usuário pode modificar/atualizar outro usuário
     */
    public function canModifyUser(User $targetUser): bool
    {
        return $this->canManageUsersFromOrg($targetUser->organization_id);
    }

    /**
     * Verifica se o usuário pode deletar outro usuário
     * Apenas superusers globais podem deletar (regra mais restritiva)
     */
    public function canDeleteUser(User $targetUser): bool
    {
        return $this->is_superuser;
    }

    /**
     * Verifica se o usuário pode modificar status de superuser
     */
    public function canModifySuperuserStatus(): bool
    {
        return $this->is_superuser;
    }

    /**
     * Verifica se o usuário pode modificar a organização de outro usuário
     */
    public function canModifyUserOrganization(User $targetUser): bool
    {
        return $this->is_superuser;
    }

    /**
     * Retorna o ID da organização que o usuário pode gerenciar
     * Superusers retornam null (podem gerenciar todas)
     * Outros usuários retornam sua própria organização
     */
    public function getManageableOrganizationId(): ?string
    {
        if ($this->is_superuser) {
            return null; // Pode gerenciar todas
        }

        return $this->organization_id;
    }
}
