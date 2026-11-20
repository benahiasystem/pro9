<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - sale_note_id: bigint
 * - date_dispatch: date
 * - time_dispatch: time
 * - person_pick: varchar(255)
 * - reference: varchar(255)
 * - person_dispatch: varchar(255)
 * - status: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_sale_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('sale_note_id');
            $table->date('date_dispatch')->nullable();
            $table->time('time_dispatch')->nullable();
            $table->string('person_pick', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('reference', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('person_dispatch', 255)->collation('pro9_ci_ai')->nullable();
            $table->boolean('status')->nullable();
            $table->index(['sale_note_id'], 'dispatch_sale_notes_sale_note_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_sale_notes');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
