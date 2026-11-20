<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - filename: varchar(255)
 * - payment_id: bigint
 * - payment_type: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_files', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('filename', 255)->collation('pro9_ci_ai');
            $table->bigInteger('payment_id');
            $table->string('payment_type', 255)->collation('pro9_ci_ai');
            $table->index(['payment_id', 'payment_type'], 'payment_files_payment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_files');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
