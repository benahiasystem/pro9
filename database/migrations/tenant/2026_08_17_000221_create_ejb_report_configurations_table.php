<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - document_type_id: varchar(255)
 * - bank_account_pen_id: bigint
 * - bank_account_usd_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejb_report_configurations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('document_type_id', 255)->collation('pro9_ci_ai');
            $table->bigInteger('bank_account_pen_id')->nullable();
            $table->bigInteger('bank_account_usd_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['document_type_id'], 'ejb_report_configurations_document_type_id_foreign');
            $table->index(['bank_account_pen_id'], 'ejb_report_configurations_bank_account_pen_id_foreign');
            $table->index(['bank_account_usd_id'], 'ejb_report_configurations_bank_account_usd_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejb_report_configurations');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
