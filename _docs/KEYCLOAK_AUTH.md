# Middleware de Autenticação Keycloak para Laravel

Este documento descreve a implementação de autenticação Keycloak no Laravel, baseada na arquitetura do backend Python (FastAPI).

## Visão Geral

A implementação replica a funcionalidade do backend Python (`app/api/deps.py`) para autenticação via Keycloak usando tokens JWT, com as seguintes características:

- ✅ Validação de token JWT via introspection endpoint do Keycloak
- ✅ Just-In-Time (JIT) Provisioning de usuários
- ✅ Sincronização automática de roles e organização
- ✅ Métodos de verificação de permissões no Model User (superuser, org_superuser, etc.)

## Componentes Criados

### 1. Middleware de Autenticação Principal

#### `KeycloakAuthMiddleware` (`app/Http/Middleware/KeycloakAuthMiddleware.php`)

Middleware obrigatório que valida tokens JWT do Keycloak.

**Funcionalidades:**
- Extrai token do header `Authorization: Bearer {token}`
- Valida token via endpoint de introspection do Keycloak
- Extrai informações do usuário (email, roles, groups, organização)
- Realiza JIT provisioning se o usuário não existe localmente
- Sincroniza roles e organização do Keycloak com banco local
- Verifica se usuário está ativo

**Uso:**
```php
Route::middleware('keycloak.auth')->group(function () {
    Route::get('/users/me', [UserController::class, 'me']);
});
```

### 2. Métodos de Autorização no Model User

Ao invés de usar middlewares separados, as verificações de permissão são feitas usando métodos no modelo `User`:

#### Métodos Disponíveis

```php
// Verifica se pode gerenciar usuários (superuser ou org_superuser)
$user->canManageUsers(): bool

// Verifica se pode gerenciar usuários de uma organização específica
$user->canManageUsersFromOrg(?string $organizationId): bool

// Verifica se pode visualizar usuários de uma organização
$user->canViewUsersFromOrg(?string $organizationId): bool

// Verifica se pode modificar outro usuário
$user->canModifyUser(User $targetUser): bool

// Verifica se pode deletar outro usuário (apenas superuser)
$user->canDeleteUser(User $targetUser): bool

// Verifica se pode modificar status de superuser
$user->canModifySuperuserStatus(): bool

// Verifica se pode modificar organização de outro usuário
$user->canModifyUserOrganization(User $targetUser): bool

// Retorna a organização que o usuário pode gerenciar
$user->getManageableOrganizationId(): ?string
```

**Uso no Controller:**
```php
public function show(Request $request, $userId): JsonResponse
{
    $currentUser = Auth::user();
    $user = User::find($userId);
    
    if (!$currentUser->canViewUsersFromOrg($user->organization_id)) {
        return response()->json(['error' => 'Sem permissão'], 403);
    }
    
    return response()->json($user);
}
```

### 3. Modelo User Atualizado

O modelo `User` foi atualizado com os seguintes campos:

```php
// Keycloak
'id_user_keycloak',
'id_tenant_keycloak',

// Status e Roles
'is_active',
'is_superuser',
'is_org_superuser',

// Organização
'organization_id',

// Perfil
'nm_full_name',
'nm_telefone_pais',
'nm_telefone_ddd',
'nm_telefone_numero',
'nu_cpf',
```

**Migration:** `database/migrations/2025_11_01_072858_add_keycloak_fields_to_users_table.php`

### 4. Configuração

Arquivo `config/keycloak.php` criado com as seguintes variáveis:

```php
'server_url' => env('KEYCLOAK_SERVER_URL', 'http://localhost:8080'),
'internal_server_url' => env('KEYCLOAK_INTERNAL_SERVER_URL', ...),
'realm' => env('KEYCLOAK_REALM', 'master'),
'client_id' => env('KEYCLOAK_CLIENT_ID', 'legalblox-backend'),
'client_secret' => env('KEYCLOAK_CLIENT_SECRET', ''),
```

## Variáveis de Ambiente Necessárias

Adicione ao arquivo `.env`:

```env
KEYCLOAK_SERVER_URL=http://localhost:8080
KEYCLOAK_INTERNAL_SERVER_URL=http://keycloak:8080
KEYCLOAK_REALM=legalblox
KEYCLOAK_CLIENT_ID=legalblox-backend
KEYCLOAK_CLIENT_SECRET=your-client-secret
```

## Fluxo de Autenticação

### 1. Requisição com Token

```
Cliente → [Authorization: Bearer token] → KeycloakAuthMiddleware
                                        ↓
                                    Valida token
                                        ↓
                    ┌───────────────────┴───────────────────┐
                    ↓                                       ↓
            Token válido?                            Token inválido
                    ↓                                       ↓
            Extrai dados                              Retorna 401
                    ↓
            Busca/cria usuário (JIT)
                    ↓
            Sincroniza roles/org
                    ↓
            Usuário ativo?
                    ↓
            Define Auth::user()
                    ↓
            Continua requisição
```

