<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
return new class extends Migration
{
    protected $connection = 'tenant';
    public function up(): void
    {
        Schema::table('accounting_journal_lines', fn(Blueprint $t) => $t->decimal('original_amount', 32, 12)->nullable()->change());
        Schema::table('accounting_open_items', function (Blueprint $t) {
            $t->decimal('original_amount', 32, 12)->change();
            $t->decimal('remaining_original', 32, 12)->change();
        });
        Schema::table('accounting_settlement_allocations', fn(Blueprint $t) => $t->decimal('original_amount', 32, 12)->change());
    }
    public function down(): void
    {
        throw new \RuntimeException('La precisión original se conserva; una reducción requiere revisar los importes existentes.');
    }
};
