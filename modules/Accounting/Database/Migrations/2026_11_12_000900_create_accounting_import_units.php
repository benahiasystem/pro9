<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_import_jobs', function (Blueprint $t) {
            $t->string('filename', 180)->nullable();
            $t->json('options')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->string('parameters_hash', 64)->nullable();
            $t->string('data_hash', 64)->nullable();
            $t->string('preview_hash', 64)->nullable();
            $t->unique(['company_id', 'parameters_hash'], 'accounting_import_file_identity');
        });
        foreach (['accounting_import_units', 'accounting_import_receipts'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->uuid('id')->primary();
                $t->uuid('organization_id');
                $t->uuid('company_id');
                $t->uuid('import_job_id');
                $t->string('reference', 100);
                $t->string('reference_hash', 64);
                $t->string('content_hash', 64);
                if ($name === 'accounting_import_units') {
                    $t->unsignedInteger('position');
                    $t->string('status', 24);
                    $t->json('rows');
                    $t->json('errors');
                    $t->json('summary');
                    $t->json('resource')->nullable();
                    $t->unique(['import_job_id', 'reference_hash'], 'accounting_import_unit_reference');
                } else {
                    $t->string('kind', 30);
                    $t->json('resource');
                    $t->unsignedInteger('accepted_by');
                    $t->foreign('accepted_by')->references('id')->on('users')->restrictOnDelete();
                    $t->unique(['company_id', 'kind', 'reference_hash'], 'accounting_import_accepted_reference');
                }
                $t->timestamps();
                $suffix = $name === 'accounting_import_units' ? 'unit' : 'receipt';
                $t->unique(['organization_id', 'company_id', 'id'], 'accounting_import_' . $suffix . '_owner');
                $t->foreign(['organization_id', 'company_id'])->references(['organization_id', 'id'])->on('accounting_companies')->restrictOnDelete();
                $t->foreign(['organization_id', 'company_id', 'import_job_id'], 'accounting_import_' . $suffix . '_job')->references(['organization_id', 'company_id', 'id'])->on('accounting_import_jobs')->restrictOnDelete();
            });
        }
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER accounting_import_receipt_upd BEFORE UPDATE ON accounting_import_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Referencia de importacion aceptada inmutable'");
            DB::unprepared("CREATE TRIGGER accounting_import_receipt_del BEFORE DELETE ON accounting_import_receipts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Referencia de importacion aceptada conservada'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Conservar las referencias de importación impide duplicar registros al reintentar.');
    }
};
