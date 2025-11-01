<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Keycloak integration fields
            $table->string('id_user_keycloak')->nullable()->index()->after('email');
            $table->string('id_tenant_keycloak')->nullable()->index();
            
            // User status and roles
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('is_superuser')->default(false)->after('is_active');
            $table->boolean('is_org_superuser')->default(false)->after('is_superuser');
            
            // Organization
            $table->string('organization_id')->nullable()->index()->after('is_org_superuser');
            
            // User profile fields
            $table->string('nm_full_name')->nullable()->after('organization_id');
            $table->string('nm_telefone_pais', 5)->nullable();
            $table->string('nm_telefone_ddd', 3)->nullable();
            $table->string('nm_telefone_numero', 15)->nullable();
            $table->string('nu_cpf', 14)->nullable()->index();
            
            // Make password nullable for Keycloak users
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'id_user_keycloak',
                'id_tenant_keycloak',
                'is_active',
                'is_superuser',
                'is_org_superuser',
                'organization_id',
                'nm_full_name',
                'nm_telefone_pais',
                'nm_telefone_ddd',
                'nm_telefone_numero',
                'nu_cpf',
            ]);
            
            // Restore password to not nullable
            $table->string('password')->nullable(false)->change();
        });
    }
};
