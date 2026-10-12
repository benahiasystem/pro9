<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** New tenants only. Immutable versions: id, company/person, effective_from, profile, actor, created_at. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'SQL'
CREATE TABLE `fiscal_profile_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `effective_from` date NOT NULL,
  `profile` json NOT NULL,
  `actor_id` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `profile_company_date` (`company_id`,`effective_from`),
  KEY `profile_person_date` (`person_id`,`effective_from`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function down(): void { DB::unprepared('DROP TABLE IF EXISTS `fiscal_profile_versions`'); }
};
