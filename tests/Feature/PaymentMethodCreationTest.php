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
        Schema::connection('tenant')->create('payment_method_types', function (Blueprint $table) {
            $table->string('id', 2);
            $table->string('description');
            $table->string('hka_code', 2);
            $table->boolean('is_credit')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->string('collection_kind')->default('unclassified');
            $table->string('cash_currency_type_id')->nullable();
            $table->boolean('is_active')->default(true);
        });
        $this->actingAs((new \App\Models\Tenant\User())->forceFill(['id'=>1,'type'=>'admin']));
        Route::post('/test-payment-methods', [PaymentMethodTypeController::class, 'store']);
        Route::put('/test-payment-methods/{id}', [PaymentMethodTypeController::class, 'update']);
        Route::post('/test-web-payment-methods', [PaymentMethodTypeController::class, 'store'])->middleware('web');
    }

    public function test_duplicate_code_is_rejected_without_changing_the_existing_method(): void
    {
        DB::connection('tenant')->table('payment_method_types')->insert([
            'id' => '27', 'description' => 'Original', 'hka_code' => '99', 'is_active' => false,
        ]);

        $this->postJson('/test-payment-methods', ['id' => '27', 'description' => 'Duplicado', 'collection_kind'=>'account'])
            ->assertStatus(422)
            ->assertJsonPath('message.id.0', 'El código del método de pago ya existe.');
        $this->withHeaders(['Accept' => 'text/html', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post('/test-web-payment-methods', ['id' => '27', 'description' => 'Duplicado', 'collection_kind'=>'account'])
            ->assertStatus(422)
            ->assertJsonPath('id.0', 'El código del método de pago ya existe.');

        $rows = DB::connection('tenant')->table('payment_method_types')->get();
        self::assertCount(1, $rows);
        self::assertSame('Original', $rows[0]->description);
    }

    public function test_new_code_can_be_created_and_existing_method_can_be_edited(): void
    {
        $this->postJson('/test-payment-methods', ['id' => '27', 'description' => 'Nuevo', 'collection_kind'=>'account'])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson('/test-payment-methods/27', ['id' => '27', 'description' => 'Editado', 'collection_kind'=>'account'])
            ->assertOk()->assertJsonPath('success', true);
        $this->putJson('/test-payment-methods/27', ['id' => '28', 'description' => 'Incorrecto', 'collection_kind'=>'account'])
            ->assertStatus(422)->assertJsonStructure(['message' => ['id']]);
        $this->putJson('/test-payment-methods/28', ['id' => '28', 'description' => 'Inexistente', 'collection_kind'=>'account'])
            ->assertNotFound();

        $rows = DB::connection('tenant')->table('payment_method_types')->get();
        self::assertCount(1, $rows);
        self::assertSame('27', $rows[0]->id);
        self::assertSame('Editado', $rows[0]->description);
        self::assertSame('99', $rows[0]->hka_code);
    }
}
// ######### FIN CAMBIO CATÁLOGOS DE NOMBRES
