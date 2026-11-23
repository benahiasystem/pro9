<?php

namespace Tests\Unit;

use App\CoreFacturalo\Facturalo;
use App\Http\Controllers\Tenant\CashController;
use App\Http\Resources\Tenant\{DocumentCollection, DocumentResource};
use App\Providers\DocumentPaymentProvider;
use App\Support\Exact\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\PostgresTestEnvironment;

/** @group postgres-integration */
final class InvoicePaymentBalancePostgresTest extends TestCase
{
    public function test_pos_cash_link_requires_the_original_cash_session(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            config(['view.paths' => [base_path('resources/views')], 'view.compiled' => sys_get_temp_dir()]);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $noteData = array_replace($input, ['id' => null, 'series' => 'NV01', 'document_type_id' => '80', 'prefix' => 'NV']);
            $note = \App\Models\Tenant\SaleNote::create((new \App\Http\Controllers\Tenant\SaleNoteController())->mergeData($noteData));
            $cashA = $db->table('cash')->where('user_id', auth()->id())->where('state', true)->value('id');
            self::assertNotNull($cashA);
            $controller = new CashController();
            foreach (['document_id' => $invoice->id, 'sale_note_id' => $note->id] as $field => $id) {
                $request = Request::create('/cash/cash_document', 'POST', [$field => $id, 'require_existing_cash_link' => true]);
                self::assertTrue($controller->cash_document($request)->getData(true)['success']);
                self::assertTrue($controller->cash_document($request)->getData(true)['success']);
                self::assertSame(1, $db->table('cash_documents')->where('cash_id', $cashA)->where($field, $id)->count());
                self::assertSame(1, $db->table('cash_document_credits')->where('cash_id', $cashA)->where($field, $id)->count());
            }
            $db->table('cash')->where('id', $cashA)->update(['state' => false]);
            $cashB = \App\Models\Tenant\Cash::create(['user_id' => auth()->id(), 'state' => true, 'date_opening' => '2026-09-10', 'time_opening' => '14:00:00', 'beginning_balance' => '0']);
            foreach (['document_id' => $invoice->id, 'sale_note_id' => $note->id] as $field => $id) {
                try {
                    $controller->cash_document(Request::create('/cash/cash_document', 'POST', [$field => $id, 'require_existing_cash_link' => true]));
                    self::fail('Debe rechazarse una caja distinta de la original.');
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    self::assertArrayHasKey('cash', $exception->errors());
                }
            }
            try {
                $controller->cash_document(Request::create('/cash/cash_document', 'POST', ['document_id' => $invoice->id, 'sale_note_id' => $note->id, 'require_existing_cash_link' => true]));
                self::fail('Debe rechazarse un comprobante ambiguo.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
            foreach (['cash_documents', 'cash_document_credits', 'cash_document_payments'] as $table) {
                self::assertSame(0, $db->table($table)->where('cash_id', $cashB->id)->count(), $table);
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_cash_link_compares_the_exact_credit_balance_at_its_tolerance(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            config(['view.paths' => [base_path('resources/views')], 'view.compiled' => sys_get_temp_dir()]);
            $app->register(\Illuminate\View\ViewServiceProvider::class);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            $cashId = $db->table('cash')->where('user_id', auth()->id())->where('state', true)->value('id');
            self::assertNotNull($cashId);

            $payment = $invoice->payments()->make([
                'date_of_payment' => $invoice->date_of_issue,
                'payment_method_type_id' => '01',
                'payment_method_snapshot' => ['collection_kind' => 'cash', 'currency_type_id' => 'VES'],
                'currency_type_id' => 'VES',
                'exchange_rate' => '1',
                'operation_key' => Str::uuid()->toString(),
            ]);
            $request = Request::create('/cash/cash_document', 'POST', ['document_id' => $invoice->id]);
            $credit = $db->table('cash_document_credits')->where('cash_id', $cashId)->where('document_id', $invoice->id);
            $controller = new CashController();
            foreach ([['0.0050000000001', 'PENDING'], ['0.005', 'PROCESSED']] as [$remaining, $status]) {
                $amount = $invoice->exactValue('total_payable')->minus($remaining);
                $payment->setExactValues(['payment' => $amount, 'original_amount' => $amount])->save();
                $invoice->refresh();
                self::assertTrue($invoice->exactBalance()->isEqualTo($remaining));
                self::assertSame('0.005', $invoice->balance);
                self::assertTrue($controller->cash_document($request)->getData(true)['success']);
                self::assertSame($status, $credit->value('status'));
                self::assertSame(1, $credit->count());
            }
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }

    public function test_payment_events_keep_a_subprecision_invoice_balance_open(): void
    {
        $fixture = new FiscalEmissionSchemaTest();
        (new ReflectionMethod($fixture, 'setUp'))->invoke($fixture);
        try {
            [$db, $app, $input] = (new ReflectionMethod($fixture, 'bootHkaFixture'))->invoke($fixture);
            PostgresTestEnvironment::assertServer($db);
            $db->table('configurations')->update(['auto_send_hka' => false]);
            $input['payment_condition_id'] = '02';
            $input['payments'] = [];
            $invoice = (new Facturalo())->forWebDocuments()->save($input)->getDocument()->fresh();
            self::assertSame(0, (int) $invoice->total_canceled);

            $router = new \Illuminate\Routing\Router(new \Illuminate\Events\Dispatcher($app), $app);
            $router->get('downloads/{model}/{type}/{external_id}/{format?}', fn () => null)->name('tenant.download.external_id');
            $router->getRoutes()->refreshNameLookups();
            $app->instance('url', new \Illuminate\Routing\UrlGenerator($router->getRoutes(), app('request')));
            $read = static fn ($document) => [
                (new DocumentCollection(collect([$document])))->resolve()[0],
                (new DocumentResource($document))->resolve(),
            ];

            (new DocumentPaymentProvider($app))->boot();
            $payment = $invoice->payments()->make([
                'date_of_payment' => $invoice->date_of_issue,
                'payment_method_type_id' => '01',
                'payment_method_snapshot' => ['collection_kind' => 'cash', 'currency_type_id' => 'VES'],
                'currency_type_id' => 'VES',
                'exchange_rate' => '1',
                'operation_key' => Str::uuid()->toString(),
            ]);
            $total = $invoice->exactValue('total_payable');
            $remaining = Decimal::of('1/10000000000000');
            $amount = $total->minus($remaining);
            $payment->setExactValues(['payment' => $amount, 'original_amount' => $amount])->save();
            $invoice->refresh();
            self::assertTrue($invoice->exactBalance()->isEqualTo($remaining));
            self::assertSame('0', $invoice->balance);
            self::assertSame(0, (int) $invoice->total_canceled, 'Un saldo positivo impide marcar la factura como pagada.');
            foreach ($read($invoice) as $record) {
                self::assertSame('0', $record['balance']);
                self::assertTrue($record['has_open_balance'] ?? false);
            }

            $payment->setExactValues(['payment' => $total, 'original_amount' => $total])->save();
            self::assertSame(1, (int) $invoice->fresh()->total_canceled);
            foreach ($read($invoice->fresh()) as $record) {
                self::assertSame('0', $record['balance']);
                self::assertFalse($record['has_open_balance'] ?? true);
            }

            $payment->forceFill(['reversed_at' => now()])->save();
            self::assertSame(0, (int) $invoice->fresh()->total_canceled);
            self::assertTrue($invoice->fresh()->exactBalance()->isEqualTo($total));
            [$list, $detail] = $read($invoice->fresh());
            self::assertTrue($list['has_open_balance']);
            self::assertTrue($detail['has_open_balance']);
            self::assertSame('0', $detail['total_paid']);
        } finally {
            (new ReflectionMethod($fixture, 'tearDown'))->invoke($fixture);
        }
    }
}
