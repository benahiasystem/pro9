<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - width: numeric(8,2)
 * - height: numeric(8,2)
 * - establishment_id: bigint
 * - name: varchar(255)
 * - is_default: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tag_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->bigInteger('establishment_id');
            $table->string('name', 255)->collation('sab_ci_ai');
            $table->boolean('is_default')->default(false);
            $table->index(['establishment_id'], 'tag_templates_establishment_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_templates');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
