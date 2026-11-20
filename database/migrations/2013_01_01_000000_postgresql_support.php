<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
return new class extends Migration {
    public function up(): void { \App\Database\PostgresBootstrap::install(DB::connection()); }
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.sab_touch_updated_at()');
        DB::statement('DROP FUNCTION IF EXISTS public.sab_touch_explicit()');
        DB::statement('DROP FUNCTION IF EXISTS public.sab_touch_reset()');
        DB::statement('DROP COLLATION IF EXISTS public.sab_ci_ai');
        DB::statement('DROP COLLATION IF EXISTS public.sab_search');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
