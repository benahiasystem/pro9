<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_period_reviews', function (Blueprint $table) {
            $table->index(['company_id', 'period_id', 'created_at'], 'accounting_period_review_history');
        });
        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE' => 'upd', 'DELETE' => 'del'] as $event => $suffix) {
                DB::unprepared("CREATE TRIGGER accounting_period_review_" . $suffix . " BEFORE " . $event . " ON accounting_period_reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revision de periodo inmutable'");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Las revisiones y versiones de informes de períodos se conservan como evidencia.');
    }
};
