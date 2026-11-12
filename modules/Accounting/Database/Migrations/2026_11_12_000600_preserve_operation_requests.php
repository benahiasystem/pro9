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
        Schema::table('accounting_operation_results', function (Blueprint $table) {
            $table->string('state', 16)->default('pending');
            $table->json('request_payload')->nullable();
            $table->string('cancellation_reason', 2000)->nullable();
        });
        DB::table('accounting_operation_results')->whereNotNull('result')->update(['state' => 'confirmed']);
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER accounting_operation_result_upd BEFORE UPDATE ON accounting_operation_results FOR EACH ROW BEGIN IF OLD.state IN ('confirmed', 'cancelled') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirmacion o cancelacion inmutable'; END IF; END");
            DB::unprepared("CREATE TRIGGER accounting_operation_result_del BEFORE DELETE ON accounting_operation_results FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidad de operacion conservada'");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Las confirmaciones y cancelaciones conservan su identidad; no se eliminan para volver a permitir envíos anteriores.');
    }
};
