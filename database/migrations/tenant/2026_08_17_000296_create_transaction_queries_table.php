<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - date: date
 * - time: time
 * - response: json
 * - transaction_id: bigint
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_queries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->time('time');
            $table->json('response');
            $table->bigInteger('transaction_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['transaction_id'], 'transaction_queries_transaction_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_queries');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
