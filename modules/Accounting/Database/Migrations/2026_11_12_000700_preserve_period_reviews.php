<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
use Modules\Accounting\Support\TenantDB as DB;

use Modules\Accounting\Support\PostgresIntegrity as Integrity;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_period_reviews', function (Blueprint $table) {
            $table->index(['company_id', 'period_id', 'created_at'], 'accounting_period_review_history');
        });
        foreach (['UPDATE' => 'upd', 'DELETE' => 'del'] as $event => $suffix) {
            Integrity::reject('accounting_period_reviews', 'accounting_period_review_'.$suffix, $event, 'Revision de periodo inmutable');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Las revisiones y versiones de informes de períodos se conservan como evidencia.');
    }
};
