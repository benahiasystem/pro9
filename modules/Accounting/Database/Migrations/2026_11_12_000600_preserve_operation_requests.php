<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;

use Modules\Accounting\Support\PostgresIntegrity as Integrity;

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
        Integrity::reject('accounting_operation_results', 'accounting_operation_result_upd', 'UPDATE', 'Confirmacion o cancelacion inmutable', "OLD.state IN ('confirmed', 'cancelled')");
        Integrity::reject('accounting_operation_results', 'accounting_operation_result_del', 'DELETE', 'Identidad de operacion conservada');
    }

    public function down(): void
    {
        throw new RuntimeException('Las confirmaciones y cancelaciones conservan su identidad; no se eliminan para volver a permitir envíos anteriores.');
    }
};
