<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\{TenantSchema as Schema, TenantDB as DB};

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
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER accounting_report_job_upd BEFORE UPDATE ON accounting_report_jobs FOR EACH ROW BEGIN IF OLD.status='ready' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe congelado inmutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_report_job_del BEFORE DELETE ON accounting_report_jobs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe conservado'");
            DB::unprepared("CREATE TRIGGER accounting_report_artifact_upd BEFORE UPDATE ON accounting_report_artifacts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Descarga verificada inmutable'");
            DB::unprepared("CREATE TRIGGER accounting_report_artifact_del BEFORE DELETE ON accounting_report_artifacts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Descarga verificada conservada'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Los informes y sus huellas se conservan para reproducir las versiones descargadas.');
    }
};
