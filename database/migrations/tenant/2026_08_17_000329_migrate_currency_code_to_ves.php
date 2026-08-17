<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ######## INICIO MIGRACIÓN MONEDA VENEZUELA ########
    private const NATIONAL_CURRENCY_ID = 'VES';

    private const SECONDARY_CURRENCY_ID = 'USD';

    private const LEGACY_CURRENCY_IDS = ['PEN', 'VED'];

    private const REFERENCE_COLUMNS = [
        'currency_type_id',
        'currency_type_id_source',
        'currency_type_id_target',
    ];

    public function up(): void
    {
        $schemaName = DB::connection()->getDatabaseName();

        DB::table('cat_currency_types')->updateOrInsert(
            ['id' => self::NATIONAL_CURRENCY_ID],
            [
                'active' => true,
                'symbol' => 'Bs.',
                'description' => 'Bolívares',
            ]
        );

        DB::table('cat_currency_types')->updateOrInsert(
            ['id' => self::SECONDARY_CURRENCY_ID],
            [
                'active' => true,
                'symbol' => '$',
                'description' => 'Dólares Americanos',
            ]
        );

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $columns = DB::table('information_schema.COLUMNS')
                ->select('TABLE_NAME', 'COLUMN_NAME')
                ->where('TABLE_SCHEMA', $schemaName)
                ->whereIn('COLUMN_NAME', self::REFERENCE_COLUMNS)
                ->get();

            foreach ($columns as $column) {
                if ($column->TABLE_NAME === 'cat_currency_types') {
                    continue;
                }

                DB::table($column->TABLE_NAME)
                    ->whereIn($column->COLUMN_NAME, self::LEGACY_CURRENCY_IDS)
                    ->update([
                        $column->COLUMN_NAME => self::NATIONAL_CURRENCY_ID,
                    ]);
            }

            DB::table('cat_currency_types')
                ->whereIn('id', self::LEGACY_CURRENCY_IDS)
                ->delete();

            $remainingReferences = $columns
                ->reject(static fn ($column): bool => $column->TABLE_NAME === 'cat_currency_types')
                ->sum(static function ($column): int {
                    return DB::table($column->TABLE_NAME)
                        ->whereIn($column->COLUMN_NAME, self::LEGACY_CURRENCY_IDS)
                        ->count();
                });

            if ($remainingReferences !== 0) {
                throw new RuntimeException(
                    'Persisten referencias monetarias obsoletas después de migrar a VES.'
                );
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(): void
    {
        // El cambio de código no altera importes y no debe reintroducir códigos obsoletos.
    }
    // ######## FIN MIGRACIÓN MONEDA VENEZUELA ########
};
