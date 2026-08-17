<?php

use App\Support\Venezuela\ExistingTenantMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ######## INICIO PUENTE TERRITORIAL Y DOCUMENTAL VENEZUELA ########
    public function up(): void
    {
        (new ExistingTenantMigrator())->migrate(DB::connection());
    }

    public function down(): void
    {
        // Irreversible: no se reintroducen catálogos peruanos ni códigos obsoletos.
    }
    // ######## FIN PUENTE TERRITORIAL Y DOCUMENTAL VENEZUELA ########
};
