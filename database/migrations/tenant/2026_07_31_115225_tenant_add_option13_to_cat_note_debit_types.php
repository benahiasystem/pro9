<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('tenant')->table('cat_note_debit_types')->updateOrInsert(
            ['id' => '13'],
            [
                'active' => true,
                'description' => 'Penalidades',
            ]
        );

        // El motivo 03 deja de contemplar penalidades
        DB::connection('tenant')->table('cat_note_debit_types')
            ->where('id', '03')
            ->update(['description' => 'Otros conceptos']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('tenant')->table('cat_note_debit_types')->where('id', '13')->delete();

        DB::connection('tenant')->table('cat_note_debit_types')
            ->where('id', '03')
            ->update(['description' => 'Penalidades/ otros conceptos']);
    }
};
