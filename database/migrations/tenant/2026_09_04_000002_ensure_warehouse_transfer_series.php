<?php

use App\Services\SeriesCodeGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('establishments') || ! Schema::hasTable('series')) {
            return;
        }

        $generator = app(SeriesCodeGenerator::class);

        DB::table('establishments')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($establishment_id) use ($generator) {
                $generator->ensureWarehouseInternalSeries((int) $establishment_id);
            });
    }

    public function down(): void
    {
        // Las series pueden haber sido usadas por traslados; no se eliminan al revertir.
    }
};
