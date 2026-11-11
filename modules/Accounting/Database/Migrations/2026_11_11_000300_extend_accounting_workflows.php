<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
return new class extends Migration
{
    protected $connection = 'tenant';
    public function up(): void
    {
        Schema::table('accounting_memberships', function (Blueprint $t) {
            $t->json('roles')->nullable();
            $t->unsignedInteger('version')->default(1);
        });
        Schema::create('accounting_organization_memberships', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->unsignedInteger('user_id');
            $t->string('role', 24)->default('admin');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->foreign('organization_id')->references('id')->on('accounting_organizations')->restrictOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $t->unique(['organization_id', 'user_id'], 'accounting_org_user_unique');
        });
        Schema::table('accounting_auth_grants', fn(Blueprint $t) => $t->dateTime('session_until')->nullable());
        Schema::table('accounting_source_documents', function (Blueprint $t) {
            $t->unsignedInteger('created_by')->nullable();
            $t->unsignedInteger('validated_by')->nullable();
            $t->dateTime('validated_at')->nullable();
            $t->unsignedInteger('validated_version')->nullable();
            $t->date('received_on')->nullable();
            $t->string('natural_key', 64)->nullable();
            $t->text('exclusion_reason')->nullable();
            $t->text('rate_evidence_reference')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $t->foreign('validated_by')->references('id')->on('users')->restrictOnDelete();
            $t->unique(['company_id', 'natural_key'], 'accounting_document_natural_key');
        });
        Schema::table('accounting_rule_sets', fn(Blueprint $t) => $t->json('conditions')->nullable());
        Schema::table('accounting_tax_profiles', fn(Blueprint $t) => $t->unsignedInteger('version')->default(1));
    }
    public function down(): void
    {
        Schema::table('accounting_tax_profiles', fn(Blueprint $t) => $t->dropColumn('version'));
        Schema::table('accounting_rule_sets', fn(Blueprint $t) => $t->dropColumn('conditions'));
        Schema::table('accounting_source_documents', function (Blueprint $t) {
            $t->dropForeign(['created_by']);
            $t->dropForeign(['validated_by']);
            $t->dropUnique('accounting_document_natural_key');
            $t->dropColumn(['created_by', 'validated_by', 'validated_at', 'validated_version', 'received_on', 'natural_key', 'exclusion_reason', 'rate_evidence_reference']);
        });
        Schema::table('accounting_auth_grants', fn(Blueprint $t) => $t->dropColumn('session_until'));
        Schema::dropIfExists('accounting_organization_memberships');
        Schema::table('accounting_memberships', fn(Blueprint $t) => $t->dropColumn(['roles', 'version']));
    }
};
