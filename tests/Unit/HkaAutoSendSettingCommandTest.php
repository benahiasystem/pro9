<?php

namespace Tests\Unit;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class HkaAutoSendSettingCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['tenant', 'system'] as $name) {
            config(['database.connections.'.$name => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
            DB::purge($name);
        }
        $tenancy = $this->getMockBuilder(\Hyn\Tenancy\Database\Connection::class)->disableOriginalConstructor()->onlyMethods(['tenantName', 'systemName'])->getMock();
        $tenancy->method('tenantName')->willReturn('tenant');
        $tenancy->method('systemName')->willReturn('system');
        $this->app->instance(\Hyn\Tenancy\Database\Connection::class, $tenancy);
        Schema::connection('system')->create('websites', function ($table) {
            $table->increments('id'); $table->string('uuid'); $table->softDeletes();
        });
        DB::connection('system')->table('websites')->insert([['id' => 1, 'uuid' => 'first'], ['id' => 2, 'uuid' => 'second']]);
        Schema::connection('tenant')->create('configurations', function ($table) {
            $table->increments('id'); $table->boolean('options_pos')->default(true); $table->string('formats');
        });
        DB::connection('tenant')->table('configurations')->insert(['formats' => 'unchanged']);
    }

    public function test_missing_ambiguous_invalid_or_unknown_targets_do_not_change_a_database(): void
    {
        foreach ([[], ['--tenant' => '1', '--all-tenants' => true], ['--tenant' => 'invalid'], ['--tenant' => '0'], ['--tenant' => '999']] as $options) {
            $this->artisan('hka:upgrade-auto-send-setting', $options)->assertExitCode(1);
            self::assertFalse(Schema::connection('tenant')->hasColumn('configurations', 'auto_send_hka'));
        }
    }

    public function test_selected_tenant_dry_run_apply_and_repeat_restore_the_previous_context(): void
    {
        $previous = new Website(); $previous->setRawAttributes(['id' => 99]);
        $current = $previous; $selected = [];
        $environment = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->onlyMethods(['tenant'])->getMock();
        $environment->method('tenant')->willReturnCallback(function (...$arguments) use (&$current, &$selected) {
            if (!empty($arguments[0])) { $current = $arguments[0]; $selected[] = $current->id; }
            return $current;
        });
        $this->app->instance(Environment::class, $environment);
        $this->artisan('hka:upgrade-auto-send-setting', ['--tenant' => '2', '--dry-run' => true])->assertExitCode(0);
        self::assertSame([2, 99], $selected);
        self::assertSame($previous, $current);
        self::assertFalse(Schema::connection('tenant')->hasColumn('configurations', 'auto_send_hka'));
        $this->artisan('hka:upgrade-auto-send-setting', ['--tenant' => '2'])->assertExitCode(0);
        self::assertEquals(1, DB::connection('tenant')->table('configurations')->value('auto_send_hka'));
        self::assertSame('unchanged', DB::connection('tenant')->table('configurations')->value('formats'));
        DB::connection('tenant')->table('configurations')->update(['auto_send_hka' => false]);
        $this->artisan('hka:upgrade-auto-send-setting', ['--tenant' => '2'])->assertExitCode(0);
        self::assertEquals(0, DB::connection('tenant')->table('configurations')->value('auto_send_hka'));
        self::assertSame($previous, $current);
    }

    public function test_all_tenants_and_a_failed_upgrade_restore_the_original_context(): void
    {
        $previous = new Website(); $previous->setRawAttributes(['id' => 99]);
        $current = $previous; $selected = [];
        $environment = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->onlyMethods(['tenant'])->getMock();
        $environment->method('tenant')->willReturnCallback(function ($website = null) use (&$current, &$selected) {
            if ($website) { $current = $website; $selected[] = $website->id; }
            return $current;
        });
        $this->app->instance(Environment::class, $environment);
        $this->artisan('hka:upgrade-auto-send-setting', ['--all-tenants' => true, '--dry-run' => true])->assertExitCode(0);
        self::assertSame([1, 2, 99], $selected);
        self::assertSame($previous, $current);
        Schema::connection('tenant')->drop('configurations');
        $this->artisan('hka:upgrade-auto-send-setting', ['--tenant' => '2'])->assertExitCode(1);
        self::assertSame($previous, $current);
    }

    public function test_a_previously_empty_tenant_context_is_cleared_after_the_command(): void
    {
        $tenantContract = \Hyn\Tenancy\Contracts\Tenant::class;
        $this->app->instance($tenantContract, null);
        $connection = config('database.connections.tenant');
        $environment = $this->getMockBuilder(Environment::class)->disableOriginalConstructor()->onlyMethods(['tenant'])->getMock();
        $environment->method('tenant')->willReturnCallback(function ($website = null) use ($tenantContract) {
            if ($website) $this->app->instance($tenantContract, $website);
            return $this->app->make($tenantContract);
        });
        $this->app->instance(Environment::class, $environment);
        $this->artisan('hka:upgrade-auto-send-setting', ['--tenant' => '2', '--dry-run' => true])->assertExitCode(0);
        self::assertNull($this->app->make($tenantContract));
        self::assertSame($connection, config('database.connections.tenant'));
    }
}
