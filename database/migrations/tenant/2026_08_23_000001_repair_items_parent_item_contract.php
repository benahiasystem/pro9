<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// ########### INICIO CONTRATO FLUJO DE PRODUCTOS ###########
return new class extends Migration
{
    private const TABLE = 'items';
    private const COLUMN = 'parent_item_id';
    private const INDEX = 'items_parent_item_id_index';
    private const FOREIGN = 'items_parent_item_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new RuntimeException('No se puede reparar el flujo de productos porque la tabla items no existe.');
        }

        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unsignedInteger(self::COLUMN)->nullable()->after('is_set');
            });
        }

        if (! $this->indexExists()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(self::COLUMN, self::INDEX);
            });
        }

        if (! $this->foreignExists()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign(self::COLUMN, self::FOREIGN)
                    ->references('id')
                    ->on(self::TABLE)
                    ->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if ($this->foreignExists()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropForeign(self::FOREIGN);
            });
        }

        if ($this->indexExists()) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });
    }

    private function indexExists(): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('INDEX_NAME', self::INDEX)
            ->exists();
    }

    private function foreignExists(): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', self::TABLE)
            ->where('CONSTRAINT_NAME', self::FOREIGN)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
// ########### FIN CONTRATO FLUJO DE PRODUCTOS ###########
