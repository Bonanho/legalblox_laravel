<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get current user
     * GET /api/v1/users/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // TODO: Adicionar lógica de organization_type_info similar ao FastAPI
        // if ($org_type_info) {
        //     $user->organization_type = $org_type_info['name'];
        // }
        
        return response()->json($user);
    }

    /**
     * Get current user profile
     * GET /api/v1/users/me/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // TODO: Buscar dados da organização
        // $org = Organization::where('organization_id', $user->organization_id)->first();
        
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            // 'organization_id' => $org->organization_id ?? null,
            // 'organization_name' => $org->ds_nome_razao_social ?? null,
            'display_name' => $user->name ?? null,
            'nm_full_name' => $user->name ?? null,
            // Adicionar outros campos conforme necessário
        ]);
    }

    /**
     * Update current user profile
     * PUT /api/v1/users/me/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'display_name' => 'sometimes|string|max:255',
            'nm_full_name' => 'sometimes|string|max:255',
            'nm_telefone_pais' => 'sometimes|string|max:10',
            'nm_telefone_ddd' => 'sometimes|string|max:10',
            'nm_telefone_numero' => 'sometimes|string|max:20',
            'nu_cpf' => 'sometimes|string|max:14',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user->updateProfile($request->only([
            'nm_full_name', 
            'nm_telefone_pais', 'nm_telefone_ddd', 'nm_telefone_numero', 'nu_cpf'
        ]));

        return response()->json(['message' => 'Perfil atualizado com sucesso', 'user' => $user], 200);
    }

    /**
     * Reset user password
     * POST /api/v1/users/me/profile/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Senha atual incorreta'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Senha atualizada com sucesso'], 200);
    }

    /**
     * Get user's reported issues
     * GET /api/v1/users/me/reported-issues
     */
    public function getReportedIssues(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // TODO: Implementar busca de reported issues
        // $issues = ReportedIssue::where('user_id', $user->id)
        //     ->skip($request->input('skip', 0))
        //     ->take($request->input('limit', 100))
        //     ->get();
        
        return response()->json([]);
    }

    /**
     * Get list of users
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        $skip = $request->input('skip', 0);
        $limit = $request->input('limit', 100);
        $organizationId = $request->input('organizationId');
        $search = $request->input('search');
        $activeOnly = $request->input('active_only', true);

        // Determina a organização que o usuário pode visualizar
        $targetOrganizationId = $organizationId;
        
        if ($currentUser->is_superuser) {
            // Superusers podem ver qualquer organização ou todas
            $targetOrganizationId = $organizationId;
        } elseif ($currentUser->is_org_superuser) {
            // Org superusers só podem ver sua própria organização
            $targetOrganizationId = $currentUser->organization_id;
            
            // Bloqueia tentativa de acessar outra organização
            if ($organizationId && $organizationId !== $currentUser->organization_id) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Não autorizado a visualizar usuários da organização especificada'
                ], 403);
            }
        } else {
            // Usuários regulares podem apenas ver sua própria organização
            if (!$currentUser->organization_id) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Usuário não possui organização associada'
                ], 403);
            }
            
            $targetOrganizationId = $currentUser->organization_id;
            
            if ($organizationId && $organizationId !== $currentUser->organization_id) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Não autorizado a visualizar usuários da organização especificada'
                ], 403);
            }
        }
        
        $query = User::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        // Filtra por organização se não for superuser
        if ($targetOrganizationId) {
            $query->where('organization_id', $targetOrganizationId);
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $users = $query->skip($skip)->take($limit)->get();

        return response()->json($users);
    }

    /**
     * Get user by ID
     * GET /api/v1/users/{user_id}
     */
    public function show(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões de acesso
        if (!$currentUser->canViewUsersFromOrg($user->organization_id)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão para acessar este usuário'
            ], 403);
        }

        return response()->json($user);
    }

    /**
     * Create new user
     * POST /api/v1/users
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        // Verifica permissões básicas
        if (!$currentUser->canManageUsers()) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão para criar usuários'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'organization_id' => 'nullable|string',
            'is_superuser' => 'nullable|boolean',
            'is_org_superuser' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Determina a organização do novo usuário
        $newUserOrganizationId = $request->organization_id;
        
        if ($currentUser->is_org_superuser) {
            // Org superusers só podem criar usuários na própria organização
            $newUserOrganizationId = $currentUser->organization_id;
            
            // Bloqueia tentativa de criar superuser global
            if ($request->is_superuser) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Org superusers não podem criar global superusers'
                ], 403);
            }
        } else {
            // Superusers não podem criar outros superusers (segurança adicional)
            if ($request->is_superuser && $currentUser->is_superuser) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Criação de global superuser requer autorização especial'
                ], 403);
            }
        }

        // TODO: Gerar senha temporária e enviar email
        $user = User::createNew([
            'email' => $request->email,
            'name' => $request->name,
            'password' => $request->password,
            'organization_id' => $newUserOrganizationId ?? $currentUser->organization_id,
            'is_superuser' => $request->is_superuser ?? false,
            'is_org_superuser' => $request->is_org_superuser ?? false,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Update user
     * PUT /api/v1/users/{user_id}
     */
    public function update(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões básicas para modificar usuário
        if (!$currentUser->canModifyUser($user)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão suficiente para modificar este usuário'
            ], 403);
        }

        // Verificações específicas para org superusers
        if (!$currentUser->is_superuser) {
            // Org superusers não podem modificar status de superuser
            if ($request->has('is_superuser')) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Org superusers não podem modificar status de global superuser'
                ], 403);
            }
            
            // Org superusers não podem transferir usuários para outras organizações
            if ($request->has('organization_id') && 
                $request->organization_id !== $user->organization_id) {
                return response()->json([
                    'error' => 'Sem permissão',
                    'detail' => 'Org superusers não podem transferir usuários para outras organizações'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'name' => 'sometimes|string|max:255',
            'organization_id' => 'nullable|string',
            'is_superuser' => 'nullable|boolean',
            'is_org_superuser' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user->updateAdminData($request->only(['email', 'name', 'organization_id', 'is_superuser', 'is_org_superuser', 'is_active']));

        return response()->json($user);
    }

    /**
     * Delete user
     * DELETE /api/v1/users/{user_id}
     */
    public function destroy(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões - apenas superusers globais podem deletar
        if (!$currentUser->canDeleteUser($user)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Apenas superusuários globais podem deletar usuários'
            ], 403);
        }

        // Prevenir auto-deleção
        if ($user->id == $currentUser->id) {
            return response()->json([
                'error' => 'Não é possível deletar sua própria conta'
            ], 400);
        }

        $user->delete();

        return response()->json(['message' => 'Usuário deletado com sucesso']);
    }

    /**
     * Generate password for user
     * POST /api/v1/users/{user_id}/generate-password
     */
    public function generatePassword(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões
        if (!$currentUser->canModifyUser($user)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão para resetar a senha deste usuário'
            ], 403);
        }

        // TODO: Gerar senha temporária e enviar email

        return response()->json(['message' => 'Senha gerada e email enviado com sucesso']);
    }

    /**
     * Deactivate user
     * POST /api/v1/users/{user_id}/deactivate
     */
    public function deactivate(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões
        if (!$currentUser->canModifyUser($user)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão para desativar este usuário'
            ], 403);
        }

        // Prevenir auto-desativação
        if ($user->id == $currentUser->id) {
            return response()->json([
                'error' => 'Não é possível desativar sua própria conta'
            ], 400);
        }

        $user->setActive(false);

        return response()->json($user);
    }

    /**
     * Activate user
     * POST /api/v1/users/{user_id}/activate
     */
    public function activate(Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        // Verifica permissões
        if (!$currentUser->canModifyUser($user)) {
            return response()->json([
                'error' => 'Sem permissão',
                'detail' => 'Sem permissão para ativar este usuário'
            ], 403);
        }

        $user->setActive(true);

        return response()->json($user);
    }
}
