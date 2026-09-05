<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('module_levels')) {
            DB::table('module_levels')
                ->whereIn('value', ['dispatch_carrier', 'carrier_dispatches'])
                ->delete();
        }
    }

    public function down(): void
    {
    }
};
