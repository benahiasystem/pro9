<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - fiscal_environment: varchar(16)
 * - date: date
 * - origin_date_of_issue: date
 * - origin_id: bigint
 * - origin_type: varchar(255)
 * - worker_full_name: varchar(255)
 * - total: numeric(30,12)
 * - created_at: timestamp
 * - updated_at: timestamp
 * - calculation_snapshot: json
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tips', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('fiscal_environment', 16)->collation('sab_ci_ai');
            $table->date('date')->comment('Fecha de registro');
            $table->date('origin_date_of_issue')->comment('Fecha del documento origen de la propina');
            $table->bigInteger('origin_id');
            $table->string('origin_type', 255)->collation('sab_ci_ai');
            $table->string('worker_full_name', 255)->collation('sab_ci_ai');
            $table->decimal('total', 30, 12);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->json('calculation_snapshot')->nullable()->comment('Exact decimal fractions v1');
            $table->index(['origin_id', 'origin_type'], 'tips_origin_index');
            $table->index(['fiscal_environment'], 'tips_fiscal_environment_foreign');
            $table->index(['date'], 'tips_date_index');
            $table->index(['origin_date_of_issue'], 'tips_origin_date_of_issue_index');
            $table->index(['worker_full_name'], 'tips_worker_full_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tips');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
