<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
return new class extends Migration
{
    public function up()
    {
        // En una instalación reconstruida esta migración precede a create_items_table.
        // El contrato definitivo se completa en la migración de reparación posterior.
        if (! Schema::hasTable('items') || Schema::hasColumn('items', 'parent_item_id')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            // items.id es increments (unsigned int), la FK debe coincidir
            $table->unsignedInteger('parent_item_id')->nullable()->index()->after('is_set');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'parent_item_id')) {
            return;
        }

        if ($this->foreignExists('items_parent_item_id_foreign')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropForeign('items_parent_item_id_foreign');
            });
        }

        Schema::table('items', function (Blueprint $table) {
            if ($this->indexExists('items_parent_item_id_index')) {
                $table->dropIndex('items_parent_item_id_index');
            }
            $table->dropColumn('parent_item_id');
        });
    }

    private function foreignExists(string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'items')
            ->where('CONSTRAINT_NAME', $name)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function indexExists(string $name): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'items')
            ->where('INDEX_NAME', $name)
            ->exists();
    }
};
// ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
