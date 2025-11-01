<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Serviço para integração com Keycloak
 * 
 * Responsável por todas as comunicações com o servidor Keycloak:
 * - Validação de tokens JWT via introspection
 * - Extração de dados de usuário dos tokens
 * - Processamento de roles e grupos
 */
class KeycloakService
{
    /**
     * Valida um token JWT com o Keycloak via introspection endpoint
     * 
     * @param string $token Token JWT a ser validado
     * @return array|null Informações do token ou null se inválido
     */
    public function validateToken(string $token): ?array
    {
        $config = config('keycloak');
        
        try {
            Log::debug('Validating token with Keycloak...', [
                'keycloak_url' => $config['server_url']
            ]);

            $response = Http::asForm()->post(
                $config['server_url'] . '/realms/' . $config['realm'] . '/protocol/openid-connect/token/introspect',
                [
                    'token' => $token,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                ]
            );

            if (!$response->successful()) {
                Log::warning('Keycloak introspection failed', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $tokenInfo = $response->json();

            // Verificar se o token está ativo
            if (!isset($tokenInfo['active']) || !$tokenInfo['active']) {
                Log::warning('Token validation failed: token is not active or has expired');
                return null;
            }

            return $tokenInfo;

        } catch (\Exception $e) {
            Log::error('Error validating token with Keycloak: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrai informações estruturadas do usuário a partir do token introspectado
     * 
     * @param array $tokenInfo Informações do token retornadas pelo Keycloak
     * @return array|null Dados estruturados do usuário ou null se inválido
     */
    public function extractUserDataFromToken(array $tokenInfo): ?array
    {
        $email = $tokenInfo['email'] ?? null;
        $keycloakUserId = $tokenInfo['sub'] ?? null;

        if (!$email || !$keycloakUserId) {
            Log::error('Token validation failed: missing email or user ID');
            return null;
        }

        // Extrair roles
        $realmAccess = $tokenInfo['realm_access'] ?? [];
        $roles = $realmAccess['roles'] ?? [];
        
        $isSuperuser = in_array('platform_admin', $roles);
        $isOrgSuperuser = in_array('org_admin', $roles);

        // Extrair organização dos grupos
        $organizationId = $this->extractOrganizationFromGroups($tokenInfo['groups'] ?? []);

        // Extrair nome do usuário
        $name = $tokenInfo['name'] ?? '';

        Log::info('Token validated for user', [
            'email' => $email,
            'organization_id' => $organizationId,
            'roles' => $roles,
            'is_superuser' => $isSuperuser,
            'is_org_superuser' => $isOrgSuperuser
        ]);

        return [
            'email' => $email,
            'keycloak_user_id' => $keycloakUserId,
            'name' => $name,
            'is_superuser' => $isSuperuser,
            'is_org_superuser' => $isOrgSuperuser,
            'organization_id' => $organizationId,
        ];
    }

    /**
     * Extrai o ID da organização dos grupos do Keycloak
     * 
     * @param array $groups Lista de grupos do Keycloak
     * @return string|null ID da organização ou null se não encontrado
     */
    public function extractOrganizationFromGroups(array $groups): ?string
    {
        if (empty($groups)) {
            return null;
        }

        // TODO: Implementar lógica para validar grupos contra organizações ativas
        // Por enquanto, retorna o primeiro grupo removendo barras iniciais
        $firstGroup = $groups[0] ?? null;
        
        if ($firstGroup) {
            // Remove barra inicial se presente
            return ltrim($firstGroup, '/');
        }

        return null;
    }

    /**
     * Obtém o nome do realm configurado
     * 
     * @return string Nome do realm
     */
    public function getRealm(): string
    {
        return config('keycloak.realm', 'master');
    }

    /**
     * Obtém a URL base do servidor Keycloak
     * 
     * @return string URL base do Keycloak
     */
    public function getServerUrl(): string
    {
        return config('keycloak.server_url', 'http://localhost:8080');
    }
}

