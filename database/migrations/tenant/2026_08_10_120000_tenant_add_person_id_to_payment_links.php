<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_links', function (Blueprint $table) {

            $table->unsignedInteger('person_id')->nullable()->after('user_id');

            // pending: el link aun no fue pagado | paid: el link fue pagado y se registraron los pagos
            $table->string('status', 20)->default('pending')->after('total');
            $table->dateTime('paid_at')->nullable()->after('status');

            $table->foreign('person_id')->references('id')->on('persons')->onDelete('set null');

            $table->index('status', 'payment_links_status_index');

        });

        // los links existentes que ya tienen un pago asociado estan pagados
        DB::table('payment_links')
            ->whereNotNull('payment_id')
            ->update([
                'status' => 'paid',
                'paid_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Se valida cada objeto porque la migración pudo ejecutarse antes de agregar el estado
     *
     * @return void
     */
    public function down()
    {

        if($this->hasForeignKey('payment_links_person_id_foreign'))
        {
            Schema::table('payment_links', function (Blueprint $table) {
                $table->dropForeign(['person_id']);
            });
        }

        if($this->hasIndex('payment_links_status_index'))
        {
            Schema::table('payment_links', function (Blueprint $table) {
                $table->dropIndex('payment_links_status_index');
            });
        }

        $columns = array_values(array_filter(['person_id', 'status', 'paid_at'], function ($column) {
            return Schema::hasColumn('payment_links', $column);
        }));

        if(count($columns) === 0) return;

        Schema::table('payment_links', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });

    }


    /**
     * @param  string $index
     * @return bool
     */
    private function hasIndex($index)
    {
        return count(Schema::getConnection()->select("SHOW INDEX FROM `payment_links` WHERE Key_name = ?", [$index])) > 0;
    }


    /**
     * Al eliminar una llave foránea mysql conserva el índice con el mismo nombre,
     * por eso se consulta la constraint y no el índice
     *
     * @param  string $foreign_key
     * @return bool
     */
    private function hasForeignKey($foreign_key)
    {

        $constraints = Schema::getConnection()->select("
            select CONSTRAINT_NAME
            from information_schema.TABLE_CONSTRAINTS
            where CONSTRAINT_SCHEMA = database()
                and TABLE_NAME = 'payment_links'
                and CONSTRAINT_TYPE = 'FOREIGN KEY'
                and CONSTRAINT_NAME = ?
        ", [$foreign_key]);

        return count($constraints) > 0;

    }
};
