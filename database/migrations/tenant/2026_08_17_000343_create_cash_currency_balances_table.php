<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ######## INICIO CLASIFICACION DE COBROS Y CAJA ########
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared("CREATE TABLE cash_currency_balances (cash_id int unsigned NOT NULL, currency_type_id char(3) COLLATE utf8mb4_unicode_ci NOT NULL,
            beginning_balance decimal(30,12) NOT NULL DEFAULT 0, income decimal(30,12) NOT NULL DEFAULT 0,
            egress decimal(30,12) NOT NULL DEFAULT 0, final_balance decimal(30,12) NOT NULL DEFAULT 0,
            calculation_snapshot json NOT NULL, PRIMARY KEY (cash_id,currency_type_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS cash_currency_balances'); }
};
// ######## FIN CLASIFICACION DE COBROS Y CAJA ########
