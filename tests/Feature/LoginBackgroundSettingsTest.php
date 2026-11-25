<?php

namespace Tests\Feature;

use App\Http\Controllers\Tenant\LoginConfigurationController;
use App\Models\Tenant\Configuration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginBackgroundSettingsTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, static function ($app): void {
            $connection = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''];
            $app['config']->set(['app.env' => 'testing', 'database.default' => 'system', 'database.connections.system' => $connection, 'database.connections.tenant' => $connection, 'tenancy.db.system-connection-name' => 'system', 'cache.default' => 'array', 'session.driver' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array']);
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        Schema::connection('tenant')->create('configurations', function ($table): void {
            $table->increments('id');
            $table->text('login');
            $table->timestamps();
        });
        DB::connection('tenant')->table('configurations')->insert(['login' => json_encode(['image' => '/images/fondo-sab.svg'])]);
        Route::post('/test-login-settings', [LoginConfigurationController::class, 'update']);
    }

    public function test_background_can_be_hidden_and_shown_without_removing_the_image(): void
    {
        foreach ([false, true] as $visible) {
            $this->postJson('/test-login-settings', [
                'position_form' => 'right',
                'position_logo' => 'top-left',
                'show_background_image' => $visible,
            ])->assertOk();
            $login = Configuration::first()->login;
            $this->assertSame($visible, $login->show_background_image);
            $this->assertSame('/images/fondo-sab.svg', $login->image);
        }
    }

    public function test_background_setting_rejects_non_boolean_values(): void
    {
        $this->postJson('/test-login-settings', [
            'position_form' => 'right',
            'position_logo' => 'top-left',
            'show_background_image' => 'invalid',
        ])->assertUnprocessable();
    }
}
