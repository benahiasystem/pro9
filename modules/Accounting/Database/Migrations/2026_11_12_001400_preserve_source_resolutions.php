<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

use Modules\Accounting\Support\PostgresIntegrity as Integrity;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('tenant')->table('accounting_incidents', function (Blueprint $t) { $t->unsignedInteger('version')->default(1); $t->dateTime('resolved_at')->nullable(); });
        Schema::connection('tenant')->create('accounting_source_resolutions', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('organization_id'); $t->uuid('company_id'); $t->uuid('incident_id'); $t->uuid('source_document_id'); $t->uuid('source_version_id');
            $t->string('action', 40); $t->text('reason'); $t->unsignedInteger('base_revision'); $t->unsignedInteger('reviewed_by'); $t->string('reviewed_by_name');
            $t->json('evidence'); $t->string('integrity_hash', 64); $t->timestamps();
            $t->unique(['organization_id', 'company_id', 'id'], 'uq_source_resolution_owner');
            $t->unique(['company_id', 'incident_id'], 'uq_source_resolution_incident');
            foreach (['incident_id' => 'accounting_incidents', 'source_document_id' => 'accounting_source_documents', 'source_version_id' => 'accounting_source_versions'] as $column => $table) {
                $t->foreign(['organization_id', 'company_id', $column], 'fk_source_resolution_' . $column)->references(['organization_id', 'company_id', 'id'])->on($table)->onDelete('restrict');
            }
            $t->foreign(['organization_id', 'company_id'], 'fk_source_resolution_company')->references(['organization_id', 'id'])->on('accounting_companies')->onDelete('restrict');
        });
        foreach (['UPDATE', 'DELETE'] as $event) { Integrity::reject('accounting_source_resolutions', 'accounting_source_resolutions_immutable_'.strtolower($event), $event, 'Resolución de origen inmutable'); }
    }

    public function down(): void
    {
        if (DB::connection('tenant')->table('accounting_source_resolutions')->exists()) { throw new RuntimeException('La reversión no puede eliminar resoluciones de origen conservadas.'); }
        foreach (['update', 'delete'] as $event) { Integrity::drop('accounting_source_resolutions', 'accounting_source_resolutions_immutable_'.$event); }
        Schema::connection('tenant')->dropIfExists('accounting_source_resolutions');
        Schema::connection('tenant')->table('accounting_incidents', fn(Blueprint $t) => $t->dropColumn(['version', 'resolved_at']));
    }
};
