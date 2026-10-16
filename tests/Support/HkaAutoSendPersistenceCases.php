<?php

namespace Tests\Support;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\ConfigurationController;
use App\Http\Requests\Tenant\ConfigurationRequest;
use App\Models\Tenant\{Company, Configuration, Document, DocumentTax};
use App\Services\Fiscal\{FiscalDocumentPersistence, FiscalProfileService, HkaAutoSendSettingUpgrade, HkaEmission, HkaIgtfNote};
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait HkaAutoSendPersistenceCases
{
    public function test_hka_auto_send_configuration_requires_admin_and_valid_boolean(): void
    {
        [$db, $app] = $this->bootHkaFixture();
        if (!class_exists('Auth')) class_alias(\Illuminate\Support\Facades\Auth::class, 'Auth');
        self::assertTrue(Configuration::firstOrFail()->auto_send_hka);
        $rules = (new ConfigurationRequest())->rules();
        foreach ([true, false, 1, 0, '1', '0'] as $value) {
            self::assertTrue($app->make('validator')->make(['cron' => true, 'decimal_quantity' => 2, 'auto_send_hka' => $value], $rules)->passes());
        }
        self::assertTrue($app->make('validator')->make(['cron' => true, 'decimal_quantity' => 2, 'auto_send_hka' => 'invalid'], $rules)->fails());
        auth()->user()->type = 'seller';
        $request = ConfigurationRequest::create('/configurations', 'POST', ['id' => 1, 'auto_send_hka' => false, 'stock' => false]);
        $before = (array) $db->table('configurations')->first();
        try { (new ConfigurationController())->store($request); self::fail('Seller changed automatic sending'); }
        catch (HttpException $exception) { self::assertSame(403, $exception->getStatusCode()); }
        self::assertSame($before, (array) $db->table('configurations')->first());
        auth()->user()->type = 'admin';
        $response = (new ConfigurationController())->store($request);
        self::assertFalse(Configuration::firstOrFail()->auto_send_hka);
        self::assertFalse($response['configuration']['auto_send_hka']);
        $response = (new ConfigurationController())->store(ConfigurationRequest::create('/configurations', 'POST', ['id' => 1, 'stock' => true]));
        self::assertFalse($response['configuration']['auto_send_hka']);
        self::assertFalse(Configuration::firstOrFail()->auto_send_hka);
    }

    public function test_hka_auto_send_off_preserves_sale_and_requires_manual_send_after_reactivation(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        config(['hka_notes' => require dirname(__DIR__, 2).'/config/hka_notes.php']);
        $sent = []; $mode = 'confirmed';
        $this->fakeSpeHka($db, $app, $sent, $mode);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $stock = $db->table('item_warehouse')->value('stock');
        $db->beginTransaction();
        $fact = (new Facturalo())->save($input);
        self::assertSame([], $sent);
        $db->commit();
        $document = $fact->getDocument()->fresh();
        self::assertSame('not_requested', $document->emission->status);
        self::assertFalse($document->emission->response['auto_send_requested']);
        self::assertNull($document->emission->payload);
        self::assertNull($document->control_number);
        self::assertSame(HkaEmission::DEFERRED_MESSAGE, $fact->getResponse()['message']);
        self::assertCount(1, $document->payments);
        self::assertEquals(0, $document->balance);
        self::assertEquals($stock - 2, $db->table('item_warehouse')->value('stock'));
        $app->make(Factory::class)->assertNothingSent();
        $db->table('configurations')->update(['auto_send_hka' => true]);
        app(HkaEmission::class)->sendAutomatic($document);
        $app->make(Factory::class)->assertNothingSent();
        self::assertSame('not_requested', $document->fresh()->emission->status);
        app(HkaEmission::class)->send($document);
        self::assertSame('confirmed', $document->fresh()->emission->status);
        self::assertCount(1, $sent);
        self::assertCount(1, $document->fresh()->payments);
        self::assertEquals($stock - 2, $db->table('item_warehouse')->value('stock'));
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $db->table('series_configurations')->where('document_type_id', '07')->update(['number' => 500]);
        $noteInput = $this->noteInput($input, $document, '07', '04', [['affected_document_item_id' => $document->items->first()->id, 'adjustment_amount' => 23.20]]);
        $note = (new Facturalo())->forWebDocuments()->save($noteInput)->getDocument()->fresh();
        self::assertSame('not_requested', $note->emission->status);
        self::assertFalse($note->emission->response['auto_send_requested']);
        self::assertCount(1, $sent);
        $db->table('configurations')->update(['auto_send_hka' => true]);
        self::assertSame($note->id, (new Facturalo())->forWebDocuments()->save($noteInput)->getDocument()->id);
        self::assertSame('not_requested', $note->fresh()->emission->status);
        self::assertCount(1, $sent);
        $result = app(HkaEmission::class)->send($note);
        self::assertSame('confirmed', $note->fresh()->emission->status, $result['diagnostic'] ?? 'Note did not confirm');
        self::assertCount(2, $sent);
        $db->beginTransaction();
        $next = (new Facturalo())->save($input)->getDocument();
        self::assertCount(2, $sent);
        $db->rollBack();
        self::assertNull(Document::find($next->id));
        self::assertCount(2, $sent);
        $next = (new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('confirmed', $next->emission->status);
        self::assertTrue($next->emission->response['auto_send_requested']);
        self::assertCount(3, $sent);
        \Illuminate\Support\Facades\Cache::flush();
        $http = new Factory(); $http->preventStrayRequests();
        $http->fake(function ($request) use ($db) {
            self::assertStringEndsWith('/Autenticacion', $request->url());
            self::assertSame(0, $db->transactionLevel());
            $db->table('configurations')->update(['auto_send_hka' => false]);
            return \Illuminate\Support\Facades\Http::response(['token' => 'synthetic-token', 'expiracion' => date('c', time() + 3600)]);
        });
        \Illuminate\Support\Facades\Http::swap($http);
        $stopped = (new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('not_requested', $stopped->emission->status);
        self::assertNull($stopped->emission->payload);
        $http->assertSentCount(1);
    }

    public function test_hka_auto_send_off_blocks_igtf_callbacks_and_later_resume(): void
    {
        [$db, $app, $input] = $this->bootHkaFixture();
        $sent = []; $mode = 'confirmed';
        $this->fakeSpeHka($db, $app, $sent, $mode);
        $company = Company::firstOrFail();
        FiscalProfileService::store($company, SpeProfiles::profile());
        $company->forceFill(['igtf_enabled' => true, 'igtf_rate' => 3])->save();
        $input['payments'] = []; $input['payment_condition_id'] = '02';
        $invoice = (new Facturalo())->save($input)->getDocument()->fresh();
        self::assertSame('confirmed', $invoice->emission->status);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        $http = new Factory(); $http->preventStrayRequests(); \Illuminate\Support\Facades\Http::swap($http);
        $db->beginTransaction();
        $payment = FiscalDocumentPersistence::payment($invoice, [
            'date_of_payment' => '2026-09-10', 'payment_method_type_id' => '01', 'currency_type_id' => 'USD',
            'exchange_rate' => '10.00000001', 'original_amount' => 5, 'financial_intermediation' => 'none', 'operation_key' => (string) Str::uuid(),
        ]);
        $db->commit();
        $note = Document::findOrFail(DocumentTax::where('document_payment_id', $payment->id)->value('document_id'));
        self::assertTrue(HkaIgtfNote::applies($note));
        self::assertSame('not_requested', $note->emission->status);
        self::assertFalse($note->emission->response['auto_send_requested']);
        self::assertNull($note->emission->payload);
        $http->assertNothingSent();
        $db->table('configurations')->update(['auto_send_hka' => true]);
        HkaIgtfNote::resume($invoice);
        $http->assertNothingSent();
        self::assertSame('not_requested', $note->fresh()->emission->status);
        $this->fakeSpeHka($db, $app, $sent, $mode);
        app(HkaEmission::class)->send($note);
        self::assertSame('confirmed', $note->fresh()->emission->status);
    }

    public function test_hka_auto_send_additive_upgrade_preserves_existing_data_and_is_idempotent(): void
    {
        [$db] = $this->bootHkaFixture();
        $before = (array) $db->table('configurations')->first();
        unset($before['auto_send_hka']);
        $db->statement('ALTER TABLE configurations DROP COLUMN auto_send_hka');
        self::assertTrue(Configuration::firstOrFail()->auto_send_hka);
        $upgrade = new HkaAutoSendSettingUpgrade();
        self::assertTrue($upgrade->upgrade($db, true));
        self::assertFalse($db->getSchemaBuilder()->hasColumn('configurations', 'auto_send_hka'));
        self::assertTrue($upgrade->upgrade($db));
        $after = (array) $db->table('configurations')->first();
        self::assertEquals(1, $after['auto_send_hka']);
        unset($after['auto_send_hka']);
        self::assertSame($before, $after);
        $column = $db->selectOne("SHOW COLUMNS FROM configurations WHERE Field = 'auto_send_hka'");
        self::assertSame('tinyint(1)', $column->Type);
        self::assertSame('NO', $column->Null);
        self::assertEquals('1', $column->Default);
        $db->table('configurations')->update(['auto_send_hka' => false]);
        self::assertFalse($upgrade->upgrade($db));
        self::assertFalse($upgrade->upgrade($db, true));
        self::assertFalse(Configuration::firstOrFail()->auto_send_hka);
    }
}
