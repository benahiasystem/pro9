<?php

use App\Support\Venezuela\IdentityDocumentCatalogMigrator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        (new IdentityDocumentCatalogMigrator())->migrate(DB::connection());
    }

    public function down(): void
    {
        // El catálogo es una invariante venezolana y no se restaura a valores SUNAT.
    }
};
