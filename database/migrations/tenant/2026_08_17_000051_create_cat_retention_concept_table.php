<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: char(3)
 * - description: varchar(512)
 * - percentage_label: varchar(160)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_retention_concept', function (Blueprint $table) {
            $table->char('id', 3)->collation('pro9_ci_ai');
            $table->string('description', 512)->collation('pro9_ci_ai');
            $table->string('percentage_label', 160)->collation('pro9_ci_ai');
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_retention_concept');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
