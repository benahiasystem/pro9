<?php
namespace Tests\Unit;

use App\Http\Controllers\Tenant\SeriesController;
use App\Http\Controllers\Tenant\SeriesDeviceGroupController;
use App\Http\Requests\Tenant\SeriesRequest;
use App\Models\Tenant\Series;
use App\Services\SeriesNumbering;
use App\Services\SeriesResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Support\SeriesDatabaseTestCase;

class SeriesNumberingTest extends SeriesDatabaseTestCase
{
    private function createSeries(string $type = '01', string $code = 'FF01', int $start = 100, int $branch = 1, bool $dedicated = false, bool $contingency = false): int
    {
        $request = SeriesRequest::create('/', 'POST', ['establishment_id' => $branch, 'document_type_id' => $type, 'number' => $code, 'correlative' => $start, 'dedicated' => $dedicated, 'contingency' => $contingency]);
        app('validator')->make($request->all(), $request->rules())->validate();
        self::assertTrue((new SeriesController())->store($request)['success']);
        return (int) $this->db->table('series')->where('number', $code)->where('establishment_id', $branch)->value('id');
    }

    private function emit(string $type, string $code, string $table = 'documents', string $environment = 'demo', $number = '#', int $branch = 1): int
    {
        return $this->db->transaction(function () use ($type, $code, $table, $environment, $number, $branch) {
            $model = new SeriesDocumentSubject();
            $model->setTable($table);
            $model->fiscal_environment = $environment;
            $allocated = SeriesNumbering::next($model, $type, $code, $number, $branch);
            $model->forceFill(['document_type_id' => $type, 'series' => $code, 'number' => $allocated, 'establishment_id' => $branch])->save();
            return $allocated;
        });
    }

    /** @dataProvider documents */
    public function test_first_and_next_document_use_configured_start(string $type, string $code, string $table): void
    {
        $this->createSeries($type, $code);
        self::assertSame(100, $this->emit($type, $code, $table));
        self::assertSame(101, $this->emit($type, $code, $table));
        self::assertTrue(Series::where('number', $code)->first()->in_use);
        self::assertNull($this->db->table($table)->value('control_number'));
    }

    public static function documents(): array
    {
        return [['01', 'FF01', 'documents'], ['07', 'FC01', 'documents'], ['08', 'FD01', 'documents'], ['09', 'TT01', 'dispatches'], ['80', 'NV01', 'sale_notes'], ['U3', 'AS01', 'guides'], ['U2', 'AI01', 'guides'], ['U4', 'AT01', 'inventories_transfer']];
    }

    /** @dataProvider selectedInvoiceSeries */
    public function test_selected_invoice_series_id_is_resolved_and_numbered_without_using_ff01(string $code): void
    {
        $this->createSeries('01', 'FF01', 100);
        $selectedId = $this->createSeries('01', $code, 457);
        $series = \App\CoreFacturalo\Requests\Web\Validation\Functions::findSeries([
            'document_type_id' => '01', 'establishment_id' => 1,
            'series_id' => $selectedId, 'series' => 'FF01',
        ]);
        self::assertSame($selectedId, $series->id);
        self::assertSame($code, $series->number);
        self::assertSame(457, $this->emit('01', $series->number));
        self::assertSame($code, $this->db->table('documents')->value('series'));
        self::assertFalse((bool) Series::where('number', 'FF01')->first()->in_use);
    }

    public static function selectedInvoiceSeries(): array
    {
        return [[''], ['R4MIRAMONTES'], ['F234-HHFDGGGG']];
    }

