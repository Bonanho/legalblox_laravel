<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    /**
     * Nome da tabela
     */
    protected $table = 'organizations';

    /**
     * Indica se o modelo deve usar timestamps automáticos (created_at/updated_at)
     * Como a tabela usa dt_cadastro e dt_atualizacao, desabilitamos os timestamps padrão
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
            'config_json' => 'array',
            'dt_cadastro' => 'datetime',
            'dt_atualizacao' => 'datetime',
        ];
    }

    /**
     * Override do método para usar dt_cadastro ao invés de created_at
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->dt_cadastro)) {
                $model->dt_cadastro = now();
            }
            if (empty($model->dt_atualizacao)) {
                $model->dt_atualizacao = now();
            }
        });

        static::updating(function ($model) {
            $model->dt_atualizacao = now();
        });
    }

    // Constantes para Status
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE   = 1;
    const STATUS_SANDBOX  = 2;
    const STATUS = [
        self::STATUS_INACTIVE => "Inativo",
        self::STATUS_ACTIVE   => "Ativo",
        self::STATUS_SANDBOX  => "Sandbox"
    ];

    // Constantes para Tipo
    const TYPE_CORP   = 1;
    const TYPE_RETAIL = 2;
    const TYPE        = [
        self::TYPE_CORP   => "Corp",
        self::TYPE_RETAIL => "Varejo"
    ];

    /**
     * Retorna o label do status
     */
    public function getStatus()
    {
        return self::STATUS[$this->org_status_id] ?? null;
    }

    /**
     * Retorna o label do tipo
     */
    public function getType()
    {
        return self::TYPE[$this->org_type_id] ?? null;
    }

    /**
     * Relacionamento com usuários
     */
    public function users()
    {
        return $this->hasMany(User::class, 'organization_id', 'organization_id');
    }

    /**
     * Cria uma nova organização
     * 
     * @param array $orgData Dados da organização
     * @return self
     */
    public static function createNew(array $orgData): self
    {
        // Validação básica
        if (empty($orgData['organization_id'])) {
            throw new \InvalidArgumentException('organization_id é obrigatório');
        }

        if (empty($orgData['ds_nome_razao_social'])) {
            throw new \InvalidArgumentException('ds_nome_razao_social é obrigatório');
        }

        // Verifica se organization_id já existe
        $existing = static::findByOrganizationId($orgData['organization_id']);
        if ($existing) {
            throw new \InvalidArgumentException("Organization with ID '{$orgData['organization_id']}' already exists in DB.");
        }

        // Verifica se CNPJ já existe (se fornecido)
        if (!empty($orgData['nu_cnpj'])) {
            $existingCnpj = static::findByCnpj($orgData['nu_cnpj']);
            if ($existingCnpj) {
                throw new \InvalidArgumentException("Organization with CNPJ '{$orgData['nu_cnpj']}' already exists in DB.");
            }
        }

        // Cria instância e define propriedades explicitamente
        $organization = new self();
        $organization->organization_id = $orgData['organization_id'];
        $organization->ds_nome_razao_social = $orgData['ds_nome_razao_social'];
        $organization->ds_nome_fantasia = $orgData['ds_nome_fantasia'] ?? null;
        $organization->nu_cnpj = $orgData['nu_cnpj'] ?? null;
        $organization->nu_cnae_principal = $orgData['nu_cnae_principal'] ?? null;
        $organization->ds_cnae_principal = $orgData['ds_cnae_principal'] ?? null;
        $organization->ds_endereco_logradouro = $orgData['ds_endereco_logradouro'] ?? null;
        $organization->ds_endereco_numero = $orgData['ds_endereco_numero'] ?? null;
        $organization->ds_endereco_complemento = $orgData['ds_endereco_complemento'] ?? null;
        $organization->ds_bairro = $orgData['ds_bairro'] ?? null;
        $organization->ds_municipio_nome = $orgData['ds_municipio_nome'] ?? null;
        $organization->cd_municipio_ibge = $orgData['cd_municipio_ibge'] ?? null;
        $organization->sg_uf = $orgData['sg_uf'] ?? null;
        $organization->ds_cep = $orgData['ds_cep'] ?? null;
        $organization->nm_contato_principal = $orgData['nm_contato_principal'] ?? null;
        $organization->nm_email_contato_principal = $orgData['nm_email_contato_principal'] ?? null;
        $organization->nu_telefone_contato_principal = $orgData['nu_telefone_contato_principal'] ?? null;
        $organization->id_tenant_keycloak = $orgData['id_tenant_keycloak'] ?? null;
        $organization->org_type_id = $orgData['org_type_id'] ?? self::TYPE_CORP;
        $organization->org_status_id = $orgData['org_status_id'] ?? self::STATUS_ACTIVE;
        $organization->config_json = $orgData['config_json'] ?? null;
        $organization->ds_observacoes = $orgData['ds_observacoes'] ?? null;
        $organization->whatsapp_enabled = $orgData['whatsapp_enabled'] ?? false;
        $organization->dt_cadastro = now();
        $organization->dt_atualizacao = now();
        
        $organization->save();
        
        return $organization;
    }

    /**
     * Atualiza dados da organização
     * 
     * @param array $orgData Dados da organização a atualizar
     * @return self
     */
    public function updateOrganizationData(array $orgData): self
    {
        // Atualiza campos permitidos explicitamente
        if (isset($orgData['ds_nome_razao_social'])) {
            $this->ds_nome_razao_social = $orgData['ds_nome_razao_social'];
        }
        if (isset($orgData['ds_nome_fantasia'])) {
            $this->ds_nome_fantasia = $orgData['ds_nome_fantasia'];
        }
        if (isset($orgData['nu_cnpj'])) {
            // Verifica se CNPJ está sendo alterado e se conflita com outro
            if ($orgData['nu_cnpj'] !== $this->nu_cnpj) {
                $existingCnpj = static::findByCnpj($orgData['nu_cnpj']);
                if ($existingCnpj && $existingCnpj->id !== $this->id) {
                    throw new \InvalidArgumentException("Organization with CNPJ '{$orgData['nu_cnpj']}' already exists");
                }
            }
            $this->nu_cnpj = $orgData['nu_cnpj'];
        }
        if (isset($orgData['nu_cnae_principal'])) {
            $this->nu_cnae_principal = $orgData['nu_cnae_principal'];
        }
        if (isset($orgData['ds_cnae_principal'])) {
            $this->ds_cnae_principal = $orgData['ds_cnae_principal'];
        }
        if (isset($orgData['ds_endereco_logradouro'])) {
            $this->ds_endereco_logradouro = $orgData['ds_endereco_logradouro'];
        }
        if (isset($orgData['ds_endereco_numero'])) {
            $this->ds_endereco_numero = $orgData['ds_endereco_numero'];
        }
        if (isset($orgData['ds_endereco_complemento'])) {
            $this->ds_endereco_complemento = $orgData['ds_endereco_complemento'];
        }
        if (isset($orgData['ds_bairro'])) {
            $this->ds_bairro = $orgData['ds_bairro'];
        }
        if (isset($orgData['ds_municipio_nome'])) {
            $this->ds_municipio_nome = $orgData['ds_municipio_nome'];
        }
        if (isset($orgData['cd_municipio_ibge'])) {
            $this->cd_municipio_ibge = $orgData['cd_municipio_ibge'];
        }
        if (isset($orgData['sg_uf'])) {
            $this->sg_uf = $orgData['sg_uf'];
        }
        if (isset($orgData['ds_cep'])) {
            $this->ds_cep = $orgData['ds_cep'];
        }
        if (isset($orgData['nm_contato_principal'])) {
            $this->nm_contato_principal = $orgData['nm_contato_principal'];
        }
        if (isset($orgData['nm_email_contato_principal'])) {
            $this->nm_email_contato_principal = $orgData['nm_email_contato_principal'];
        }
        if (isset($orgData['nu_telefone_contato_principal'])) {
            $this->nu_telefone_contato_principal = $orgData['nu_telefone_contato_principal'];
        }
        if (isset($orgData['id_tenant_keycloak'])) {
            $this->id_tenant_keycloak = $orgData['id_tenant_keycloak'];
        }
        if (isset($orgData['org_type_id'])) {
            $this->org_type_id = $orgData['org_type_id'];
        }
        if (isset($orgData['org_status_id'])) {
            $this->org_status_id = $orgData['org_status_id'];
        }
        if (isset($orgData['config_json'])) {
            $this->config_json = $orgData['config_json'];
        }
        if (isset($orgData['ds_observacoes'])) {
            $this->ds_observacoes = $orgData['ds_observacoes'];
        }
        if (isset($orgData['whatsapp_enabled'])) {
            $this->whatsapp_enabled = $orgData['whatsapp_enabled'];
        }
        
        $this->dt_atualizacao = now();
        $this->save();
        
        return $this;
    }

    /**
     * Buscar organização por organization_id
     */
    public static function findByOrganizationId(string $organizationId): ?self
    {
        return static::where('organization_id', $organizationId)->first();
    }

    /**
     * Buscar organização por CNPJ
     */
    public static function findByCnpj(string $cnpj): ?self
    {
        return static::where('nu_cnpj', $cnpj)->first();
    }

    /**
     * Buscar organizações por status
     */
    public static function getByStatus(array $statusIds, int $skip = 0, int $limit = 1000)
    {
        return static::whereIn('org_status_id', $statusIds)
            ->skip($skip)
            ->take($limit)
            ->get();
    }

    /**
     * Buscar organizações por termo de busca
     */
    public static function search(string $searchTerm, ?int $statusFilter = null, int $skip = 0, int $limit = 100)
    {
        $query = static::where(function($q) use ($searchTerm) {
            $q->where('ds_nome_razao_social', 'like', "%{$searchTerm}%")
              ->orWhere('ds_nome_fantasia', 'like', "%{$searchTerm}%")
              ->orWhere('organization_id', 'like', "%{$searchTerm}%");
        });

        if ($statusFilter !== null) {
            $query->where('org_status_id', $statusFilter);
        }

        return $query->skip($skip)->take($limit)->get();
    }

    /**
     * Contar usuários da organização
     */
    public function getUserCount(): int
    {
        return $this->users()->count();
    }

    /**
     * Verifica se pode ser deletada (não tem usuários)
     */
    public function canDelete(): bool
    {
        return $this->getUserCount() === 0;
    }

    /**
     * Ativar organização
     */
    public function activate(): self
    {
        $this->org_status_id = self::STATUS_ACTIVE;
        $this->save();
        return $this;
    }

    /**
     * Desativar organização
     */
    public function deactivate(): self
    {
        $this->org_status_id = self::STATUS_INACTIVE;
        $this->save();
        return $this;
    }

    /**
     * Colocar organização em modo sandbox
     */
    public function setSandbox(): self
    {
        $this->org_status_id = self::STATUS_SANDBOX;
        $this->save();
        return $this;
    }
}