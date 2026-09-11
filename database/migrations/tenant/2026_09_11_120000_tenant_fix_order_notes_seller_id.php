<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantFixOrderNotesSellerId extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('order_notes') || !Schema::hasColumn('order_notes', 'seller_id')) {
            return;
        }

        DB::statement("
            UPDATE order_notes
            INNER JOIN users ON users.id = order_notes.user_id
            SET order_notes.seller_id = order_notes.user_id
            WHERE users.type = 'seller'
              AND (order_notes.seller_id IS NULL OR order_notes.seller_id != order_notes.user_id)
        ");
    }

    public function down()
    {
        // No reversible: corrige datos históricos mal asignados.
    }
}
