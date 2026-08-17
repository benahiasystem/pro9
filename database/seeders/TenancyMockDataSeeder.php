<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// ########### INICIO DATOS MOCK VENEZUELA
/**
 * Carga un conjunto mínimo, reconocible e idempotente para pruebas manuales.
 * Ningún registro sin el prefijo MOCK- se crea o modifica desde este seeder.
 */
class TenancyMockDataSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::connection('tenant');

        $database->transaction(function () use ($database): void {
            $timestamp = '2000-01-01 00:00:00';

            $database->table('categories')->updateOrInsert(
                ['name' => 'MOCK-CATEGORÍA-VENEZUELA'],
                ['updated_at' => $timestamp, 'created_at' => $timestamp]
            );
            $database->table('brands')->updateOrInsert(
                ['name' => 'MOCK-MARCA-VENEZUELA'],
                ['updated_at' => $timestamp, 'created_at' => $timestamp]
            );

            $database->table('persons')->updateOrInsert(
                ['number' => 'MOCK-CLIENTE-VE'],
                [
                    'type' => 'customers',
                    'identity_document_type_id' => '1',
                    'name' => 'MOCK-CLIENTE-VENEZUELA',
                    'country_id' => 'VE',
                    'nationality_id' => 'VE',
                    'department_id' => '14',
                    'province_id' => '0229',
                    'district_id' => '000619',
                    'address' => 'MOCK-DIRECCIÓN-CARACAS',
                    'telephone' => '+584121234567',
                    'email' => 'mock-cliente@example.test',
                    'status' => 1,
                    'enabled' => 1,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );

            $categoryId = $database->table('categories')
                ->where('name', 'MOCK-CATEGORÍA-VENEZUELA')
                ->value('id');
            $brandId = $database->table('brands')
                ->where('name', 'MOCK-MARCA-VENEZUELA')
                ->value('id');

            $database->table('items')->updateOrInsert(
                ['internal_id' => 'MOCK-ITEM-VES-001'],
                [
                    'name' => 'MOCK-PRODUCTO-VENEZUELA',
                    'second_name' => 'MOCK-PRODUCTO-VENEZUELA',
                    'description' => 'MOCK-PRODUCTO-VENEZUELA',
                    'item_type_id' => '01',
                    'item_code' => 'MOCK-ITEM-VES-001',
                    'unit_type_id' => 'NIU',
                    'currency_type_id' => 'VES',
                    'sale_unit_price' => 100,
                    'purchase_unit_price' => 50,
                    'sale_affectation_igv_type_id' => '10',
                    'purchase_affectation_igv_type_id' => '10',
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'status' => 1,
                    'active' => 1,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );
        });
    }
}
// ########### FIN DATOS MOCK VENEZUELA
