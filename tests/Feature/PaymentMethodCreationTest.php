<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Sale\Http\Controllers\PaymentMethodTypeController;
use Tests\TestCase;

// ########## INICIO CAMBIO CATÁLOGOS DE NOMBRES
class PaymentMethodCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\LockedAdmin::class);
        DB::purge('tenant');
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::connection('tenant')->create('cat_currency_types', function (Blueprint $table) {
            $table->string('id')->primary(); $table->boolean('active'); $table->string('description'); $table->string('symbol');
        });
        Schema::connection('tenant')->create('cat_iva_rate_types', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('tax_kind'); $table->string('percentage');
        });
        DB::connection('tenant')->table('cat_currency_types')->insert([
            ['id'=>'VES','active'=>1,'description'=>'Bolívares','symbol'=>'Bs.'],
            ['id'=>'USD','active'=>1,'description'=>'Dólares','symbol'=>'$'],
            ['id'=>'XXX','active'=>0,'description'=>'Inactiva','symbol'=>'X'],
        ]);
        DB::connection('tenant')->table('cat_iva_rate_types')->insert([
            ['id'=>'IGTF','tax_kind'=>'IGTF','percentage'=>'3.00'],
            ['id'=>'G','tax_kind'=>'IVA','percentage'=>'16.00'],
        ]);
        Schema::connection('tenant')->create('payment_method_types', function (Blueprint $table) {
            $table->string('id', 2);
            $table->string('description');
            $table->string('hka_code', 2);
            $table->boolean('is_credit')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->string('collection_kind')->default('unclassified');
            $table->string('cash_currency_type_id')->nullable();
            $table->string('currency_type_id')->default('VES');
            $table->string('iva_rate_type_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        $this->actingAs((new \App\Models\Tenant\User())->forceFill(['id'=>1,'type'=>'admin']));
        Route::get('/test-payment-tables', [PaymentMethodTypeController::class, 'tables']);
        Route::get('/test-payment-methods', [PaymentMethodTypeController::class, 'records']);
        Route::get('/test-payment-methods/{id}', [PaymentMethodTypeController::class, 'record']);
        Route::delete('/test-payment-methods/{id}', [PaymentMethodTypeController::class, 'destroy']);
        Route::post('/test-payment-methods', [PaymentMethodTypeController::class, 'store']);
        Route::put('/test-payment-methods/{id}', [PaymentMethodTypeController::class, 'update']);
        Route::post('/test-web-payment-methods', [PaymentMethodTypeController::class, 'store'])->middleware('web');
        Route::post('/test-payment-active', [PaymentMethodTypeController::class, 'active']);
        Route::post('/test-finance-payment-active', [\Modules\Finance\Http\Controllers\PaymentMethodTypeController::class, 'active']);
    }

    public function test_duplicate_code_is_rejected_without_changing_the_existing_method(): void
    {
        DB::connection('tenant')->table('payment_method_types')->insert([
            'id' => '27', 'description' => 'Original', 'hka_code' => '99', 'is_active' => false,
        ]);

        $this->postJson('/test-payment-methods', ['id' => '27', 'description' => 'Duplicado', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertStatus(422)
            ->assertJsonPath('message.id.0', 'El código del método de pago ya existe.');
        $this->withHeaders(['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post('/test-web-payment-methods', ['id' => '27', 'description' => 'Duplicado', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertStatus(422)
            ->assertJsonPath('id.0', 'El código del método de pago ya existe.');

        $rows = DB::connection('tenant')->table('payment_method_types')->get();
        self::assertCount(1, $rows);
        self::assertSame('Original', $rows[0]->description);
    }

    public function test_new_code_can_be_created_and_existing_method_can_be_edited(): void
    {
        $this->postJson('/test-payment-methods', ['id' => '27', 'description' => 'Nuevo', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertOk()->assertJsonPath('success', true);
        self::assertTrue(\App\Models\Tenant\PaymentMethodType::active()->where('id', '27')->exists());
        $this->putJson('/test-payment-methods/27', ['id' => '27', 'description' => 'Editado', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson('/test-payment-methods/27', ['id' => '28', 'description' => 'Incorrecto', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertStatus(422)->assertJsonStructure(['message' => ['id']]);
        $this->putJson('/test-payment-methods/28', ['id' => '28', 'description' => 'Inexistente', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
            ->assertNotFound();

        $rows = DB::connection('tenant')->table('payment_method_types')->get();
        self::assertCount(1, $rows);
        self::assertSame('27', $rows[0]->id);
        self::assertSame('Editado', $rows[0]->description);
        self::assertSame('99', $rows[0]->hka_code);
    }

    public function test_new_methods_are_available_even_if_client_requests_inactive(): void
    {
        foreach (['cash', 'account', 'credit'] as $index => $kind) {
            $id = (string) (31 + $index);
            $this->postJson('/test-payment-methods', [
                'id' => $id, 'description' => 'Nuevo', 'collection_kind' => $kind,
                'currency_type_id' => 'VES', 'is_active' => false,
            ])->assertOk()->assertJsonPath('success', true);
            self::assertSame(1, DB::connection('tenant')->table('payment_method_types')->where('id', $id)->value('is_active'));
        }
        $this->postJson('/test-payment-methods', [
            'id' => '34', 'description' => 'Sin clasificar', 'collection_kind' => 'unclassified', 'currency_type_id' => 'VES',
        ])->assertStatus(422);
        self::assertFalse(DB::connection('tenant')->table('payment_method_types')->where('id', '34')->exists());

        $this->putJson('/test-payment-methods/31', [
            'id' => '31', 'description' => 'Editado', 'collection_kind' => 'cash', 'currency_type_id' => 'VES', 'is_active' => false,
        ])->assertOk();
        self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id', '31')->value('is_active'));
    }
    public function test_initial_methods_are_editable_without_changing_id_or_hka_and_cannot_be_deleted(): void
    {
        DB::connection('tenant')->table('payment_method_types')->insert([
            'id'=>'01', 'description'=>'Efectivo', 'hka_code'=>'08', 'collection_kind'=>'cash',
            'cash_currency_type_id'=>'VES', 'is_cash'=>true, 'is_active'=>true,
        ]);
        foreach (['02', '03', '04', '05', '09', '10', '11', '12', '13'] as $id) {
            DB::connection('tenant')->table('payment_method_types')->insert([
                'id'=>$id, 'description'=>'Inicial', 'hka_code'=>'99', 'collection_kind'=>'account', 'currency_type_id'=>'VES', 'is_active'=>true,
            ]);
        }
        $records = $this->getJson('/test-payment-methods')->assertOk()->json('data');
        self::assertCount(10, $records);
        foreach ($records as $record) {
            self::assertTrue($record['show_actions']);
            self::assertFalse($record['can_delete']);
        }
        $this->putJson('/test-payment-methods/01', [
            'id'=>'01', 'description'=>'Efectivo USD', 'collection_kind'=>'cash', 'currency_type_id'=>'USD', 'cash_currency_type_id'=>'VES', 'hka_code'=>'99',
        ])->assertOk()->assertJsonPath('success', true);
        $row = DB::connection('tenant')->table('payment_method_types')->where('id', '01')->first();
        self::assertSame('USD', $row->currency_type_id);
        self::assertSame('USD', $row->cash_currency_type_id);
        self::assertSame('08', $row->hka_code);
        $this->putJson('/test-payment-methods/01', [
            'id'=>'01', 'description'=>'Cuenta', 'collection_kind'=>'account', 'currency_type_id'=>'VES', 'cash_currency_type_id'=>null,
        ])->assertOk()->assertJsonPath('success', true);
        self::assertSame('account', DB::connection('tenant')->table('payment_method_types')->where('id', '01')->value('collection_kind'));
        $this->deleteJson('/test-payment-methods/01')->assertJsonPath('success', false);
    }

    public function test_retired_ids_can_be_reused_and_unclassified_methods_remain_inactive(): void
    {
        foreach (['06', '07', '14', '26'] as $id) {
            $this->postJson('/test-payment-methods', ['id'=>$id, 'description'=>'Personalizado', 'collection_kind'=>'account', 'currency_type_id'=>'VES'])
                ->assertOk()->assertJsonPath('success', true);
        }
        $this->deleteJson('/test-payment-methods/06')->assertOk()->assertJsonPath('success', true);
        $this->putJson('/test-payment-methods/14', ['id'=>'14', 'description'=>'Pendiente', 'collection_kind'=>'unclassified', 'currency_type_id'=>'VES', 'is_active'=>false])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson('/test-payment-methods/14', ['id'=>'14', 'description'=>'Pendiente', 'collection_kind'=>'unclassified', 'currency_type_id'=>'VES', 'is_active'=>true])
            ->assertStatus(422);
        $this->putJson('/test-payment-methods/26', ['id'=>'26', 'description'=>'Efectivo', 'collection_kind'=>'cash', 'currency_type_id'=>null])
            ->assertStatus(422);
    }

    public function test_currency_and_igtf_options_are_validated_saved_and_removable(): void
    {
        $this->getJson('/test-payment-tables')->assertOk()->assertJsonCount(2, 'currency_types')
            ->assertJsonPath('taxes.0.id', 'IGTF')->assertJsonPath('taxes.0.percentage', '3.00');
        $base = ['id'=>'30', 'description'=>'Moneda', 'collection_kind'=>'account'];
        $this->postJson('/test-payment-methods', $base)->assertStatus(422);
        $this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'XXX'])->assertStatus(422);
        $this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'ZZZ'])->assertStatus(422);
        $this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'USD', 'iva_rate_type_id'=>'G'])->assertStatus(422);
        $this->postJson('/test-payment-methods', $base + ['currency_type_id'=>'USD', 'iva_rate_type_id'=>'IGTF'])->assertOk();
        $this->getJson('/test-payment-methods/30')->assertJsonPath('currency_type_id', 'USD')->assertJsonPath('iva_rate_type_id', 'IGTF');
        $this->putJson('/test-payment-methods/30', $base + ['currency_type_id'=>'VES', 'iva_rate_type_id'=>null])->assertOk();
        $this->getJson('/test-payment-methods/30')->assertJsonPath('currency_type_id', 'VES')->assertJsonPath('iva_rate_type_id', null);
    }

    public function test_only_admin_can_change_availability_or_delete_custom_methods(): void
    {
        DB::connection('tenant')->table('payment_method_types')->insert([
            ['id'=>'14','description'=>'Cuenta','hka_code'=>'99','collection_kind'=>'account','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_active'=>1],
            ['id'=>'15','description'=>'Sin clasificar','hka_code'=>'99','collection_kind'=>'unclassified','currency_type_id'=>'VES','cash_currency_type_id'=>null,'is_active'=>0],
            ['id'=>'01','description'=>'Inicial','hka_code'=>'08','collection_kind'=>'cash','currency_type_id'=>'VES','cash_currency_type_id'=>'VES','is_active'=>1],
        ]);
        $this->actingAs((new \App\Models\Tenant\User())->forceFill(['id'=>2,'type'=>'seller']));
        foreach (['/test-payment-active','/test-finance-payment-active'] as $path) {
            $this->postJson($path,['id'=>'14','is_active'=>false])->assertForbidden();
        }
        $this->deleteJson('/test-payment-methods/14')->assertForbidden();
        self::assertSame(1, DB::connection('tenant')->table('payment_method_types')->where('id','14')->value('is_active'));
        $this->actingAs((new \App\Models\Tenant\User())->forceFill(['id'=>1,'type'=>'admin']));
        foreach (['/test-payment-active','/test-finance-payment-active'] as $path) {
            $this->postJson($path,['id'=>'15','is_active'=>true])->assertStatus(422);
            $this->postJson($path,['id'=>'14','is_active'=>'invalid'])->assertStatus(422);
        }
        self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id','15')->value('is_active'));
        $this->postJson('/test-finance-payment-active',['id'=>'14','is_active'=>false])->assertOk();
        self::assertSame(0, DB::connection('tenant')->table('payment_method_types')->where('id','14')->value('is_active'));
        $this->deleteJson('/test-payment-methods/01')->assertOk()->assertJsonPath('success',false);
        $this->deleteJson('/test-payment-methods/14')->assertOk()->assertJsonPath('success',true);
    }

}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
