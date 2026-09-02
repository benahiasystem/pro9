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
    public function run()
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('123456'),
            'api_token' => Str::random(60),
        ]);


        DB::table('plan_documents')->insert([
            // ########## INICIO CAMBIO QUITAR BOLETA
            ['id' => 1, 'description' => 'Facturas, notas de débito y crédito y anulaciones' ],
            // ######### FIN CAMBIO QUITAR BOLETA
            ['id' => 2, 'description' => 'Guias de remisión' ],
            ['id' => 3, 'description' => 'Retenciones'],
            ['id' => 4, 'description' => 'Percepciones']
        ]);

        Plan::create([
            'name' => 'Ilimitado',
            'pricing' =>  99,
            'limit_users' => 0,
            'limit_documents' =>  0,
            'plan_documents' => [1,2,3,4],
            'is_popular' => true,
            'locked' => true
        ]);

        Plan::create([
            'name' => 'NRUS',
            'pricing' => 49,
            'limit_users' => 0,
            'limit_documents' => 0,
            'plan_documents' => [1],
            'is_popular' => false,
            'locked' => true,
            'establishments_limit' => 1,
            'establishments_unlimited' => false,
            'sales_limit' => 8000,
            'sales_unlimited' => false,
            'module_permissions' => [
                'business' => null,
                'nrus' => true,
                'modules' => [7, 2, 1, 17, 18, 8, 12, 52, 4],
                'apps' => [11, 14, 5, 53],
                'levels' => [1, 2, 5, 8, 15, 84, 16],
            ],
        ]);

    }
}
