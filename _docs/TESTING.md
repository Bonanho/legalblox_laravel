# Guia de Testes - TDD Implementado

Este projeto agora possui uma suíte completa de testes seguindo os princípios de TDD (Test-Driven Development).

## Estrutura de Testes

### Testes Unitários (`tests/Unit/`)

#### 1. `Models/UserTest.php` - 24 testes
Testa todos os métodos de autorização e criação de usuários:
- ✅ Métodos de autorização (`canManageUsers`, `canModifyUser`, etc.)
- ✅ Criação via Keycloak (JIT Provisioning)
- ✅ Criação manual com senha
- ✅ Atualização de perfil e dados administrativos
- ✅ Gerenciamento de status ativo/inativo

#### 2. `Services/KeycloakServiceTest.php` - 19 testes
Testa a integração com Keycloak usando mocks:
- ✅ Validação de tokens JWT
- ✅ Extração de dados de usuário
- ✅ Identificação de roles (superuser, org_superuser)
- ✅ Extração de organização dos grupos
- ✅ Tratamento de erros e casos edge

### Testes de Feature (`tests/Feature/`)

#### 1. `Auth/KeycloakAuthTest.php` - 10 testes
Testa o middleware de autenticação completo:
- ✅ Rejeição de tokens inválidos/missing
- ✅ Autenticação com token válido
- ✅ JIT Provisioning automático
- ✅ Sincronização de dados do Keycloak
- ✅ Rejeição de usuários inativos
- ✅ Tratamento de erros do servidor Keycloak

#### 2. `Api/UserControllerTest.php` - 18 testes
Testa todos os endpoints do UserController:
- ✅ Listagem de usuários (com filtros de permissão)
- ✅ Visualização de usuários (com verificação de acesso)
- ✅ Criação de usuários (com validação de permissões)
- ✅ Atualização de usuários
- ✅ Exclusão de usuários (apenas superuser)
- ✅ Ativação/desativação de usuários
- ✅ Atualização de perfil próprio

**Total: 71 testes** cobrindo:
- ✅ Autenticação completa via Keycloak
- ✅ Autorização por roles e organizações
- ✅ CRUD de usuários com controle de acesso
- ✅ JIT Provisioning
- ✅ Sincronização automática

## Executar Testes

### Pré-requisitos

1. **Extensão SQLite do PHP** (para testes):
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3

# Ou alternativamente, configure MySQL/PostgreSQL no phpunit.xml
```

### Comandos

```bash
# Rodar todos os testes
php artisan test

# Rodar apenas testes unitários
php artisan test --testsuite=Unit

# Rodar apenas testes de feature
php artisan test --testsuite=Feature

# Rodar um arquivo específico
php artisan test tests/Unit/Models/UserTest.php

# Rodar um teste específico
php artisan test --filter superuser_can_manage_users

# Com cobertura (se disponível)
php artisan test --coverage
```

## Abordagem TDD

### Para Código Existente (Regressão)
- Testes foram criados para documentar comportamento atual
- Garantem que refatorações futuras não quebrem funcionalidades

### Para Novas Features (TDD Puro)
1. **Red**: Escrever teste que falha
2. **Green**: Implementar código mínimo para passar
3. **Refactor**: Melhorar mantendo testes verdes

## Padrões Usados

### Mocks para Integrações Externas
```php
// KeycloakService usa Http::fake() para mockar requisições
Http::fake([
    'keycloak.test:8080/*' => Http::response($tokenInfo, 200),
]);
```

### Factories para Dados de Teste
```php
// UserFactory com states
User::factory()->superuser()->create();
User::factory()->orgSuperuser()->create(['organization_id' => 'org-123']);
```

### RefreshDatabase
- Usado apenas em testes que precisam de banco de dados
- Testes unitários de serviços não usam RefreshDatabase

## Cobertura Esperada

- ✅ **Model User**: ~100% dos métodos de autorização
- ✅ **KeycloakService**: ~100% dos métodos públicos
- ✅ **KeycloakAuthMiddleware**: Fluxos principais cobertos
- ✅ **UserController**: Endpoints críticos cobertos

## Próximos Passos (TDD)

Quando implementar novas features, seguir TDD:

1. **Validação de Grupos Keycloak**
   - [ ] Criar `KeycloakOrganizationServiceTest`
   - [ ] Implementar validação de grupos contra organizações ativas

2. **Cache de Tokens**
   - [ ] Criar testes para cache de validação de tokens
   - [ ] Implementar cache no `KeycloakService`

3. **Retry Logic**
   - [ ] Criar testes para retry em falhas temporárias
   - [ ] Implementar retry com exponential backoff

## Troubleshooting

### Erro: "could not find driver (Connection: sqlite)"
**Solução**: Instale a extensão SQLite:
```bash
sudo apt-get install php-sqlite3
php -m | grep sqlite  # Verificar se está instalado
```

### Erro: "Table doesn't exist"
**Solução**: Execute migrations antes dos testes:
```bash
php artisan migrate --env=testing
```

### Testes lentos
**Solução**: Os testes usam SQLite in-memory, que é rápido. Se estiver lento, verifique:
- Configuração de cache
- Número de requisições HTTP mockadas

## Boas Práticas

1. ✅ Cada teste testa uma coisa específica
2. ✅ Nomes de testes são descritivos
3. ✅ Mocks são usados para dependências externas
4. ✅ Factories são usados para dados de teste
5. ✅ Testes são independentes (não dependem uns dos outros)
6. ✅ Setup e teardown são usados quando necessário

## Notas

- Os warnings sobre `@test` em doc-comments são normais no PHPUnit 11
- Podem ser removidos no futuro ou convertidos para atributos PHP 8.0+
- Não afetam a execução dos testes

