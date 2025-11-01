<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrganizationController;

// Todas as rotas estarão sob /api/v1/ (apiPrefix configurado no bootstrap/app.php)
Route::prefix('users')->middleware('keycloak.auth')->group(function () 
{
    // Rotas de autenticação para o usuário logado
    Route::prefix('me')->group(function () {
        Route::get('/', [UserController::class, 'me']); // GET /api/v1/users/me
        Route::get('/profile', [UserController::class, 'getProfile']); // GET /api/v1/users/me/profile
        Route::put('/profile', [UserController::class, 'updateProfile']); // PUT /api/v1/users/me/profile
        Route::post('/profile/reset-password', [UserController::class, 'resetPassword']); // POST /api/v1/users/me/profile/reset-password
        Route::get('/reported-issues', [UserController::class, 'getReportedIssues']); // GET /api/v1/users/me/reported-issues
    });
    
    // Rotas CRUD de usuários
    Route::get('/', [UserController::class, 'index']); // GET /api/v1/users
    Route::post('/', [UserController::class, 'store']); // POST /api/v1/users
    Route::get('/{user_id}', [UserController::class, 'show']); // GET /api/v1/users/{user_id}
    Route::put('/{user_id}', [UserController::class, 'update']); // PUT /api/v1/users/{user_id}
    Route::delete('/{user_id}', [UserController::class, 'destroy']); // DELETE /api/v1/users/{user_id}
    
    // Rotas de gerenciamento de usuários
    Route::post('/{user_id}/generate-password', [UserController::class, 'generatePassword']); // POST /api/v1/users/{user_id}/generate-password
    Route::post('/{user_id}/deactivate', [UserController::class, 'deactivate']); // POST /api/v1/users/{user_id}/deactivate
    Route::post('/{user_id}/activate', [UserController::class, 'activate']); // POST /api/v1/users/{user_id}/activate
});

// Rotas de organizações
Route::prefix('organizations')->middleware('keycloak.auth')->group(function () {
    // Rotas CRUD de organizações
    Route::get('/', [OrganizationController::class, 'index']); // GET /api/v1/organizations
    Route::get('/current', [OrganizationController::class, 'current']); // GET /api/v1/organizations/current
    Route::get('/summary/list', [OrganizationController::class, 'summaryList']); // GET /api/v1/organizations/summary/list
    Route::post('/', [OrganizationController::class, 'store']); // POST /api/v1/organizations
    Route::get('/{organization_id}', [OrganizationController::class, 'show']); // GET /api/v1/organizations/{organization_id}
    Route::put('/{organization_id}', [OrganizationController::class, 'update']); // PUT /api/v1/organizations/{organization_id}
    Route::delete('/{organization_id}', [OrganizationController::class, 'destroy']); // DELETE /api/v1/organizations/{organization_id}
    
    // Rotas de gerenciamento de organizações
    Route::get('/{organization_id}/users', [OrganizationController::class, 'users']); // GET /api/v1/organizations/{organization_id}/users
    Route::post('/{organization_id}/activate', [OrganizationController::class, 'activate']); // POST /api/v1/organizations/{organization_id}/activate
    Route::post('/{organization_id}/deactivate', [OrganizationController::class, 'deactivate']); // POST /api/v1/organizations/{organization_id}/deactivate
    Route::post('/{organization_id}/sandbox', [OrganizationController::class, 'sandbox']); // POST /api/v1/organizations/{organization_id}/sandbox
});