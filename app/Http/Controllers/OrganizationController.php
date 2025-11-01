<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrganizationController extends Controller
{
    /**
     * Get list of organizations
     * GET /api/v1/organizations
     * Only global superusers can access this endpoint.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can manage organizations.'
            ], 403);
        }

        $skip = $request->input('skip', 0);
        $limit = $request->input('limit', 100);
        $search = $request->input('search');
        $statusFilter = $request->input('status_filter'); // 1=Active, 0=Inactive, 2=Sandbox

        if ($search) {
            $organizations = Organization::search($search, $statusFilter, $skip, $limit);
        } elseif ($statusFilter !== null) {
            $organizations = Organization::getByStatus([$statusFilter], $skip, $limit);
        } else {
            $organizations = Organization::skip($skip)->take($limit)->get();
        }

        return response()->json($organizations);
    }

    /**
     * Get current user's organization
     * GET /api/v1/organizations/current
     */
    public function current(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->organization_id) {
            return response()->json([
                'error' => 'O usuário não pertence a nenhuma organização'
            ], 404);
        }

        $organization = Organization::findByOrganizationId($currentUser->organization_id);
        
        if (!$organization) {
            return response()->json([
                'error' => 'Organização não encontrada'
            ], 404);
        }

        return response()->json($organization);
    }

    /**
     * Get simplified list of organizations for dropdowns
     * GET /api/v1/organizations/summary/list
     * Only global superusers can access this endpoint.
     */
    public function summaryList(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can list organizations.'
            ], 403);
        }

        $organizations = Organization::getByStatus([Organization::STATUS_ACTIVE, Organization::STATUS_INACTIVE, Organization::STATUS_SANDBOX], 0, 1000);
        
        // Format as summary
        $summary = $organizations->map(function($org) {
            return [
                'id' => $org->id,
                'organization_id' => $org->organization_id,
                'ds_nome_razao_social' => $org->ds_nome_razao_social,
                'ds_nome_fantasia' => $org->ds_nome_fantasia,
                'org_type_id' => $org->org_type_id,
                'org_status_id' => $org->org_status_id,
                'whatsapp_enabled' => $org->whatsapp_enabled ?? false,
            ];
        });

        return response()->json($summary);
    }

    /**
     * Get organization by ID
     * GET /api/v1/organizations/{organization_id}
     * Global superusers can access any organization,
     * org superusers can only access their own organization.
     */
    public function show(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();
        $organization = Organization::findByOrganizationId($organizationId);

        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        // Permission check
        if (!$currentUser->is_superuser) {
            if (!($currentUser->is_org_superuser && $currentUser->organization_id == $organizationId)) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Not enough permissions to access this organization.'
                ], 403);
            }
        }

        return response()->json($organization);
    }

    /**
     * Get users from organization
     * GET /api/v1/organizations/{organization_id}/users
     * Global superusers can access any organization,
     * org superusers can only access their own organization.
     */
    public function users(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();

        // Permission check
        if (!$currentUser->is_superuser) {
            if (!($currentUser->is_org_superuser && $currentUser->organization_id == $organizationId)) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Not enough permissions to access users from this organization.'
                ], 403);
            }
        }

        $skip = $request->input('skip', 0);
        $limit = $request->input('limit', 100);
        $activeOnly = $request->input('active_only', true);

        $query = User::where('organization_id', $organizationId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $users = $query->skip($skip)->take($limit)->get();

        // Format as UserSummary
        $userSummary = $users->map(function($user) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'is_active' => $user->is_active,
                'is_superuser' => $user->is_superuser,
                'is_org_superuser' => $user->is_org_superuser,
                'organization_id' => $user->organization_id,
            ];
        });

        return response()->json($userSummary);
    }

    /**
     * Create new organization
     * POST /api/v1/organizations
     * Only global superusers can create organizations.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can create organizations.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|string|max:255|unique:organizations,organization_id',
            'ds_nome_razao_social' => 'required|string|max:255',
            'ds_nome_fantasia' => 'nullable|string|max:255',
            'nu_cnpj' => 'nullable|string|max:18|unique:organizations,nu_cnpj',
            'nu_cnae_principal' => 'nullable|string|max:7',
            'ds_cnae_principal' => 'nullable|string',
            'ds_endereco_logradouro' => 'nullable|string|max:255',
            'ds_endereco_numero' => 'nullable|string|max:50',
            'ds_endereco_complemento' => 'nullable|string|max:255',
            'ds_bairro' => 'nullable|string|max:100',
            'ds_municipio_nome' => 'nullable|string|max:100',
            'cd_municipio_ibge' => 'nullable|string|max:7',
            'sg_uf' => 'nullable|string|max:2',
            'ds_cep' => 'nullable|string|max:9',
            'nm_contato_principal' => 'nullable|string|max:255',
            'nm_email_contato_principal' => 'nullable|email|max:255',
            'nu_telefone_contato_principal' => 'nullable|string|max:20',
            'org_type_id' => 'nullable|integer|in:' . Organization::TYPE_CORP . ',' . Organization::TYPE_RETAIL,
            'org_status_id' => 'nullable|integer|in:' . Organization::STATUS_INACTIVE . ',' . Organization::STATUS_ACTIVE . ',' . Organization::STATUS_SANDBOX,
            'config_json' => 'nullable|array',
            'ds_observacoes' => 'nullable|string',
            'whatsapp_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Validate CNPJ format if provided
        if ($request->has('nu_cnpj') && $request->nu_cnpj) {
            $cnpj = preg_replace('/\D/', '', $request->nu_cnpj);
            if (strlen($cnpj) !== 14) {
                return response()->json([
                    'error' => 'CNPJ must have exactly 14 digits'
                ], 400);
            }
        }

        // Validate CEP format if provided
        if ($request->has('ds_cep') && $request->ds_cep) {
            $cep = preg_replace('/\D/', '', $request->ds_cep);
            if (strlen($cep) !== 8) {
                return response()->json([
                    'error' => 'CEP must have exactly 8 digits'
                ], 400);
            }
        }

        // Format UF to uppercase
        $data = $request->all();
        if (isset($data['sg_uf'])) {
            $data['sg_uf'] = strtoupper($data['sg_uf']);
        }

        try {
            // TODO: Integrate with Keycloak to create group
            // For now, creating without Keycloak integration
            $organization = Organization::createNew($data);
            
            return response()->json($organization, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao criar organização',
                'detail' => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Update organization
     * PUT /api/v1/organizations/{organization_id}
     * Global superusers can update any organization,
     * org superusers can only update their own organization (and only CORP type).
     */
    public function update(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();
        $organization = Organization::findByOrganizationId($organizationId);

        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        // Permission check
        if (!$currentUser->is_superuser) {
            // Org superusers can only update CORP organizations and only their own
            $isCorp = $organization->org_type_id == Organization::TYPE_CORP;
            
            if (!($currentUser->is_org_superuser && $currentUser->organization_id == $organizationId && $isCorp)) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Not enough permissions to update this organization.'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'ds_nome_razao_social' => 'sometimes|string|max:255',
            'ds_nome_fantasia' => 'nullable|string|max:255',
            'nu_cnpj' => 'nullable|string|max:18|unique:organizations,nu_cnpj,' . $organization->id,
            'nu_cnae_principal' => 'nullable|string|max:7',
            'ds_cnae_principal' => 'nullable|string',
            'ds_endereco_logradouro' => 'nullable|string|max:255',
            'ds_endereco_numero' => 'nullable|string|max:50',
            'ds_endereco_complemento' => 'nullable|string|max:255',
            'ds_bairro' => 'nullable|string|max:100',
            'ds_municipio_nome' => 'nullable|string|max:100',
            'cd_municipio_ibge' => 'nullable|string|max:7',
            'sg_uf' => 'nullable|string|max:2',
            'ds_cep' => 'nullable|string|max:9',
            'nm_contato_principal' => 'nullable|string|max:255',
            'nm_email_contato_principal' => 'nullable|email|max:255',
            'nu_telefone_contato_principal' => 'nullable|string|max:20',
            'id_tenant_keycloak' => 'nullable|string|max:255',
            'org_type_id' => 'nullable|integer|in:1,2',
            'org_status_id' => 'nullable|integer|in:0,1,2',
            'config_json' => 'nullable|array',
            'ds_observacoes' => 'nullable|string',
            'whatsapp_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Validate CNPJ format if being updated
        if ($request->has('nu_cnpj') && $request->nu_cnpj && $request->nu_cnpj !== $organization->nu_cnpj) {
            $cnpj = preg_replace('/\D/', '', $request->nu_cnpj);
            if (strlen($cnpj) !== 14) {
                return response()->json([
                    'error' => 'CNPJ must have exactly 14 digits'
                ], 400);
            }
        }

        // Validate CEP format if being updated
        if ($request->has('ds_cep') && $request->ds_cep) {
            $cep = preg_replace('/\D/', '', $request->ds_cep);
            if (strlen($cep) !== 8) {
                return response()->json([
                    'error' => 'CEP must have exactly 8 digits'
                ], 400);
            }
        }

        // Format UF to uppercase
        $data = $request->only([
            'ds_nome_razao_social', 'ds_nome_fantasia', 'nu_cnpj',
            'nu_cnae_principal', 'ds_cnae_principal',
            'ds_endereco_logradouro', 'ds_endereco_numero', 'ds_endereco_complemento',
            'ds_bairro', 'ds_municipio_nome', 'cd_municipio_ibge', 'sg_uf', 'ds_cep',
            'nm_contato_principal', 'nm_email_contato_principal', 'nu_telefone_contato_principal',
            'id_tenant_keycloak', 'org_type_id', 'org_status_id',
            'config_json', 'ds_observacoes', 'whatsapp_enabled'
        ]);

        if (isset($data['sg_uf'])) {
            $data['sg_uf'] = strtoupper($data['sg_uf']);
        }

        try {
            $organization->updateOrganizationData($data);
            return response()->json($organization);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao atualizar organização',
                'detail' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete organization
     * DELETE /api/v1/organizations/{organization_id}
     * Only global superusers can delete organizations.
     * Organization must have no users before it can be deleted.
     */
    public function destroy(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can delete organizations.'
            ], 403);
        }

        $organization = Organization::findByOrganizationId($organizationId);
        
        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        // Check if organization has users
        $userCount = $organization->getUserCount();
        if ($userCount > 0) {
            return response()->json([
                'error' => 'Não é possível deletar',
                'detail' => "Cannot delete organization with {$userCount} users. Please remove or transfer all users first."
            ], 400);
        }

        // TODO: Integrate with Keycloak to delete group
        // For now, deleting without Keycloak integration
        $organization->delete();

        return response()->json(null, 204);
    }

    /**
     * Deactivate organization
     * POST /api/v1/organizations/{organization_id}/deactivate
     * Only global superusers can deactivate organizations.
     */
    public function deactivate(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can deactivate organizations.'
            ], 403);
        }

        // Check if organization has users
        $organization = Organization::findByOrganizationId($organizationId);
        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        $userCount = $organization->getUserCount();
        if ($userCount > 0) {
            return response()->json([
                'error' => 'Não é possível desativar',
                'detail' => "Cannot deactivate organization with {$userCount} active users. Please deactivate or transfer users first."
            ], 400);
        }

        $organization->deactivate();

        return response()->json($organization);
    }

    /**
     * Activate organization
     * POST /api/v1/organizations/{organization_id}/activate
     * Only global superusers can activate organizations.
     */
    public function activate(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can activate organizations.'
            ], 403);
        }

        $organization = Organization::findByOrganizationId($organizationId);
        
        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        $organization->activate();

        return response()->json($organization);
    }

    /**
     * Set organization to Sandbox mode
     * POST /api/v1/organizations/{organization_id}/sandbox
     * Only global superusers can set organizations to sandbox.
     */
    public function sandbox(Request $request, $organizationId): JsonResponse
    {
        $currentUser = Auth::user();

        if (!$currentUser->is_superuser) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Not enough permissions. Only global superusers can set organizations to sandbox mode.'
            ], 403);
        }

        $organization = Organization::findByOrganizationId($organizationId);
        
        if (!$organization) {
            return response()->json([
                'error' => 'Organization not found'
            ], 404);
        }

        $organization->setSandbox();

        return response()->json($organization);
    }
}