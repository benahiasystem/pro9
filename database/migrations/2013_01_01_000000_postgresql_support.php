<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
return new class extends Migration {
    public function up(): void { \App\Database\PostgresBootstrap::install(DB::connection()); }
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.pro9_touch_updated_at()');
        DB::statement('DROP FUNCTION IF EXISTS public.pro9_touch_explicit()');
        DB::statement('DROP FUNCTION IF EXISTS public.pro9_touch_reset()');
        DB::statement('DROP COLLATION IF EXISTS public.pro9_ci_ai');
        DB::statement('DROP COLLATION IF EXISTS public.pro9_search');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