### 2. JIT Provisioning

Quando um usuário autenticado no Keycloak não existe localmente:

1. Middleware valida token com Keycloak
2. Extrai: `email`, `sub` (Keycloak ID), `roles`, `groups`, `name`
3. Cria usuário no banco local com os dados do Keycloak
4. Define `is_superuser` baseado na role `platform_admin`
5. Define `is_org_superuser` baseado na role `org_admin`
6. Define `organization_id` baseado nos groups do Keycloak

### 3. Sincronização

A cada requisição, o middleware verifica se há mudanças em:
- `is_superuser` / `is_org_superuser`
- `organization_id`
- `id_user_keycloak`
- `name`

Se houver mudanças, atualiza automaticamente o banco local.

## Exemplos de Uso

### Rotas Protegidas

```php
// routes/api.php

// Aplicar a todas as rotas users
Route::prefix('users')->middleware('keycloak.auth')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/me', [UserController::class, 'me']);
});

// Apenas superusuários podem deletar
Route::prefix('users')->middleware(['keycloak.auth', 'ensure.superuser'])->group(function () {
    Route::delete('/{id}', [UserController::class, 'destroy']);
});

// Org superusuários podem gerenciar sua organização
Route::prefix('organization')->middleware(['keycloak.auth', 'ensure.org_superuser'])->group(function () {
    Route::get('/users', [OrgController::class, 'users']);
});
```

### Acessar Usuário Autenticado

No controller:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

public function me(Request $request) 
{
    $user = Auth::user();
    // ou
    $user = $request->user();
    
    return response()->json($user);
}
```

Verificar permissões:

```php
public function deleteUser(Request $request, $id)
{
    $currentUser = Auth::user();
    
    if (!$currentUser->is_superuser) {
        return response()->json(['error' => 'Sem permissão'], 403);
    }
    
    // ...
}
```

## Comparação com Backend Python

| Funcionalidade Python | Implementação Laravel | Status |
|----------------------|----------------------|--------|
| `get_current_user()` | `KeycloakAuthMiddleware` | ✅ |
| `get_current_user_optional()` | _Removido - implementar se necessário_ | ⏸️ |
| `get_current_superuser()` | `User::canManageUsers()` | ✅ |
| `get_current_org_superuser()` | `User::canManageUsersFromOrg()` | ✅ |
| `get_current_active_user()` | Já verificado no `KeycloakAuthMiddleware` | ✅ |
| JIT Provisioning | Implementado no middleware | ✅ |
| Sync de roles/org | Implementado no middleware | ✅ |
| Verificações inline | Métodos no modelo User | ✅ |

## Logs e Debugging

O middleware registra logs detalhados:

```
[DEBUG] Validating token with Keycloak...
[INFO] Token validated for user: user@example.com, org: org-123, roles: [platform_admin], is_superuser: true
[INFO] JIT Provisioning: Creating new user user@example.com with superuser=true
[INFO] User synchronized from Keycloak, fields: is_superuser, organization_id
```

Verifique os logs em `storage/logs/laravel.log`.

## Arquitetura de Autorização

### Design Simples e Coerente

A implementação segue o princípio **YAGNI** (You Aren't Gonna Need It):

1. **1 Middleware apenas**: `keycloak.auth`
   - Evita over-engineering
   - Facilita manutenção
   - Se precisar de endpoints públicos no futuro, o middleware opcional pode ser adicionado

2. **Regras de negócio no Model**: Métodos de autorização no `User`
   - Reusáveis
   - Testáveis
   - Centralizados

3. **Controllers clean**: Apenas chamam métodos do model
   - Sem lógica de negócio
   - Fácil de entender
   - Fácil de testar

### Vantagens desta Abordagem

- ✅ **Simples**: Menos arquivos, menos complexidade
- ✅ **Testável**: Métodos isolados são fáceis de testar
- ✅ **Reutilizável**: Mesmo método usado em vários controllers
- ✅ **Manutenível**: Lógica centralizada no model
- ✅ **Legível**: Código auto-documentado

## Próximos Passos (TODOs)

1. **Validação de Grupos:** Implementar lógica para validar grupos do Keycloak contra organizações ativas no banco
2. **Debug User Switching:** Implementar funcionalidade de alternar entre usuários para debugging (similar ao `ALLOW_MOCK_USER_SWITCHING` do Python)
3. **Caching:** Adicionar cache para tokens válidos para reduzir chamadas ao Keycloak
4. **Retry Logic:** Implementar retry automático em caso de falha temporária do Keycloak

## Dependências

- `guzzlehttp/guzzle` - Para fazer requisições HTTP ao Keycloak

Instalar com:
```bash
composer require guzzlehttp/guzzle
```

## Executar Migrations

```bash
php artisan migrate
```

Isso criará os campos necessários na tabela `users`.

