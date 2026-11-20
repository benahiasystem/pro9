<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - name: varchar(50)
 * - description: varchar(250)
 * - active: boolean
 * - created_at: timestamp
 * - updated_at: timestamp
 * - days: bigint
 * - default: smallint
 * - color: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_offices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50)->collation('sab_ci_ai');
            $table->string('description', 250)->collation('sab_ci_ai')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->bigInteger('days')->default('0');
            $table->smallInteger('default')->default('0');
            $table->string('color', 255)->collation('sab_ci_ai')->nullable();
        });
        DB::statement('ALTER TABLE "documentary_offices" ADD CONSTRAINT "documentary_offices_days_range" CHECK ("days" BETWEEN 0 AND 16777215)');
        DB::statement('ALTER TABLE "documentary_offices" ADD CONSTRAINT "documentary_offices_default_range" CHECK ("default" BETWEEN 0 AND 255)');
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_offices');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
