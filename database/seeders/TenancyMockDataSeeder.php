<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// ########### INICIO DATOS MOCK VENEZUELA
/**
 * Carga datos venezolanos reconocibles para pruebas manuales de ventas.
 * Los valores no alteran ni dependen de catálogos comerciales preexistentes.
 */
class TenancyMockDataSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::connection('tenant');

        $database->transaction(function () use ($database): void {
            $timestamp = '2000-01-01 00:00:00';

            foreach ([
                'cat_identity_document_types' => ['0', '1', '6', '7', 'E', 'C', 'G', 'R'],
                'cat_unit_types' => ['UND', 'SERV'],
                'cat_currency_types' => ['VES'],
                'item_types' => ['01', '02'],
                'cat_affectation_igv_types' => ['10', '20'],
            ] as $table => $ids) {
                $available = $database->table($table)->whereIn('id', $ids)->pluck('id')->map(static fn ($id) => (string) $id)->all();
                $missing = array_values(array_diff($ids, $available));
                if ($missing !== []) {
                    throw new RuntimeException("No están disponibles los catálogos requeridos en {$table}: " . implode(', ', $missing));
                }
            }

            $database->table('categories')->updateOrInsert(
                ['name' => 'MOCK-CATEGORÍA-VENEZUELA'],
                ['updated_at' => $timestamp, 'created_at' => $timestamp]
            );
            $database->table('brands')->updateOrInsert(
                ['name' => 'MOCK-MARCA-VENEZUELA'],
                ['updated_at' => $timestamp, 'created_at' => $timestamp]
            );

            $database->table('persons')->updateOrInsert(
                ['type' => 'customers', 'identity_document_type_id' => '1', 'country_id' => 'VE', 'number' => '12345678'],
                [
                    'type' => 'customers',
                    'identity_document_type_id' => '1',
                    'name' => 'MOCK-CLIENTE-VENEZUELA',
                    'country_id' => 'VE',
                    'nationality_id' => 'VE',
                    'address' => 'MOCK-DIRECCIÓN-CARACAS',
                    'telephone' => '+584121234567',
                    'email' => 'mock-cliente@example.test',
                    'status' => 1,
                    'enabled' => 1,
                    'parent_id' => 0,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );

            $database->table('persons')->updateOrInsert(
                ['type' => 'customers', 'identity_document_type_id' => '6', 'country_id' => 'VE', 'number' => '123456789'],
                [
                    'name' => 'MOCK-EMPRESA-CLIENTE-VENEZUELA',
                    'country_id' => 'VE',
                    'address' => 'MOCK-DIRECCIÓN-CARACAS',
                    'telephone' => '+584121234568',
                    'email' => 'mock-empresa@example.test',
                    'status' => 1,
                    'enabled' => 1,
                    'parent_id' => 0,
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
                    'unit_type_id' => 'UND',
                    'currency_type_id' => 'VES',
                    'sale_unit_price' => 100,
                    'purchase_unit_price' => 50,
                    'sale_affectation_igv_type_id' => '10',
                    'purchase_affectation_igv_type_id' => '10',
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'stock' => 20,
                    'status' => 1,
                    'active' => 1,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );

            $database->table('items')->updateOrInsert(
                ['internal_id' => 'MOCK-SERV-VES-001'],
                [
                    'name' => 'MOCK-SERVICIO-VENEZUELA',
                    'second_name' => 'MOCK-SERVICIO-VENEZUELA',
                    'description' => 'MOCK-SERVICIO-VENEZUELA',
                    'item_type_id' => '02',
                    'item_code' => 'MOCK-SERV-VES-001',
                    'unit_type_id' => 'SERV',
                    'currency_type_id' => 'VES',
                    'sale_unit_price' => 250,
                    'purchase_unit_price' => 0,
                    'sale_affectation_igv_type_id' => '10',
                    'purchase_affectation_igv_type_id' => '10',
                    'stock' => 20,
                    'status' => 1,
                    'active' => 1,
                    'updated_at' => $timestamp,
                    'created_at' => $timestamp,
                ]
            );

            $warehouse = $database->table('warehouses')->orderBy('id')->first();
            if ($warehouse) {
                foreach (['MOCK-ITEM-VES-001', 'MOCK-SERV-VES-001'] as $internalId) {
                    $itemId = $database->table('items')->where('internal_id', $internalId)->value('id');
                    if ($itemId) {
                        $database->table('item_warehouse')->updateOrInsert(
                            ['item_id' => $itemId, 'warehouse_id' => $warehouse->id],
                            ['stock' => 20, 'updated_at' => $timestamp, 'created_at' => $timestamp]
                        );
                    }
                }
            }
        });
    }
}
// ########### FIN DATOS MOCK VENEZUELA
