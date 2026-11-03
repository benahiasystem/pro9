<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One commission per actual recorded receipt; the financial movement is separate.
return new class extends Migration {
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE `payment_commissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_global_payment_id` int(10) unsigned NOT NULL,
  `currency_type_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_of_payment` date NOT NULL,
  `amount` decimal(30,12) NOT NULL,
  `calculation_snapshot` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_commissions_source_unique` (`source_global_payment_id`),
  KEY `payment_commissions_currency_fk` (`currency_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS `payment_commissions`');
    }
};
