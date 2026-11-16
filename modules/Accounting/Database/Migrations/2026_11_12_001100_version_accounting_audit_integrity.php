<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;

return new class extends Migration {
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::table('accounting_audit_events', function (Blueprint $t) {
            // Existing evidence remains untouched. Its legacy byte serialization
            // was not preserved by MySQL's JSON storage.
            $t->unsignedTinyInteger('hash_version')->default(1);
            $t->string('actor_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounting_audit_events', fn(Blueprint $t) => $t->dropColumn(['hash_version', 'actor_name']));
    }
};
