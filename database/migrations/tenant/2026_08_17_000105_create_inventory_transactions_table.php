<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: varchar(255)
 * - name: varchar(255)
 * - type: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->string('id', 255)->collation('pro9_ci_ai');
            $table->string('name', 255)->collation('pro9_ci_ai');
            $table->string('type', 255)->collation('pro9_ci_ai');
            $table->index(['id'], 'inventory_transactions_id_index');
            $table->unique(['id'], 'inventory_transactions_id_pg_unique');
        });
        DB::statement('ALTER TABLE "inventory_transactions" ADD CONSTRAINT "inventory_transactions_type_check" CHECK ("type" IN (\'input\',\'output\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
