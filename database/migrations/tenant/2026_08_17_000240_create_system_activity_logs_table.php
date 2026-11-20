<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ########## INICIO CAMBIO POSTGRESQL 17
/** Instalaciones nuevas. Inventario de columnas:
 * - id: bigint (secuencia)
 * - user_id: bigint
 * - system_activity_log_type_id: varchar(255)
 * - date: date
 * - time: time
 * - origin_id: bigint
 * - origin_type: varchar(255)
 * - ip: varchar(255)
 * - device_name: varchar(255)
 * - device_type: varchar(255)
 * - platform_name: varchar(255)
 * - platform_version: varchar(255)
 * - browser_name: varchar(255)
 * - browser_version: varchar(255)
 * - request_email: varchar(255)
 * - route: varchar(255)
 * - location: json
 * - created_at: timestamp
 * - updated_at: timestamp
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id')->nullable();
            $table->string('system_activity_log_type_id', 255)->collation('sab_ci_ai')->nullable();
            $table->date('date');
            $table->time('time');
            $table->bigInteger('origin_id')->nullable();
            $table->string('origin_type', 255)->collation('sab_ci_ai')->nullable();
            $table->string('ip', 255)->collation('sab_ci_ai')->nullable();
            $table->string('device_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('device_type', 255)->collation('sab_ci_ai')->nullable();
            $table->string('platform_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('platform_version', 255)->collation('sab_ci_ai')->nullable();
            $table->string('browser_name', 255)->collation('sab_ci_ai')->nullable();
            $table->string('browser_version', 255)->collation('sab_ci_ai')->nullable();
            $table->string('request_email', 255)->collation('sab_ci_ai')->nullable();
            $table->string('route', 255)->collation('sab_ci_ai')->nullable();
            $table->json('location')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['origin_id', 'origin_type'], 'system_activity_logs_payment_index');
            $table->index(['user_id'], 'system_activity_logs_user_id_foreign');
            $table->index(['system_activity_log_type_id'], 'system_activity_logs_auth_transaction_type_index');
            $table->index(['date'], 'system_activity_logs_date_index');
            $table->index(['time'], 'system_activity_logs_time_index');
            $table->index(['device_type'], 'system_activity_logs_device_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_activity_logs');
    }
};
// ######### FIN CAMBIO POSTGRESQL 17
