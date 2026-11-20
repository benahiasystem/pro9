<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\{TenantSchema as Schema, TenantDB as DB};

use Modules\Accounting\Support\PostgresIntegrity as Integrity;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_report_jobs', function (Blueprint $t) {
            $t->string('view_token', 64)->nullable();
            $t->unsignedBigInteger('row_count')->nullable();
            $t->unsignedInteger('schema_version')->default(1);
        });
        Schema::create('accounting_report_artifacts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('organization_id');
            $t->uuid('company_id');
            $t->uuid('report_job_id');
            $t->string('format', 8);
            $t->string('path');
            $t->string('sha256', 64);
            $t->unsignedBigInteger('bytes');
            $t->string('renderer_revision', 64);
            $t->unsignedInteger('created_by');
            $t->timestamps();
            $t->unique(['report_job_id', 'format'], 'accounting_report_artifact_format');
            $t->unique(['organization_id', 'company_id', 'id'], 'accounting_report_artifact_owner');
            $t->foreign(['organization_id', 'company_id'])->references(['organization_id', 'id'])->on('accounting_companies')->restrictOnDelete();
            $t->foreign(['organization_id', 'company_id', 'report_job_id'], 'accounting_report_artifact_job')->references(['organization_id', 'company_id', 'id'])->on('accounting_report_jobs')->restrictOnDelete();
            $t->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
        Integrity::reject('accounting_report_jobs', 'accounting_report_job_upd', 'UPDATE', 'Informe congelado inmutable', "OLD.status='ready'");
        Integrity::reject('accounting_report_jobs', 'accounting_report_job_del', 'DELETE', 'Informe conservado');
        Integrity::reject('accounting_report_artifacts', 'accounting_report_artifact_upd', 'UPDATE', 'Descarga verificada inmutable');
        Integrity::reject('accounting_report_artifacts', 'accounting_report_artifact_del', 'DELETE', 'Descarga verificada conservada');
    }

    public function down(): void
    {
        throw new RuntimeException('Los informes y sus huellas se conservan para reproducir las versiones descargadas.');
    }
};
