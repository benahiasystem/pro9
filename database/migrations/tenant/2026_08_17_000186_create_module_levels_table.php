<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - value: varchar(255)
 * - description: varchar(255)
 * - module_id: bigint
 * - route_name: varchar(255)
 * - route_path: varchar(255)
 * - label_menu: varchar(3)
 * - icon_id: varchar(255)
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_levels', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('value', 255)->collation('pro9_ci_ai');
            $table->string('description', 255)->collation('pro9_ci_ai');
            $table->bigInteger('module_id');
            $table->string('route_name', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('route_path', 255)->collation('pro9_ci_ai')->nullable();
            $table->string('label_menu', 3)->collation('pro9_ci_ai')->nullable();
            $table->string('icon_id', 255)->collation('pro9_ci_ai')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['module_id'], 'module_levels_module_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_levels');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
