<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\KeycloakService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keycloak JWT Authentication Middleware
 * 
 * Esta middleware valida tokens JWT emitidos pelo Keycloak e realiza
 * Just-In-Time (JIT) provisioning de usuários quando necessário.
 * 
 * Funcionalidades:
 * - Valida token JWT com o Keycloak via introspection endpoint
 * - Extrai informações do usuário (email, roles, groups)
 * - Sincroniza roles e organização do Keycloak com o banco local
 * - Realiza JIT provisioning para novos usuários
 * - Suporta modo debug para superusuários
 */
class KeycloakAuthMiddleware
{
    /**
     * Instância do serviço Keycloak
     */
    protected KeycloakService $keycloakService;

    /**
     * Construtor - injeta o KeycloakService
     */
    public function __construct(KeycloakService $keycloakService)
    {
        $this->keycloakService = $keycloakService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 1. Extrair token do header Authorization
            $token = $this->extractToken($request);
            
            if (!$token) {
                return response()->json([
                    'error' => 'Token não fornecido',
                    'detail' => 'Token de autenticação é obrigatório'
                ], 401);
            }

            // 2. Validar token com Keycloak via introspection
            $tokenInfo = $this->keycloakService->validateToken($token);
            
            if (!$tokenInfo) {
                return response()->json([
                    'error' => 'Token inválido',
                    'detail' => 'Token não é válido ou expirou'
                ], 401);
            }

            // 3. Extrair informações do usuário
            $userData = $this->keycloakService->extractUserDataFromToken($tokenInfo);
            
            if (!$userData) {
                return response()->json([
                    'error' => 'Informações do usuário inválidas',
                    'detail' => 'Token não contém informações de usuário válidas'
                ], 401);
            }

            // 4. Buscar ou criar usuário (JIT Provisioning)
            $user = $this->getOrCreateUser($userData);

            // 5. Verificar se usuário está ativo
            if (!$user->is_active) {
                return response()->json([
                    'error' => 'Usuário inativo',
                    'detail' => 'Conta de usuário está inativa'
                ], 401);
            }

            // 6. Definir usuário autenticado
            Auth::setUser($user);
            $request->setUserResolver(fn() => $user);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Keycloak authentication error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_path' => $request->path(),
                'request_method' => $request->method(),
                'has_token' => !empty($this->extractToken($request)),
            ]);

            return response()->json([
                'error' => 'Erro de autenticação',
                'detail' => 'Não foi possível validar as credenciais',
                'message' => config('app.debug') ? $e->getMessage() : null,
            ], 401);
        }
    }

    /**
     * Extrai o token JWT do header Authorization
     */
    private function extractToken(Request $request): ?string
    {
        $authorizationHeader = $request->header('Authorization');
        
        if (!$authorizationHeader) {
            return null;
        }

        // Suporta formato "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            return $matches[1];
        }

        // Suporta apenas o token diretamente
        return $authorizationHeader;
    }

    /**
     * Busca ou cria o usuário no banco local (JIT Provisioning)
     */
    private function getOrCreateUser(array $userData): User
    {
        // Buscar usuário existente
        $user = User::where('email', $userData['email'])->first();

        if (!$user) {
            // JIT Provisioning: Criar novo usuário
            Log::info('JIT Provisioning: Creating new user', [
                'email' => $userData['email'],
                'is_superuser' => $userData['is_superuser']
            ]);

            $user = User::createFromKeycloak($userData);

            Log::info('JIT Provisioning: Successfully created user', [
                'email' => $user->email,
                'is_superuser' => $user->is_superuser
            ]);
        } else {
            // Sincronizar informações do Keycloak
            $this->syncUserFromKeycloak($user, $userData);
        }

        return $user;
    }

    /**
     * Sincroniza informações do usuário com os dados do Keycloak
     */
    private function syncUserFromKeycloak(User $user, array $userData): void
    {
        $needsUpdate = false;
        $updateFields = [];

        if ($user->is_superuser !== $userData['is_superuser']) {
            $user->is_superuser = $userData['is_superuser'];
            $needsUpdate = true;
            $updateFields[] = 'is_superuser';
        }

        if ($user->is_org_superuser !== $userData['is_org_superuser']) {
            $user->is_org_superuser = $userData['is_org_superuser'];
            $needsUpdate = true;
            $updateFields[] = 'is_org_superuser';
        }

        if ($user->organization_id !== $userData['organization_id']) {
            $user->organization_id = $userData['organization_id'];
            $needsUpdate = true;
            $updateFields[] = 'organization_id';
        }

        if ($user->id_user_keycloak !== $userData['keycloak_user_id']) {
            $user->id_user_keycloak = $userData['keycloak_user_id'];
            $needsUpdate = true;
            $updateFields[] = 'id_user_keycloak';
        }

        if ($user->nm_full_name !== $userData['name'] && !empty($userData['name'])) {
            $user->nm_full_name = $userData['name'];
            $needsUpdate = true;
            $updateFields[] = 'nm_full_name';
        }

        if ($needsUpdate) {
            $user->save();
            
            Log::info('User synchronized from Keycloak', [
                'email' => $user->email,
                'fields' => implode(', ', $updateFields)
            ]);
        }

        Log::debug('Final user state', [
            'email' => $user->email,
            'is_superuser' => $user->is_superuser,
            'is_org_superuser' => $user->is_org_superuser,
            'organization_id' => $user->organization_id
        ]);
    }
}

