<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\System\Plan;
use App\Models\System\User;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    // ########## INICIO CAMBIO POSTGRESQL 17
    public function run()
    {
        $previous=config('tenancy.db.system-connection-name','system');
        $connection=DB::getDefaultConnection();
        if ($connection==='central_migrator') config(['tenancy.db.system-connection-name'=>$connection]);
        try { DB::connection($connection)->transaction(fn ()=>$this->seedInitialData()); }
        finally { config(['tenancy.db.system-connection-name'=>$previous]); }
    }
    private function seedInitialData(): void
    {
        User::firstOrCreate(['email'=>'admin@gmail.com'], [
            'name' => 'Administrador',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('123456'),
            'api_token' => Str::random(60),
        ]);


        $documents = [
            // ########## INICIO CAMBIO QUITAR BOLETA
            ['id' => 1, 'description' => 'Facturas, notas de débito y crédito y anulaciones' ],
            // ######### FIN CAMBIO QUITAR BOLETA
            ['id' => 2, 'description' => 'Órdenes de entrega' ],
            ['id' => 3, 'description' => 'Retenciones'],
            ['id' => 4, 'description' => 'Percepciones']
        ];
        foreach ($documents as $document) DB::table('plan_documents')->updateOrInsert(['id'=>$document['id']],$document);

        Plan::firstOrCreate(['name'=>'Ilimitado'], [
            'name' => 'Ilimitado',
            'pricing' =>  99,
            'limit_users' => 0,
            'limit_documents' =>  0,
            'plan_documents' => [1,2,3,4],
            'is_popular' => true,
            'locked' => true
        ]);

        \App\Database\SequenceSynchronizer::sync(DB::connection());
    }
    // ######### FIN CAMBIO POSTGRESQL 17
}
