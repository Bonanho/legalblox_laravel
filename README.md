# LegalBlox Laravel Backend

Backend Laravel para o sistema LegalBlox com autenticação via Keycloak.

## 🚀 Tecnologias

- **Laravel 12**
- **PHP 8.2+**
- **Keycloak** (Autenticação e autorização)
- **SQLite/MySQL** (Banco de dados)

## 📋 Requisitos

- PHP 8.2 ou superior
- Composer
- Keycloak configurado e rodando

## 🛠️ Instalação

```bash
# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env
php artisan key:generate

# Executar migrations
php artisan migrate

# (Opcional) Rodar testes
php artisan test
```

## 📚 Documentação

- **[Autenticação Keycloak](_docs/KEYCLOAK_AUTH.md)** - Guia completo de integração com Keycloak
- **[Guia de Testes](_docs/TESTING.md)** - Documentação sobre testes e TDD

## 🧪 Testes

```bash
# Rodar todos os testes
php artisan test

# Rodar apenas testes unitários
php artisan test --testsuite=Unit

# Rodar apenas testes de feature
php artisan test --testsuite=Feature

# Rodar um teste específico
php artisan test --filter test_validates_active_token_successfully
```

## 🔐 Autenticação

O sistema utiliza Keycloak para autenticação JWT. Consulte a [documentação completa](_docs/KEYCLOAK_AUTH.md) para configuração e uso.

## 📁 Estrutura do Projeto

```
legalblox_laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/     # Controllers da API
│   │   └── Middleware/      # Middlewares (KeycloakAuth)
│   ├── Models/              # Models Eloquent
│   └── Services/           # Serviços (KeycloakService)
├── _docs/                  # Documentação do projeto
├── routes/
│   └── api.php            # Rotas da API
└── tests/                  # Testes automatizados
```

## 🔧 Configuração

### Variáveis de Ambiente

Adicione ao `.env`:

```env
# Keycloak
KEYCLOAK_SERVER_URL=http://localhost:8080
KEYCLOAK_INTERNAL_SERVER_URL=http://keycloak:8080
KEYCLOAK_REALM=legalblox
KEYCLOAK_CLIENT_ID=legalblox-backend
KEYCLOAK_CLIENT_SECRET=your-client-secret
```

## 📝 API

Todas as rotas estão sob `/api/v1/` e requerem autenticação via Keycloak.

### Exemplo de Requisição

```bash
curl -X GET http://localhost:8000/api/v1/users/me \
  -H "Authorization: Bearer {token}"
```

## 🏗️ Desenvolvimento

```bash
# Rodar servidor de desenvolvimento
php artisan serve

# Ver logs
tail -f storage/logs/laravel.log
```

## 📄 Licença

Este projeto é proprietário.