    /** @dataProvider documents */
    public function test_blank_series_are_numbered_independently_by_branch(string $type, string $code, string $table): void
    {
        $first = $this->createSeries($type, '', 1, 1);
        $second = $this->createSeries($type, '', 1, 2);
        self::assertSame(1, $this->emit($type, '', $table));
        // A missing in_use flag in branch 1 must not mark branch 2 as used.
        $this->db->table('series')->where('id', $first)->update(['in_use' => 0]);
        self::assertTrue(SeriesNumbering::used(Series::find($first)));
        self::assertFalse(SeriesNumbering::used(Series::find($second)));
        self::assertSame(1, $this->emit($type, '', $table, 'demo', '#', 2));
        self::assertSame(2, $this->emit($type, '', $table));
        self::assertSame(2, $this->emit($type, '', $table, 'demo', '#', 2));
        try { $this->emit($type, '', $table, 'demo', 1, 2); self::fail('Duplicate in branch 2'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('number', $exception->errors()); }
    }

    public function test_empty_variants_are_normalized_and_registered_once_per_branch(): void
    {
        $alreadyCreated = false;
        foreach ([[], ['number' => null], ['number' => ''], ['number' => '   ']] as $variant) {
            $request = SeriesRequest::create('/', 'POST', array_merge(['establishment_id' => 1, 'document_type_id' => '01'], $variant));
            app('validator')->make($request->all(), $request->rules())->validate();
            $result = (new SeriesController())->store($request);
            self::assertSame(!$alreadyCreated, $result['success']);
            $alreadyCreated = true;
        }
        self::assertSame('', $this->db->table('series')->value('number'));
        self::assertSame(1, $this->emit('01', ''));
    }

    public function test_empty_series_cannot_be_duplicated_in_the_same_branch_even_in_other_modes(): void
    {
        $this->createSeries('01', '', 50);
        foreach (['dedicated', 'contingency'] as $mode) {
            $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 1, 'document_type_id' => '01', 'number' => '', $mode => true]);
            self::assertFalse((new SeriesController())->store($request)['success']);
        }
        self::assertSame(50, $this->emit('01', ''));
        self::assertSame(51, $this->emit('01', ''));
    }

    public function test_named_series_remain_unique_across_branches_and_invalid_codes_are_rejected(): void
    {
        $this->createSeries();
        $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 2, 'document_type_id' => '01', 'number' => ' ff01 ']);
        self::assertFalse((new SeriesController())->store($request)['success']);
        foreach ([str_repeat('A', 21), 'FF_1', 'AB 01', 'ÁB01', 1234, []] as $invalid) {
            $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 1, 'document_type_id' => '01', 'number' => $invalid]);
            self::assertTrue(app('validator')->make($request->all(), $request->rules())->fails());
        }
    }

    public function test_request_preparation_normalizes_lowercase_codes_before_validation(): void
    {
        $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 1, 'document_type_id' => '01', 'number' => ' ff02 ']);
        $prepare = new \ReflectionMethod(SeriesRequest::class, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);
        app('validator')->make($request->all(), $request->rules())->validate();
        self::assertSame('FF02', $request->number);
        self::assertTrue((new SeriesController())->store($request)['success']);
    }

    public function test_free_series_of_one_to_twenty_characters_work_for_every_type_and_mode(): void
    {
        foreach (array_unique(array_column(\App\Services\SeriesCodeGenerator::availableTypes(false), 'document_type_id')) as $type) {
            foreach (['normal', 'dedicated', 'contingency'] as $mode) {
                foreach (['X', 'AB01', 'ABCDEFGHIJKLMNOP1234', 'abc-DEF-01', '-A--1-'] as $code) {
                    $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 1, 'document_type_id' => $type,
                        'number' => $code, 'dedicated' => $mode === 'dedicated', 'contingency' => $mode === 'contingency']);
                    self::assertFalse(app('validator')->make($request->all(), $request->rules())->fails(), $type . '/' . $mode . '/' . $code);
                }
            }
        }
        $code = 'AB-CD123456789012345';
        $this->createSeries('01', $code);
        self::assertSame(100, $this->emit('01', $code));
        self::assertSame(101, $this->emit('01', $code));
    }

    public function test_series_with_hyphens_are_normalized_and_duplicate_codes_are_rejected(): void
    {
        $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 1, 'document_type_id' => '01', 'number' => 'ab-cD-01']);
        $prepare = new \ReflectionMethod(SeriesRequest::class, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);
        app('validator')->make($request->all(), $request->rules())->validate();
        self::assertTrue((new SeriesController())->store($request)['success']);
        self::assertSame('AB-CD-01', $this->db->table('series')->value('number'));
        self::assertSame(1, $this->emit('01', 'AB-CD-01'));
        $request = SeriesRequest::create('/', 'POST', ['establishment_id' => 2, 'document_type_id' => '01', 'number' => 'AB-CD-01']);
        self::assertFalse((new SeriesController())->store($request)['success']);
    }

    public function test_inventory_generator_ignores_long_numeric_suffixes_in_manual_codes(): void
    {
        $this->createSeries('U2', 'AI01');
        $this->createSeries('U2', 'AI999999999999999999');
        self::assertSame('AI02', app(\App\Services\SeriesCodeGenerator::class)->nextCode('AI'));
        $this->createSeries('U2', 'AI99');
        $this->createSeries('U2', 'AI100');
        self::assertSame('AI101', app(\App\Services\SeriesCodeGenerator::class)->nextCode('AI'));
    }

    public function test_document_search_and_configuration_requests_preserve_twenty_character_series(): void
    {
        foreach ([\App\Http\Requests\Tenant\DocumentRequest::class, \App\Http\Requests\Tenant\SearchRequest::class,
            \Modules\Document\Http\Requests\SeriesConfigurationsRequest::class] as $class) {
            $request = $class::create('/', 'POST', ['series' => ' abcdefghijklmnop1234 ']);
            $prepare = new \ReflectionMethod($class, 'prepareForValidation');
            $prepare->setAccessible(true);
            $prepare->invoke($request);
            self::assertSame('ABCDEFGHIJKLMNOP1234', $request->input('series'));
            $rules = ['series' => $request->rules()['series']];
            self::assertFalse(app('validator')->make($request->all(), $rules)->fails());
            foreach ([str_repeat('A', 21), 'ABC DEF', 'ABC_DEF'] as $invalid) {
                self::assertTrue(app('validator')->make(['series' => $invalid], $rules)->fails());
            }
        }
    }

    public function test_blank_resolution_requires_branch_and_obeys_user_permissions(): void
    {
        $this->createSeries('01', '', 1, 2);
        try { SeriesNumbering::resolve('01', null); self::fail('Branch required'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('establishment_id', $exception->errors()); }
        $this->user->type = 'seller';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->emit('01', '', 'documents', 'demo', '#', 2);
    }

    public function test_blank_series_failure_reverts_usage_and_start_and_dedicated_stays_protected(): void
    {
        $id = $this->createSeries('01', '', 100);
        try {
            $this->db->transaction(function () { $this->emit('01', ''); throw new \RuntimeException('Failure'); });
        } catch (\RuntimeException $exception) {}
        self::assertFalse(SeriesNumbering::used(Series::find($id)));
        self::assertSame(100, $this->emit('01', ''));
        $this->createSeries('01', '', 100, 2, true);
        $this->expectException(ValidationException::class);
        $this->emit('01', '', 'documents', 'demo', '#', 2);
    }

    public function test_environment_does_not_take_numbers_from_another_environment(): void
    {
        $this->createSeries();
        self::assertSame(100, $this->emit('01', 'FF01'));
        self::assertSame(100, $this->emit('01', 'FF01', 'documents', 'production'));
        self::assertSame(101, $this->emit('01', 'FF01'));
    }

    public function test_failed_save_rolls_back_usage_and_can_reuse_first_number(): void
    {
        $id = $this->createSeries();
        try {
            $this->db->transaction(function () { $this->emit('01', 'FF01'); throw new \RuntimeException('Simulated failed write'); });
        } catch (\RuntimeException $exception) {}
        self::assertFalse(Series::find($id)->in_use);
        self::assertSame(0, $this->db->table('documents')->count());
        self::assertSame(100, $this->emit('01', 'FF01'));
    }

    public function test_used_series_cannot_change_initial_number_or_be_deleted_even_with_missing_flag(): void
    {
        $id = $this->createSeries();
        $this->emit('01', 'FF01');
        $this->db->table('series')->where('id', $id)->update(['in_use' => 0]);
        $controller = new SeriesController();
        self::assertFalse($controller->updateCorrelative(Request::create('/', 'POST', ['correlative' => 500]), $id)['success']);
        self::assertFalse($controller->destroy($id)['success']);
        self::assertSame(100, (int) $this->db->table('series_configurations')->value('number'));
        self::assertTrue($controller->records(1)['data'][0]['in_use']);
    }

    public function test_unused_series_can_edit_delete_and_recreate_without_hka(): void
    {
        $id = $this->createSeries();
        $controller = new SeriesController();
        self::assertTrue($controller->updateCorrelative(Request::create('/', 'POST', ['correlative' => 200]), $id)['success']);
        self::assertSame(200, (int) $this->db->table('series_configurations')->value('number'));
        self::assertTrue($controller->destroy($id)['success']);
        self::assertSame(0, $this->db->table('series_configurations')->count());
        $this->createSeries();
        self::assertSame(100, $this->emit('01', 'FF01'));
    }

    public function test_duplicate_number_is_rejected_and_does_not_consume_another(): void
    {
        $this->createSeries();
        $this->emit('01', 'FF01');
        try { $this->emit('01', 'FF01', 'documents', 'demo', 100); self::fail('Must reject duplicate'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('number', $exception->errors()); }
        self::assertSame(101, $this->emit('01', 'FF01'));
    }

    public function test_emission_cannot_choose_series_from_another_branch_or_wrong_type(): void
    {
        $this->createSeries('01', 'FF01', 100, 2);
        $this->expectException(ValidationException::class);
        $this->emit('01', 'FF01');
    }

    public function test_admin_permissions_are_required_to_manage_series(): void
    {
        $this->user->type = 'seller';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new SeriesController())->records(1);
    }

    public function test_group_write_rejects_foreign_series_atomically(): void
    {
        $id = $this->createSeries('01', 'FF01', 100, 2, true);
        $controller = new SeriesDeviceGroupController(new SeriesResolver());
        try { $controller->store(Request::create('/', 'POST', ['establishment_id' => 1, 'name' => 'Caja', 'series_ids' => [$id]])); self::fail('Foreign series'); }
        catch (ValidationException $exception) { self::assertArrayHasKey('series_ids', $exception->errors()); }
        self::assertSame(0, $this->db->table('series_device_groups')->count());
        self::assertNull(Series::find($id)->series_device_group_id);
    }

    public function test_dedicated_group_selection_binding_and_unbinding(): void
    {
        $id = $this->createSeries('01', 'FF01', 100, 1, true);
        $controller = new SeriesDeviceGroupController(new SeriesResolver());
        self::assertTrue($controller->store(Request::create('/', 'POST', ['establishment_id' => 1, 'name' => 'Caja 1', 'series_ids' => [$id]]))['success']);
        $group = (int) $this->db->table('series_device_groups')->value('id');
        self::assertTrue($controller->bind(Request::create('/', 'POST', ['group_id' => $group, 'device_name' => 'Equipo 1']))['success']);
        self::assertFalse($controller->bind(Request::create('/', 'POST', ['group_id' => $group, 'device_name' => 'Otro equipo']))['success']);
        self::assertTrue($controller->store(Request::create('/', 'POST', ['id' => $group, 'establishment_id' => 1, 'name' => 'Caja editada', 'series_ids' => [$id]]))['success']);
        self::assertSame('Caja editada', $this->db->table('series_device_groups')->value('name'));
        app()->instance('request', Request::create('/', 'GET', [], [SeriesResolver::COOKIE_KEY => 'Equipo 1']));
        self::assertSame($group, (new SeriesResolver())->activeGroupId());
        self::assertSame(100, $this->emit('01', 'FF01'));
        self::assertTrue($controller->unbind($group)['success']);
        self::assertNull((new SeriesResolver())->activeGroupId());
        $this->expectException(ValidationException::class);
        $this->emit('01', 'FF01');
    }

    public function test_contingency_series_has_independent_correlative(): void
    {
        $this->createSeries();
        $this->createSeries('01', '0001', 50, 1, false, true);
        self::assertSame(100, $this->emit('01', 'FF01'));
        self::assertSame(50, $this->emit('01', '0001'));
        self::assertSame(51, $this->emit('01', '0001'));
    }

    public function test_same_id_in_another_tenant_does_not_resolve_original_series(): void
    {
        $id = $this->createSeries();
        $this->manager->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'other_tenant');
        $other = $this->manager->getConnection('other_tenant');
        $other->statement('CREATE TABLE series (id INTEGER PRIMARY KEY, document_type_id TEXT, number TEXT, establishment_id INTEGER)');
        $other->table('series')->insert(['id' => $id, 'document_type_id' => '01', 'number' => 'FF02', 'establishment_id' => 1]);
        $this->tenantConnection = 'other_tenant';
        $this->expectException(ValidationException::class);
        SeriesNumbering::resolve('01', 'FF01', 1);
    }

    public function test_allocation_requires_document_transaction(): void
    {
        $this->expectException(\LogicException::class);
        SeriesNumbering::next(new SeriesDocumentSubject(), '01', 'FF01', '#', 1);
    }
}

class SeriesDocumentSubject extends Model
{
    protected $connection = 'tenant';
    protected $table = 'documents';
    protected $guarded = [];
}
