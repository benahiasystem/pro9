<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - reminder_days: integer
 * - reminder_type: varchar(255)
 * - reminder_time: time
 * - shipping_medium: varchar(255)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscription_payment_reminders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('reminder_days')->default('0');
            $table->string('reminder_type', 255)->collation('sab_ci_ai')->default('before');
            $table->time('reminder_time');
            $table->string('shipping_medium', 255)->collation('sab_ci_ai')->default('email');
        });
        DB::statement('ALTER TABLE "suscription_payment_reminders" ADD CONSTRAINT "suscription_payment_reminders_reminder_days_range" CHECK ("reminder_days" BETWEEN -2147483648 AND 2147483647)');
        DB::statement('ALTER TABLE "suscription_payment_reminders" ADD CONSTRAINT "suscription_payment_reminders_reminder_type_check" CHECK ("reminder_type" IN (\'before\',\'same_day\',\'after\'))');
        DB::statement('ALTER TABLE "suscription_payment_reminders" ADD CONSTRAINT "suscription_payment_reminders_shipping_medium_check" CHECK ("shipping_medium" IN (\'email\',\'whatsapp\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('suscription_payment_reminders');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
