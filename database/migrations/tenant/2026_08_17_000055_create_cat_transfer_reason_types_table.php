<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(2)
 * - active: boolean
 * - description: varchar(255)
 * - discount_stock: boolean
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cat_transfer_reason_types', function (Blueprint $table) {
            $table->string('id', 2)->collation('sab_ci_ai');
            $table->boolean('active');
            $table->string('description', 255)->collation('sab_ci_ai');
            $table->boolean('discount_stock')->nullable()->default(false);
            $table->primary(['id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_transfer_reason_types');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
